<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class MeetingRoom extends Model
{
    use HasFactory;

    protected $table = 'meeting_rooms';

    protected $fillable = [
        'space_name',
        'meeting_uri',
        'meeting_code',
        'access_type',
        'entry_point_access',
        'moderation',
        'chat_restriction',
        'present_restriction',
        'default_join_as_viewer_type',
        'status',
        'raw_config',
        'created_by',
        'creator_id',
        'prontuario_id',
    ];

    protected $casts = [
        'raw_config' => 'array',
    ];

    /**
     * User / technician who created the meeting room.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for creator relation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->creator();
    }

    /**
     * Associated Prontuario (social assistance record) if applicable.
     */
    public function prontuario(): BelongsTo
    {
        return $this->belongsTo(Prontuario::class, 'prontuario_id');
    }

    /**
     * Conference records linked to this meeting room.
     */
    public function conferenceRecords(): HasMany
    {
        return $this->hasMany(ConferenceRecord::class, 'meeting_room_id');
    }

    /**
     * Most recent conference record for this room.
     */
    public function latestConferenceRecord(): HasOne
    {
        return $this->hasOne(ConferenceRecord::class, 'meeting_room_id')->latestOfMany('start_time');
    }

    /**
     * Scope for active meeting rooms.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope alias (PT-BR) for active meeting rooms.
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $this->scopeActive($query);
    }

    /**
     * Scope for ended meeting rooms.
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', 'ended');
    }

    /**
     * Scope alias (PT-BR) for ended meeting rooms.
     */
    public function scopeEncerradas(Builder $query): Builder
    {
        return $this->scopeEnded($query);
    }

    /**
     * Scope by Google Meet Space Name (e.g. 'spaces/xyz123').
     */
    public function scopeBySpaceName(Builder $query, string $spaceName): Builder
    {
        return $query->where('space_name', $spaceName);
    }

    /**
     * Scope by user-facing meeting code (e.g. 'abc-defg-hij').
     */
    public function scopeByMeetingCode(Builder $query, string $code): Builder
    {
        return $query->where('meeting_code', $code);
    }

    /**
     * Scope by creator technician ID.
     */
    public function scopeByCreator(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope by associated prontuario ID.
     */
    public function scopeByProntuario(Builder $query, int $prontuarioId): Builder
    {
        return $query->where('prontuario_id', $prontuarioId);
    }

    /**
     * Scope for rooms with open access policy.
     */
    public function scopeOpenAccess(Builder $query): Builder
    {
        return $query->where('access_type', 'OPEN');
    }

    /**
     * Check if meeting room enforces strict R1 host restrictions.
     */
    public function isRestricted(): bool
    {
        return $this->moderation === 'ON'
            && $this->chat_restriction === 'HOSTS_ONLY'
            && $this->present_restriction === 'HOSTS_ONLY';
    }

    /**
     * Check if viewer mode is active for non-hosts.
     */
    public function isViewerMode(): bool
    {
        return $this->default_join_as_viewer_type === 'ON';
    }

    /**
     * Check if meeting room has open access policy.
     */
    public function isOpenAccess(): bool
    {
        return $this->access_type === 'OPEN';
    }

    /**
     * Check if room status is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Accessor for full join URL.
     */
    public function getJoinUrlAttribute(): string
    {
        return $this->meeting_uri;
    }

    /**
     * Accessor for viewer_mode boolean.
     */
    public function getViewerModeAttribute(): bool
    {
        return $this->isViewerMode();
    }

    /**
     * Accessor for restrictions_configured array.
     */
    public function getRestrictionsConfiguredAttribute(): ?array
    {
        return $this->raw_config;
    }

    /**
     * Mutator for restrictions_configured.
     */
    public function setRestrictionsConfiguredAttribute($value): void
    {
        $this->attributes['raw_config'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Accessor for creator_id alias.
     */
    public function getCreatorIdAttribute(): ?int
    {
        return $this->created_by ? (int) $this->created_by : null;
    }

    /**
     * Mutator for creator_id alias.
     */
    public function setCreatorIdAttribute($value): void
    {
        $this->attributes['created_by'] = $value;
    }
}
