<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessRole extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'base_role', 'permissions'];

    protected function casts(): array
    {
        return ['base_role' => Role::class, 'permissions' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->code === 'admin';
    }

    public function assignablePermissions(): array
    {
        return array_values(array_filter($this->permissions ?? [], fn ($permission) => $permission !== 'approvals' || $this->base_role === Role::Office || $this->isAdmin()));
    }
}
