<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class VagaEmprego extends Model
{
    use HasFactory;

    protected $table = 'vagas_emprego';

    protected $fillable = [
        'empresa',
        'titulo',
        'descricao',
        'categoria',
        'municipio_id',
        'salario',
        'regime_contratacao',
        'afirmativa_egresso',
        'empresa_amiga_reintegracao',
        'escolaridade_minima',
        'vagas_totais',
        'vagas_preenchidas',
        'status',
        'beneficios',
    ];

    protected $casts = [
        'salario' => 'decimal:2',
        'afirmativa_egresso' => 'boolean',
        'empresa_amiga_reintegracao' => 'boolean',
        'vagas_totais' => 'integer',
        'vagas_preenchidas' => 'integer',
        'beneficios' => 'array',
    ];

    /**
     * Municipality of the job opening.
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioEs::class, 'municipio_id');
    }

    /**
     * Scope for open vacancies.
     */
    public function scopeAbertas(Builder $query): Builder
    {
        return $query->where('status', 'aberta');
    }

    /**
     * Scope for affirmative action vacancies.
     */
    public function scopeAfirmativas(Builder $query): Builder
    {
        return $query->where('afirmativa_egresso', true);
    }

    /**
     * Scope by municipality.
     */
    public function scopePorMunicipio(Builder $query, int $municipioId): Builder
    {
        return $query->where('municipio_id', $municipioId);
    }

    /**
     * Scope by category.
     */
    public function scopePorCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }
}
