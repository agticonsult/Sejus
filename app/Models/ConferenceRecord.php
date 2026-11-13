<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ConferenceRecord extends Model
{
    use HasFactory;

    protected $table = 'conference_records';

    protected $fillable = [
        'meeting_room_id',
        'conference_record_name',
        'space_name',
        'status',
        'start_time',
        'end_time',
        'expire_time',
        'synced_at',
        'raw_metadata',
        'raw_data',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'raw_metadata' => 'array',
        'raw_data' => 'array',
    ];

    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = $value ? Carbon::parse($value)->utc()->format('Y-m-d H:i:s.u') : null;
    }

    public function getStartTimeAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = $value ? Carbon::parse($value)->utc()->format('Y-m-d H:i:s.u') : null;
    }

    public function getEndTimeAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    public function setExpireTimeAttribute($value): void
    {
        $this->attributes['expire_time'] = $value ? Carbon::parse($value)->utc()->format('Y-m-d H:i:s.u') : null;
    }

    public function getExpireTimeAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    /**
     * Associated local meeting room.
     */
    public function meetingRoom(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    /**
     * Participants of this conference.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConferenceParticipant::class, 'conference_record_id');
    }

    /**
     * Video recordings captured during this conference.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(ConferenceRecording::class, 'conference_record_id');
    }

    /**
     * Transcripts generated during this conference.
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(ConferenceTranscript::class, 'conference_record_id');
    }

    /**
     * Granular transcript entries accessed through transcripts.
     */
    public function transcriptEntries(): HasManyThrough
    {
        return $this->hasManyThrough(
            ConferenceTranscriptEntry::class,
            ConferenceTranscript::class,
            'conference_record_id',
            'conference_transcript_id',
            'id',
            'id'
        );
    }

    /**
     * Scope for ended conferences.
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', 'ended')->orWhereNotNull('end_time');
    }

    /**
     * Scope for active/ongoing conferences.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by space resource name (e.g. 'spaces/xyz123').
     */
    public function scopeBySpaceName(Builder $query, string $spaceName): Builder
    {
        return $query->where('space_name', $spaceName);
    }

    /**
     * Scope by conference record resource name.
     */
    public function scopeByConferenceName(Builder $query, string $name): Builder
    {
        $normalized = ltrim($name, '/');
        if (!str_starts_with($normalized, 'conferenceRecords/')) {
            $normalized = "conferenceRecords/{$normalized}";
        }
        return $query->where('conference_record_name', $normalized);
    }

    /**
     * Alias for scopeByConferenceName.
     */
    public function scopeByRecordName(Builder $query, string $name): Builder
    {
        return $this->scopeByConferenceName($query, $name);
    }

    /**
     * Calculate total conference duration in seconds.
     */
    public function durationInSeconds(): ?int
    {
        if ($this->start_time && $this->end_time) {
            return abs($this->end_time->diffInSeconds($this->start_time));
        }
        return null;
    }

    /**
     * Calculate total conference duration in minutes.
     */
    public function durationInMinutes(): ?int
    {
        $seconds = $this->durationInSeconds();
        return $seconds !== null ? (int) round($seconds / 60) : null;
    }

    /**
     * Accessor for duration_seconds.
     */
    public function getDurationSecondsAttribute(): ?int
    {
        return $this->durationInSeconds();
    }

    /**
     * Human readable duration string (e.g. "1h 05m 32s" or "45m 12s").
     */
    public function getDurationHumanAttribute(): ?string
    {
        $seconds = $this->duration_seconds;
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
        }

        return sprintf('%02dm %02ds', $minutes, $secs);
    }

    /**
     * Determine if conference has concluded.
     */
    public function isEnded(): bool
    {
        return !is_null($this->end_time) || $this->status === 'ended';
    }

    /**
     * Determine if conference is active/ongoing.
     */
    public function isActive(): bool
    {
        return is_null($this->end_time) && $this->status === 'active';
    }

    /**
     * Determine if conference has video recordings.
     */
    public function hasRecordings(): bool
    {
        return $this->recordings()->count() > 0;
    }

    /**
     * Determine if conference has transcripts.
     */
    public function hasTranscripts(): bool
    {
        return $this->transcripts()->count() > 0;
    }

    /**
     * Determine if conference retention period is expired.
     */
    public function isExpired(): bool
    {
        return $this->expire_time && $this->expire_time->isPast();
    }
}
