<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'regional_warehouse_id',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function regionalWarehouse(): BelongsTo
    {
        return $this->belongsTo(RegionalWarehouse::class, 'regional_warehouse_id');
    }

    /**
     * Verifica si el usuario posee uno o varios roles específicos.
     *
     * @param UserRole|string|array<UserRole|string> $roles
     */
    public function hasRole(UserRole|string|array $roles): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $currentRoleValue = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        if ($currentRoleValue === UserRole::DIRECTOR->value) {
            return true; // El Director General posee jerarquía omni-acceso
        }

        $rolesList = is_array($roles) ? $roles : [$roles];

        foreach ($rolesList as $role) {
            $roleValue = $role instanceof UserRole ? $role->value : (string) $role;
            if ($currentRoleValue === $roleValue) {
                return true;
            }
        }

        return false;
    }

    public function isDirector(): bool
    {
        return $this->role === UserRole::DIRECTOR;
    }

    public function isAdmin(): bool
    {
        return $this->isDirector();
    }

    public function isAduana(): bool
    {
        return $this->role === UserRole::ADUANA;
    }

    public function isPvpTecnico(): bool
    {
        return $this->role === UserRole::PVP_TECNICO;
    }

    public function isDespachoPuerto(): bool
    {
        return $this->role === UserRole::DESPACHO_PUERTO;
    }

    public function isLogisticaChofer(): bool
    {
        return $this->role === UserRole::LOGISTICA_CHOFER;
    }

    public function isCajeroRegional(): bool
    {
        return $this->role === UserRole::CAJERO_REGIONAL;
    }

    public function isAuditor(): bool
    {
        return $this->role === UserRole::AUDITOR;
    }

    /**
     * Valida si el usuario tiene permiso para operar sobre un almacén regional determinado.
     */
    public function canAccessWarehouse(?string $warehouseId): bool
    {
        if ($this->isDirector() || $this->isAuditor() || $this->isDespachoPuerto()) {
            return true;
        }

        if (empty($warehouseId) || empty($this->regional_warehouse_id)) {
            return false;
        }

        return (string) $this->regional_warehouse_id === (string) $warehouseId;
    }
}
