<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class VideoRoom extends Model
{
    use HasFactory;

    protected $table = 'video_rooms';

    protected $fillable = [
        'room_code',
        'prontuario_id',
        'tecnico_id',
        'egresso_id',
        'municipio_id',
        'status',
        'prioridade',
        'motivo_atendimento',
        'scheduled_at',
        'started_at',
        'ended_at',
        'token_sala',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Parent Prontuario.
     */
    public function prontuario(): BelongsTo
    {
        return $this->belongsTo(Prontuario::class, 'prontuario_id');
    }

    /**
     * Host technician.
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /**
     * Participant Egresso.
     */
    public function egresso(): BelongsTo
    {
        return $this->belongsTo(Egresso::class, 'egresso_id');
    }

    /**
     * Origin municipality.
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioEs::class, 'municipio_id');
    }

    /**
     * Participants and telemetry.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(VideoAttendee::class, 'video_room_id');
    }

    /**
     * Scope for waiting rooms.
     */
    public function scopeAguardando(Builder $query): Builder
    {
        return $query->where('status', 'aguardando');
    }

    /**
     * Scope for active in-progress rooms.
     */
    public function scopeEmAndamento(Builder $query): Builder
    {
        return $query->where('status', 'em_andamento');
    }

    /**
     * Scope for finished rooms.
     */
    public function scopeEncerradas(Builder $query): Builder
    {
        return $query->where('status', 'encerrada');
    }

    /**
     * Scope by priority.
     */
    public function scopePorPrioridade(Builder $query, string $prioridade): Builder
    {
        return $query->where('prioridade', $prioridade);
    }
}
