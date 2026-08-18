<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CursoCapacitacao extends Model
{
    use HasFactory;

    protected $table = 'cursos_capacitacao';

    protected $fillable = [
        'instituicao',
        'titulo',
        'descricao',
        'categoria',
        'municipio_id',
        'carga_horaria',
        'modalidade',
        'bolsa_auxilio',
        'vagas_disponiveis',
        'status',
        'link_inscricao',
    ];

    protected $casts = [
        'carga_horaria' => 'integer',
        'bolsa_auxilio' => 'decimal:2',
        'vagas_disponiveis' => 'integer',
    ];

    /**
     * Host municipality (nullable if 100% remote / EAD).
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioEs::class, 'municipio_id');
    }

    /**
     * Scope for open enrollment courses.
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->where('status', 'aberto');
    }

    /**
     * Scope by modality (presencial, ead, hibrido).
     */
    public function scopePorModalidade(Builder $query, string $modalidade): Builder
    {
        return $query->where('modalidade', $modalidade);
    }

    /**
     * Scope by municipality.
     */
    public function scopePorMunicipio(Builder $query, int $municipioId): Builder
    {
        return $query->where('municipio_id', $municipioId);
    }
}
