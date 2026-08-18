<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MunicipioEs extends Model
{
    use HasFactory;

    protected $table = 'municipios_es';

    protected $fillable = [
        'codigo_ibge',
        'nome',
        'microrregiao',
        'macrorregiao',
        'latitude',
        'longitude',
        'tem_escritorio_fisico',
        'populacao_estimada',
        'total_egressos_atendidos',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'tem_escritorio_fisico' => 'boolean',
        'populacao_estimada' => 'integer',
        'total_egressos_atendidos' => 'integer',
    ];

    /**
     * Relationship with Egressos residing in this municipality.
     */
    public function egressos(): HasMany
    {
        return $this->hasMany(Egresso::class, 'municipio_residencia_id');
    }

    /**
     * Relationship with Job Vacancies in this municipality.
     */
    public function vagas(): HasMany
    {
        return $this->hasMany(VagaEmprego::class, 'municipio_id');
    }

    /**
     * Relationship with Training Courses in this municipality.
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(CursoCapacitacao::class, 'municipio_id');
    }

    /**
     * Relationship with Social Support Network (CRAS, CREAS, SINE, CAPS).
     */
    public function redeApoio(): HasMany
    {
        return $this->hasMany(RedeApoio::class, 'municipio_id');
    }

    /**
     * Relationship with Video Rooms originating from this municipality.
     */
    public function videoRooms(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'municipio_id');
    }

    /**
     * Scope for municipalities with physical social offices.
     */
    public function scopeComEscritorioFisico(Builder $query): Builder
    {
        return $query->where('tem_escritorio_fisico', true);
    }

    /**
     * Scope for remote-only assistance municipalities.
     */
    public function scopeRemotos(Builder $query): Builder
    {
        return $query->where('tem_escritorio_fisico', false);
    }

    /**
     * Scope by microrregiao.
     */
    public function scopePorMicrorregiao(Builder $query, string $microrregiao): Builder
    {
        return $query->where('microrregiao', $microrregiao);
    }

    /**
     * Scope by macrorregiao.
     */
    public function scopePorMacrorregiao(Builder $query, string $macrorregiao): Builder
    {
        return $query->where('macrorregiao', $macrorregiao);
    }
}
