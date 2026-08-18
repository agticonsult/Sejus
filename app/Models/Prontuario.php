<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Prontuario extends Model
{
    use HasFactory;

    protected $table = 'prontuarios';

    protected $fillable = [
        'numero_prontuario',
        'egresso_id',
        'tecnico_responsavel_id',
        'situacao',
        'resumo_diagnostico',
        'meta_plano_individual',
        'data_abertura',
    ];

    protected $casts = [
        'data_abertura' => 'datetime',
    ];

    /**
     * Egresso titular relationship (1:1).
     */
    public function egresso(): BelongsTo
    {
        return $this->belongsTo(Egresso::class, 'egresso_id');
    }

    /**
     * Social Worker / Reference Technician.
     */
    public function tecnicoResponsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_responsavel_id');
    }

    /**
     * Chronological timeline of interventions.
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(ProntuarioTimeline::class, 'prontuario_id')->orderBy('data_evento', 'desc');
    }

    /**
     * Immutable audit logs attached to this prontuario.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(ProntuarioAuditLog::class, 'prontuario_id')->orderBy('id', 'desc');
    }

    /**
     * Video attendance sessions.
     */
    public function videoRooms(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'prontuario_id');
    }

    /**
     * Scope for active prontuarios.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('situacao', 'ativo');
    }

    /**
     * Scope by responsible technician.
     */
    public function scopePorTecnico(Builder $query, int $tecnicoId): Builder
    {
        return $query->where('tecnico_responsavel_id', $tecnicoId);
    }
}
