<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Services\LgpdSecurityService;

class Egresso extends Model
{
    use HasFactory;

    protected $table = 'egressos';

    protected $fillable = [
        'user_id',
        'nome_completo',
        'nome_social',
        'data_nascimento',
        'cpf',
        'cpf_encrypted',
        'hash_cpf',
        'rg',
        'rg_encrypted',
        'filiacao_mae',
        'filiacao_mae_encrypted',
        'municipio_residencia_id',
        'endereco',
        'endereco_encrypted',
        'telefone',
        'telefone_encrypted',
        'escolaridade',
        'status_penal',
        'unidade_prisional_origem',
        'numero_processo_execucao',
        'vulnerabilidades',
        'consentimento_geolocalizacao',
        'consentimento_compartilhamento',
        'termo_aceito_em',
    ];

    protected $hidden = [
        'cpf_encrypted',
        'rg_encrypted',
        'filiacao_mae_encrypted',
        'endereco_encrypted',
        'telefone_encrypted',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'vulnerabilidades' => 'array',
        'consentimento_geolocalizacao' => 'boolean',
        'consentimento_compartilhamento' => 'boolean',
        'termo_aceito_em' => 'datetime',
    ];

    /**
     * User account relationship (optional 1:1).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Municipality of residence.
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioEs::class, 'municipio_residencia_id');
    }

    /**
     * Electronic Prontuario record (1:1 strict).
     */
    public function prontuario(): HasOne
    {
        return $this->hasOne(Prontuario::class, 'egresso_id');
    }

    /**
     * Associated video attendance rooms.
     */
    public function videoRooms(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'egresso_id');
    }

    /**
     * Scope by municipality.
     */
    public function scopePorMunicipio(Builder $query, int $municipioId): Builder
    {
        return $query->where('municipio_residencia_id', $municipioId);
    }

    /**
     * Scope by penal status.
     */
    public function scopePorStatusPenal(Builder $query, string $status): Builder
    {
        return $query->where('status_penal', $status);
    }

    /**
     * Scope with data sharing consent.
     */
    public function scopeComConsentimento(Builder $query): Builder
    {
        return $query->where('consentimento_compartilhamento', true);
    }

    /**
     * Get decrypted CPF.
     */
    public function getCpfAttribute(): ?string
    {
        if (empty($this->cpf_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->cpf_encrypted);
    }

    /**
     * Set and encrypt CPF with blind index.
     */
    public function setCpfAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['cpf_encrypted'] = '';
            $this->attributes['hash_cpf'] = '';
            return;
        }

        $service = app(LgpdSecurityService::class);
        $this->attributes['cpf_encrypted'] = $service->encryptField($value);
        $this->attributes['hash_cpf'] = $service->generateBlindIndex($value);
    }

    /**
     * Get decrypted RG.
     */
    public function getRgAttribute(): ?string
    {
        if (empty($this->rg_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->rg_encrypted);
    }

    /**
     * Set encrypted RG.
     */
    public function setRgAttribute(?string $value): void
    {
        $this->attributes['rg_encrypted'] = empty($value) ? null : app(LgpdSecurityService::class)->encryptField($value);
    }

    /**
     * Get decrypted Mother's name.
     */
    public function getFiliacaoMaeAttribute(): ?string
    {
        if (empty($this->filiacao_mae_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->filiacao_mae_encrypted);
    }

    /**
     * Set encrypted Mother's name.
     */
    public function setFiliacaoMaeAttribute(?string $value): void
    {
        $this->attributes['filiacao_mae_encrypted'] = empty($value) ? null : app(LgpdSecurityService::class)->encryptField($value);
    }

    /**
     * Get decrypted Address.
     */
    public function getEnderecoAttribute(): ?string
    {
        if (empty($this->endereco_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->endereco_encrypted);
    }

    /**
     * Set encrypted Address.
     */
    public function setEnderecoAttribute(?string $value): void
    {
        $this->attributes['endereco_encrypted'] = empty($value) ? null : app(LgpdSecurityService::class)->encryptField($value);
    }

    /**
     * Get decrypted Telefone.
     */
    public function getTelefoneAttribute(): ?string
    {
        if (empty($this->telefone_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->telefone_encrypted);
    }

    /**
     * Set encrypted Telefone.
     */
    public function setTelefoneAttribute(?string $value): void
    {
        $this->attributes['telefone_encrypted'] = empty($value) ? null : app(LgpdSecurityService::class)->encryptField($value);
    }

    /**
     * Formatted SEJUS registration code.
     */
    public function getRegistroSejusAttribute(): string
    {
        return 'ES-2026-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
