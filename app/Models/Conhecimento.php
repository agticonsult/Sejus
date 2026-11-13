<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Conhecimento extends Model
{
    use HasFactory;

    protected $table = 'conhecimentos';

    protected $fillable = [
        'nome',
        'descricao',
        'categoria',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Egressos que possuem este conhecimento em sua ficha.
     */
    public function egressos(): BelongsToMany
    {
        return $this->belongsToMany(Egresso::class, 'egresso_conhecimentos')
            ->withPivot(['id', 'nivel', 'necessita_treinamento', 'observacoes'])
            ->withTimestamps();
    }

    /**
     * Registros individuais na tabela pivot egresso_conhecimentos.
     */
    public function egressoConhecimentos(): HasMany
    {
        return $this->hasMany(EgressoConhecimento::class, 'conhecimento_id');
    }

    /**
     * Vagas de emprego que exigem ou listam este conhecimento.
     */
    public function vagas(): BelongsToMany
    {
        return $this->belongsToMany(VagaEmprego::class, 'vaga_conhecimento', 'conhecimento_id', 'vaga_id')
            ->withTimestamps();
    }

    /**
     * Scope para conhecimentos ativos no catálogo.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope por categoria profissional.
     */
    public function scopePorCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }
}
