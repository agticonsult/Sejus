<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProntuarioAuditLog extends Model
{
    use HasFactory;

    protected $table = 'prontuario_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'prontuario_id',
        'user_id',
        'acao',
        'ip_address',
        'user_agent',
        'previous_hash',
        'current_hash',
        'details',
        'timestamp',
    ];

    protected $casts = [
        'details' => 'array',
        'timestamp' => 'datetime',
    ];

    /**
     * Parent Prontuario.
     */
    public function prontuario(): BelongsTo
    {
        return $this->belongsTo(Prontuario::class, 'prontuario_id');
    }

    /**
     * User who executed the audited action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope by action type.
     */
    public function scopePorAcao(Builder $query, string $acao): Builder
    {
        return $query->where('acao', $acao);
    }

    /**
     * Scope by user.
     */
    public function scopePorUsuario(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by prontuario.
     */
    public function scopePorProntuario(Builder $query, int $prontuarioId): Builder
    {
        return $query->where('prontuario_id', $prontuarioId);
    }
}
