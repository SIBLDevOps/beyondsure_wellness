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

#[Fillable(['name', 'email', 'password', 'role', 'permissions', 'is_active', 'region_ids'])]
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
            'region_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Which regions this person may see: null means all of them (Super Admin, or a sub-admin with no
     * restriction), otherwise the list of region ids they were limited to.
     *
     * @return list<int>|null
     */
    public function regionScope(): ?array
    {
        if (! $this->isSubAdmin() || empty($this->region_ids)) {
            return null;
        }

        return array_map('intval', $this->region_ids);
    }

    public function isRegionRestricted(): bool
    {
        return $this->regionScope() !== null;
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

        if (! $this->isSubAdmin()) {
            return false;
        }

        $granted = $this->permissions ?? [];

        // Being allowed to edit the catalog naturally includes viewing it.
        if ($permission === 'catalog' && in_array('catalog.manage', $granted, true)) {
            return true;
        }

        return in_array($permission, $granted, true);
    }

    /** True for anyone who may see profit, cost and money-flow figures (only the Super Admin by default). */
    public function canSeeFinance(): bool
    {
        return $this->hasPermission('finance');
    }

    public function canAccessRoute(string $routeName): bool
    {
        if (! $this->isAdminStaff()) {
            return false;
        }

        foreach (AdminPermissions::requirementsFor($routeName) as $needed) {
            $allowed = $needed === 'super' ? $this->isSuperAdmin() : $this->hasPermission($needed);

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }
}
