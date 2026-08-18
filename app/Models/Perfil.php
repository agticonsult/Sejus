<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Perfil extends Model
{
    use HasFactory;

    protected $table = 'perfis';

    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'permissoes',
        'ativo',
    ];

    protected $casts = [
        'permissoes' => 'array',
        'ativo' => 'boolean',
    ];

    /**
     * Relationship with users.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'perfil_id');
    }

    /**
     * Scope for gestores.
     */
    public function scopeGestores(Builder $query): Builder
    {
        return $query->where('slug', 'gestor');
    }

    /**
     * Scope for tecnicos.
     */
    public function scopeTecnicos(Builder $query): Builder
    {
        return $query->where('slug', 'tecnico');
    }

    /**
     * Scope for egressos.
     */
    public function scopeEgressos(Builder $query): Builder
    {
        return $query->where('slug', 'egresso');
    }

    /**
     * Scope for ativos.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
