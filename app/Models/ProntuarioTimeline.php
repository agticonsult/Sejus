<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProntuarioTimeline extends Model
{
    use HasFactory;

    protected $table = 'prontuario_timeline';

    protected $fillable = [
        'prontuario_id',
        'tipo_evento',
        'titulo',
        'descricao',
        'metadata',
        'responsavel_id',
        'data_evento',
    ];

    protected $casts = [
        'metadata' => 'array',
        'data_evento' => 'datetime',
    ];

    /**
     * Parent Prontuario.
     */
    public function prontuario(): BelongsTo
    {
        return $this->belongsTo(Prontuario::class, 'prontuario_id');
    }

    /**
     * Technician / User responsible for event.
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Scope by event type.
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo_evento', $tipo);
    }

    /**
     * Scope for recent events.
     */
    public function scopeRecentes(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('data_evento', 'desc')->limit($limit);
    }
}
