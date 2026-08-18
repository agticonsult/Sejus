<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RedeApoio extends Model
{
    use HasFactory;

    protected $table = 'rede_apoio';

    protected $fillable = [
        'nome',
        'tipo',
        'municipio_id',
        'endereco',
        'telefone',
        'email',
        'horario_funcionamento',
        'servicos_oferecidos',
        'latitude',
        'longitude',
        'ativo',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'servicos_oferecidos' => 'array',
        'ativo' => 'boolean',
    ];

    /**
     * Associated municipality in ES.
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioEs::class, 'municipio_id');
    }

    /**
     * Scope for active support facilities.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope by facility type.
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope by municipality.
     */
    public function scopePorMunicipio(Builder $query, int $municipioId): Builder
    {
        return $query->where('municipio_id', $municipioId);
    }

    /**
     * Scope CRAS.
     */
    public function scopeCras(Builder $query): Builder
    {
        return $query->where('tipo', 'CRAS');
    }

    /**
     * Scope CREAS.
     */
    public function scopeCreas(Builder $query): Builder
    {
        return $query->where('tipo', 'CREAS');
    }

    /**
     * Scope SINE.
     */
    public function scopeSine(Builder $query): Builder
    {
        return $query->where('tipo', 'SINE');
    }

    /**
     * Scope CAPS.
     */
    public function scopeCaps(Builder $query): Builder
    {
        return $query->where('tipo', 'CAPS');
    }
}
