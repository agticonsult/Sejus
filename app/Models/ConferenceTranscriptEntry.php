<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceTranscriptEntry extends Model
{
    use HasFactory;

    protected $table = 'conference_transcript_entries';

    protected $fillable = [
        'conference_transcript_id',
        'entry_name',
        'participant_name',
        'speaker_name',
        'text',
        'language_code',
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
     * Parent transcript.
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(ConferenceTranscript::class, 'conference_transcript_id');
    }

    /**
     * Alias for transcript relation.
     */
    public function conferenceTranscript(): BelongsTo
    {
        return $this->transcript();
    }

    /**
     * Formatted timestamp of speech start (e.g. "14:01:15").
     */
    public function getFormattedTimestampAttribute(): ?string
    {
        return $this->start_time ? $this->start_time->format('H:i:s') : null;
    }

    /**
     * Utterance duration in seconds.
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->start_time && $this->end_time) {
            return abs($this->end_time->diffInSeconds($this->start_time));
        }

        return null;
    }
}
