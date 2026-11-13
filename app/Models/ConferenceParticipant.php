<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceParticipant extends Model
{
    use HasFactory;

    protected $table = 'conference_participants';

    protected $fillable = [
        'conference_record_id',
        'participant_name',
        'user_identifier',
        'display_name',
        'user_type',
        'user_id',
        'earliest_start_time',
        'latest_end_time',
        'duration_seconds',
        'raw_metadata',
        'raw_data',
    ];

    protected $casts = [
        'earliest_start_time' => 'datetime',
        'latest_end_time' => 'datetime',
        'duration_seconds' => 'integer',
        'raw_metadata' => 'array',
        'raw_data' => 'array',
    ];

    /**
     * Parent conference record.
     */
    public function conferenceRecord(): BelongsTo
    {
        return $this->belongsTo(ConferenceRecord::class, 'conference_record_id');
    }

    /**
     * Associated system user if linked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for signed-in Google account attendees.
     */
    public function scopeSignedIn(Builder $query): Builder
    {
        return $query->whereIn('user_type', ['signedin', 'signed_in']);
    }

    /**
     * Scope for anonymous attendees.
     */
    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->where('user_type', 'anonymous');
    }

    /**
     * Scope for phone attendees.
     */
    public function scopePhone(Builder $query): Builder
    {
        return $query->where('user_type', 'phone');
    }

    /**
     * Scope by conference record ID.
     */
    public function scopeByRecord(Builder $query, int $recordId): Builder
    {
        return $query->where('conference_record_id', $recordId);
    }

    /**
     * Check if participant is a signed-in Google user.
     */
    public function isSignedIn(): bool
    {
        return in_array($this->user_type, ['signedin', 'signed_in'], true);
    }

    /**
     * Check if participant joined anonymously.
     */
    public function isAnonymous(): bool
    {
        return $this->user_type === 'anonymous';
    }

    /**
     * Check if participant joined via phone dial-in.
     */
    public function isPhone(): bool
    {
        return $this->user_type === 'phone';
    }

    /**
     * Calculate participant session duration in seconds.
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (isset($this->attributes['duration_seconds']) && $this->attributes['duration_seconds'] !== null) {
            return (int) $this->attributes['duration_seconds'];
        }

        if ($this->earliest_start_time && $this->latest_end_time) {
            return abs($this->latest_end_time->diffInSeconds($this->earliest_start_time));
        }

        return null;
    }

    /**
     * Human readable duration format.
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
}
