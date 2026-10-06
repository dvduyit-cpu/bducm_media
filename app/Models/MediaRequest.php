<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MediaRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'event_at' => 'datetime', 'publish_at' => 'datetime',
            'due_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'important' => 'boolean'];
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
            return $query->where('assignee_id', $user->id);
        }

        return $query->where('department_id', $user->department_id)->where(function ($q) use ($user) {
            if ($user->role !== Role::Head) {
                $q->where('status', '!=', 'draft')->orWhere('creator_id', $user->id);
            }
        });
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
        return ! in_array($this->status, [RequestStatus::Completed, RequestStatus::Cancelled, RequestStatus::OnHold, RequestStatus::Draft, RequestStatus::InternalReview]) && $this->due_at->isPast();
    }

    public function canEdit(User $user): bool
    {
        return in_array($this->status, [RequestStatus::Draft, RequestStatus::NeedsInfo]) &&
            ($user->isOffice() || ($user->department_id === $this->department_id && ($user->role === Role::Head || $user->id === $this->creator_id)));
    }
}
