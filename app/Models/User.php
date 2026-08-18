<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\LgpdSecurityService;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'perfil_id',
        'name',
        'email',
        'password',
        'govbr_id',
        'cpf_encrypted',
        'hash_cpf',
        'telefone_encrypted',
        'foto_url',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'cpf_encrypted',
        'telefone_encrypted',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ativo' => 'boolean',
    ];

    /**
     * User profile relationship (RBAC).
     */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    /**
     * Associated Egresso record if user is an Egresso.
     */
    public function egresso(): HasOne
    {
        return $this->hasOne(Egresso::class, 'user_id');
    }

    /**
     * Prontuarios managed as technician.
     */
    public function prontuariosComoTecnico(): HasMany
    {
        return $this->hasMany(Prontuario::class, 'tecnico_responsavel_id');
    }

    /**
     * Timeline events recorded by this user.
     */
    public function timelineEventos(): HasMany
    {
        return $this->hasMany(ProntuarioTimeline::class, 'responsavel_id');
    }

    /**
     * Audit logs triggered by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(ProntuarioAuditLog::class, 'user_id');
    }

    /**
     * Video rooms hosted as technician.
     */
    public function videoRoomsComoTecnico(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'tecnico_id');
    }

    /**
     * Video call participations.
     */
    public function participacoesVideo(): HasMany
    {
        return $this->hasMany(VideoAttendee::class, 'user_id');
    }

    /**
     * Check if user has Gestor role.
     */
    public function isGestor(): bool
    {
        return $this->perfil?->slug === 'gestor';
    }

    /**
     * Check if user has Tecnico role.
     */
    public function isTecnico(): bool
    {
        return $this->perfil?->slug === 'tecnico';
    }

    /**
     * Check if user has Egresso role.
     */
    public function isEgresso(): bool
    {
        return $this->perfil?->slug === 'egresso';
    }

    /**
     * Check if user has Familiar role.
     */
    public function isFamiliar(): bool
    {
        return $this->perfil?->slug === 'familiar';
    }

    /**
     * Decrypt CPF attribute via LGPD service.
     */
    public function getCpfAttribute(): ?string
    {
        if (empty($this->cpf_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->cpf_encrypted);
    }

    /**
     * Set and encrypt CPF attribute with automatic blind index hashing.
     */
    public function setCpfAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['cpf_encrypted'] = null;
            $this->attributes['hash_cpf'] = null;
            return;
        }

        $service = app(LgpdSecurityService::class);
        $this->attributes['cpf_encrypted'] = $service->encryptField($value);
        $this->attributes['hash_cpf'] = $service->generateBlindIndex($value);
    }

    /**
     * Decrypt Telefone attribute.
     */
    public function getTelefoneAttribute(): ?string
    {
        if (empty($this->telefone_encrypted)) {
            return null;
        }

        return app(LgpdSecurityService::class)->decryptField($this->telefone_encrypted);
    }

    /**
     * Encrypt Telefone attribute.
     */
    public function setTelefoneAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['telefone_encrypted'] = null;
            return;
        }

        $this->attributes['telefone_encrypted'] = app(LgpdSecurityService::class)->encryptField($value);
    }
}
