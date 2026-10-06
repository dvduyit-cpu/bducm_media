<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'active', 'theme_color', 'board_preferences', 'font_family', 'font_size', 'registration_pending', 'loading_style', 'permissions', 'view_all_units', 'archived_at', 'archived_email', 'access_role_id', 'reviewer_access'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $attributes = ['active' => true, 'role' => 'staff', 'permissions' => '[]', 'view_all_units' => false, 'reviewer_access' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->access_role_id === null) {
                $user->access_role_id = AccessRole::where('code', $user->role->value)->value('id');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'board_preferences' => 'array',
            'font_size' => 'integer',
            'registration_pending' => 'boolean',
            'permissions' => 'array',
            'view_all_units' => 'boolean',
            'reviewer_access' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public static function fontOptions(): array
    {
        return [
            'system' => ['label' => 'Mặc định hệ thống', 'css' => 'Inter, "Segoe UI", Arial, sans-serif'],
            'segoe' => ['label' => 'Segoe UI', 'css' => '"Segoe UI", Arial, sans-serif'],
            'arial' => ['label' => 'Arial', 'css' => 'Arial, Helvetica, sans-serif'],
            'tahoma' => ['label' => 'Tahoma', 'css' => 'Tahoma, Arial, sans-serif'],
            'verdana' => ['label' => 'Verdana', 'css' => 'Verdana, Arial, sans-serif'],
            'georgia' => ['label' => 'Georgia', 'css' => 'Georgia, "Times New Roman", serif'],
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class);
    }

    public function roleDefinition(): ?AccessRole
    {
        return $this->accessRole ?? AccessRole::where('code', $this->role->value)->first();
    }

    public function roleLabel(): string
    {
        return $this->roleDefinition()?->name ?? $this->role->label();
    }

    public function roleCode(): string
    {
        return $this->roleDefinition()?->code ?? $this->role->value;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public static function permissionOptions(): array
    {
        return ['dashboard' => 'Bảng công việc', 'requests' => 'Sự kiện phòng ban', 'calendar' => 'Lịch sự kiện', 'approvals' => 'Duyệt & điều phối (VP BGĐ)', 'reports' => 'Báo cáo', 'library' => 'Kho tài liệu'];
    }

    public function canAccess(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->reviewer_access && in_array($permission, ['requests', 'approvals'], true)) {
            return true;
        }
        if ($permission === 'approvals' && ! $this->isOffice()) {
            return false;
        }

        $role = $this->roleDefinition();
        if (! $role || ! in_array($permission, $role->assignablePermissions(), true)) {
            return false;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    public function visibleNotifications(): MorphMany
    {
        $canViewEvents = collect(array_keys(self::permissionOptions()))->contains(fn ($permission) => $this->canAccess($permission));

        return $this->notifications()->where(function ($query) use ($canViewEvents) {
            $query->whereNull('data->request_id');
            if ($canViewEvents) {
                $query->orWhereIn('data->request_id', MediaRequest::visibleTo($this)->select('id'));
            }
        });
    }

    public function landingRoute(): string
    {
        foreach (['dashboard' => 'dashboard', 'requests' => 'requests.index', 'calendar' => 'calendar', 'approvals' => 'approvals', 'reports' => 'reports', 'library' => 'library'] as $permission => $route) {
            if ($this->canAccess($permission)) {
                return $route;
            }
        }

        return 'settings';
    }

    public function isOffice(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Office]);
    }

    public function canReview(): bool
    {
        return $this->reviewer_access || ($this->isOffice() && $this->canAccess('approvals'));
    }

    public function isLeadership(): bool
    {
        return $this->isAdmin() || $this->reviewer_access || (in_array($this->role, [Role::Office, Role::Director]) && ($this->view_all_units ?? false));
    }
}
