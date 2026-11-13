<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConferenceTranscript extends Model
{
    use HasFactory;

    protected $table = 'conference_transcripts';

    protected $fillable = [
        'conference_record_id',
        'transcript_name',
        'state',
        'docs_document_id',
        'export_uri',
        'full_text',
        'content_text',
        'entries_count',
        'raw_entries',
        'start_time',
        'end_time',
        'raw_metadata',
        'raw_data',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'entries_count' => 'integer',
        'raw_entries' => 'array',
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
     * Granular sentence entries for this transcript.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ConferenceTranscriptEntry::class, 'conference_transcript_id');
    }

    /**
     * Google Docs document link accessor.
     */
    public function getDocsLinkAttribute(): ?string
    {
        if (!empty($this->export_uri)) {
            return $this->export_uri;
        }

        if (!empty($this->docs_document_id)) {
            return "https://docs.google.com/document/d/{$this->docs_document_id}/view";
        }

        return null;
    }

    /**
     * Accessor for full_text with content_text fallback.
     */
    public function getFullTextAttribute(): ?string
    {
        return $this->attributes['full_text'] ?? $this->attributes['content_text'] ?? null;
    }

    /**
     * Accessor for content_text with full_text fallback.
     */
    public function getContentTextAttribute(): ?string
    {
        return $this->attributes['content_text'] ?? $this->attributes['full_text'] ?? null;
    }

    /**
     * Check if transcript generation is complete.
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
     * Compile chronological full text from granular entries.
     *
     * @param bool $save Whether to persist the compiled text to full_text and content_text
     * @return string
     */
    public function compileFullText(bool $save = false): string
    {
        $lines = [];
        $entries = $this->entries()->orderBy('start_time')->get();

        foreach ($entries as $entry) {
            $speaker = $entry->speaker_name ?? $entry->participant_name ?? 'Participante';
            $timeStr = $entry->start_time ? $entry->start_time->format('H:i:s') : '';
            if ($timeStr !== '') {
                $lines[] = "[{$timeStr}] {$speaker}: {$entry->text}";
            } else {
                $lines[] = "{$speaker}: {$entry->text}";
            }
        }

        $compiled = implode("\n", $lines);

        if ($save) {
            $this->full_text = $compiled;
            $this->content_text = $compiled;
            $this->entries_count = $entries->count();
            $this->save();
        }

        return $compiled;
    }

    /**
     * Scope for completed transcripts.
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
