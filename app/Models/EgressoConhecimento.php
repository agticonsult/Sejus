<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EgressoConhecimento extends Model
{
    use HasFactory;

    protected $table = 'egresso_conhecimentos';

    public const NIVEL_BASICO = 'basico';
    public const NIVEL_INTERMEDIARIO = 'intermediario';
    public const NIVEL_AVANCADO = 'avancado';

    public const NIVEIS_VALIDOS = [
        self::NIVEL_BASICO,
        self::NIVEL_INTERMEDIARIO,
        self::NIVEL_AVANCADO,
    ];

    public const NIVEIS_LABELS = [
        self::NIVEL_BASICO => 'Básico',
        self::NIVEL_INTERMEDIARIO => 'Intermediário',
        self::NIVEL_AVANCADO => 'Avançado',
    ];

    protected $fillable = [
        'egresso_id',
        'conhecimento_id',
        'nivel',
        'necessita_treinamento',
        'observacoes',
    ];

    protected $casts = [
        'necessita_treinamento' => 'boolean',
    ];

    /**
     * Egresso associado ao conhecimento.
     */
    public function egresso(): BelongsTo
    {
        return $this->belongsTo(Egresso::class, 'egresso_id');
    }

    /**
     * Conhecimento do catálogo associado.
     */
    public function conhecimento(): BelongsTo
    {
        return $this->belongsTo(Conhecimento::class, 'conhecimento_id');
    }

    /**
     * Normaliza a string de nível para formato canônico do banco.
     */
    public static function normalizarNivel(?string $nivel): string
    {
        $clean = mb_strtolower(trim((string) $nivel));
        return match ($clean) {
            'básico', 'basico' => self::NIVEL_BASICO,
            'intermediário', 'intermediario' => self::NIVEL_INTERMEDIARIO,
            'avançado', 'avancado' => self::NIVEL_AVANCADO,
            default => self::NIVEL_BASICO,
        };
    }

    /**
     * Retorna o rótulo amigável para exibição em UI.
     */
    public function getNivelFormatadoAttribute(): string
    {
        return self::NIVEIS_LABELS[$this->nivel] ?? ucfirst($this->nivel);
    }

    /**
     * Scope para conhecimentos com indicação de treinamento.
     */
    public function scopeNecessitamTreinamento(Builder $query): Builder
    {
        return $query->where('necessita_treinamento', true);
    }

    /**
     * Scope filtrando por nível de proficiência.
     */
    public function scopePorNivel(Builder $query, string $nivel): Builder
    {
        return $query->where('nivel', self::normalizarNivel($nivel));
    }
}
