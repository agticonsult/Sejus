<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class VideoAttendee extends Model
{
    use HasFactory;

    protected $table = 'video_attendees';

    protected $fillable = [
        'video_room_id',
        'user_id',
        'peer_id',
        'role',
        'joined_at',
        'left_at',
        'duration_seconds',
        'mos_score',
        'packet_loss',
        'jitter',
        'rtt_ms',
        'telemetry_data',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_seconds' => 'integer',
        'mos_score' => 'float',
        'packet_loss' => 'float',
        'jitter' => 'float',
        'rtt_ms' => 'float',
        'telemetry_data' => 'array',
    ];

    /**
     * Parent Video Room.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(VideoRoom::class, 'video_room_id');
    }

    /**
     * Authenticated user participant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for technician attendees.
     */
    public function scopeTecnicos(Builder $query): Builder
    {
        return $query->where('role', 'tecnico');
    }

    /**
     * Scope for egresso attendees.
     */
    public function scopeEgressos(Builder $query): Builder
    {
        return $query->where('role', 'egresso');
    }

    /**
     * Scope for high quality connections (MOS >= 4.0).
     */
    public function scopeQualidadeAlta(Builder $query): Builder
    {
        return $query->where('mos_score', '>=', 4.0);
    }
}
