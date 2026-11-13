<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceRecording extends Model
{
    use HasFactory;

    protected $table = 'conference_recordings';

    protected $fillable = [
        'conference_record_id',
        'recording_name',
        'state',
        'drive_file_id',
        'export_uri',
        'start_time',
        'end_time',
        'raw_metadata',
        'raw_data',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
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
     * Drive view link accessor.
     */
    public function getDriveLinkAttribute(): ?string
    {
        if (!empty($this->export_uri)) {
            return $this->export_uri;
        }

        if (!empty($this->drive_file_id)) {
            return "https://drive.google.com/file/d/{$this->drive_file_id}/view";
        }

        return null;
    }

    /**
     * Drive direct download link accessor.
     */
    public function getDriveDownloadLinkAttribute(): ?string
    {
        if (!empty($this->drive_file_id)) {
            return "https://drive.google.com/uc?export=download&id={$this->drive_file_id}";
        }

        return null;
    }

    /**
     * Recording duration in seconds.
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->start_time && $this->end_time) {
            return abs($this->end_time->diffInSeconds($this->start_time));
        }

        return null;
    }

    /**
     * Check if recording file has completed generation in Google Drive.
     */
    public function isGenerated(): bool
    {
        return $this->state === 'FILE_GENERATED';
    }

    /**
     * Alias for isGenerated.
     */
    public function isFileGenerated(): bool
    {
        return $this->isGenerated();
    }

    /**
     * Check if recording is currently processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this->state, ['STARTED', 'ENDED'], true);
    }

    /**
     * Scope for generated recordings.
     */
    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('state', 'FILE_GENERATED');
    }

    /**
     * Scope by conference record ID.
     */
    public function scopeByRecord(Builder $query, int $recordId): Builder
    {
        return $query->where('conference_record_id', $recordId);
    }
}
