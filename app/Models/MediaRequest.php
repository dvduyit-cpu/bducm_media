<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MediaRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'event_at' => 'datetime', 'publish_at' => 'datetime',
            'due_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'important' => 'boolean',
            'event_ends_at' => 'datetime', 'published_at' => 'datetime', 'approved_at' => 'datetime', 'content_tags' => 'array', 'needs_onsite' => 'boolean', 'provides_materials' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function contact()
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function histories()
    {
        return $this->hasMany(RequestHistory::class)->latest('id');
    }

    public function latestHistory(): HasOne
    {
        return $this->hasOne(RequestHistory::class)->latestOfMany();
    }

    public function mediaAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'media_assignee_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(RequestLink::class);
    }

    public function isAssignedTo(User $user): bool
    {
        return $user->role === Role::Media && in_array($user->id, [$this->assignee_id, $this->media_assignee_id], true);
    }

    public function canSubmitProduct(User $user): bool
    {
        return $this->canCollaborate($user) && in_array($this->status, [RequestStatus::Approved, RequestStatus::InProgress]);
    }

    public function supportDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_media_request')->withTimestamps();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canCollaborate(User $user): bool
    {
        if ($user->isOffice() || $user->canReview()) {
            return true;
        }
        if ($user->role === Role::Director) {
            return false;
        }

        return $this->isAssignedTo($user) || ($user->department_id !== null && ($this->department_id === $user->department_id || $this->supportDepartments->contains('id', $user->department_id)));
    }

    public function modeLabel(): string
    {
        return match ($this->coordination_mode) {
            'autonomous' => 'Tự chủ', 'support' => 'Có phòng ban hỗ trợ', default => 'Chờ VP BGĐ quyết định',
        };
    }

    public static function currentStatuses(): array
    {
        return [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::NeedsInfo, RequestStatus::Approved, RequestStatus::InProgress, RequestStatus::Completed, RequestStatus::Cancelled];
    }

    public static function boardColumns(): array
    {
        return ['draft' => ['title' => 'Bản nháp', 'color' => '#64748b'], 'submitted' => ['title' => 'Chờ VP duyệt', 'color' => '#e89b12'],
            'approved' => ['title' => 'Đã duyệt', 'color' => '#2563eb'], 'in_progress' => ['title' => 'Đang thực hiện', 'color' => '#7c3aed'], 'completed' => ['title' => 'Đã đóng', 'color' => '#15803d']];
    }

    public function approvalRole(): string
    {
        return $this->approval_route === 'auto' ? ($this->important ? 'director' : 'office') : $this->approval_route;
    }

    public static function activityTypes(): array
    {
        return ['Học thuật/Dự giờ', 'Hoạt động ngoại khóa', 'Công tác đoàn thể/Nhân sự', 'Cải cách/Tập huấn', 'Đón tiếp khách', 'Khác'];
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isLeadership()) {
            return $query;
        }
        if ($user->role === Role::Media) {
            return $query->where(fn (Builder $q) => $q->where('assignee_id', $user->id)->orWhere('media_assignee_id', $user->id)->when($user->department_id, fn ($q) => $q->orWhere('department_id', $user->department_id)->orWhereHas('supportDepartments', fn ($q) => $q->where('departments.id', $user->department_id))))->where(fn ($query) => $query->where('status', '!=', 'draft')->orWhere('creator_id', $user->id));
        }

        return $query->where(fn ($q) => $q->where('department_id', $user->department_id)->orWhereHas('supportDepartments', fn ($q) => $q->where('departments.id', $user->department_id)))->where(function ($q) use ($user) {
            if ($user->role !== Role::Head) {
                $q->where('status', '!=', 'draft')->orWhere('creator_id', $user->id);
            }
        });
    }

    public function canNotify(User $user): bool
    {
        return collect(array_keys(User::permissionOptions()))->contains(fn ($permission) => $user->canAccess($permission))
            && self::visibleTo($user)->whereKey($this->id)->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled', 'on_hold', 'draft', 'internal_review']);
    }

    public function getCodeAttribute(): string
    {
        return 'TT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getOverdueAttribute(): bool
    {
        return ! in_array($this->status, [RequestStatus::Completed, RequestStatus::Cancelled, RequestStatus::OnHold, RequestStatus::Draft, RequestStatus::InternalReview]) && ($this->due_at?->isPast() ?? false);
    }

    public function canEdit(User $user): bool
    {
        return ($user->canReview() || in_array($user->role, [Role::Admin, Role::Office, Role::Head, Role::Staff])) && in_array($this->status, [RequestStatus::Draft, RequestStatus::NeedsInfo]) &&
            ($user->canReview() || $user->isOffice() || ($user->department_id === $this->department_id && ($user->role === Role::Head || $user->id === $this->creator_id)));
    }
}
