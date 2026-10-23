<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AdminPermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'permissions', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Role 'admin' is the Super Admin: sees everything and manages the sub-admins. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSubAdmin(): bool
    {
        return $this->role === 'sub_admin';
    }

    /** Anyone who may sign in to the admin console (and is not switched off). */
    public function isAdminStaff(): bool
    {
        return in_array($this->role, ['admin', 'sub_admin'], true) && $this->is_active !== false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isSubAdmin() && in_array($permission, $this->permissions ?? [], true);
    }

    public function canAccessRoute(string $routeName): bool
    {
        $needed = AdminPermissions::forRoute($routeName);

        return match (true) {
            ! $this->isAdminStaff() => false,
            $needed === null => true,
            $needed === 'super' => $this->isSuperAdmin(),
            default => $this->hasPermission($needed),
        };
    }
}
