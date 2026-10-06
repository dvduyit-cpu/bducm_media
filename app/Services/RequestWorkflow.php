<?php

namespace App\Services;

use App\Enums\RequestStatus as S;
use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\RequestUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    public function actions(MediaRequest $item, User $user): array
    {
        $actions = [];
        if ($item->canEdit($user)) {
            $actions['submitted'] = 'Gửi Văn phòng BGĐ';
        }
        if ($user->canReview()) {
            $actions += match ($item->status) {
                S::Submitted => ['approved' => 'Duyệt sự kiện & chọn phương án', 'needs_info' => 'Yêu cầu bổ sung'],
                S::Approved => ['in_progress' => 'Bắt đầu thực hiện', 'completed' => 'Đóng sự kiện'],
                S::InProgress => ['completed' => 'Đóng sự kiện'],
                S::OnHold => ['submitted' => 'Tiếp tục kiểm tra'], default => [],
            };
            if (! in_array($item->status, [S::Completed, S::Cancelled])) {
                $actions['cancelled'] = 'Hủy sự kiện';
            }
        } elseif ($item->status === S::Approved && $item->canCollaborate($user)) {
            $actions['in_progress'] = 'Bắt đầu thực hiện';
        }

        return $actions;
    }

    public function transition(MediaRequest $item, User $user, array $data): void
    {
        DB::transaction(function () use ($item, $user, $data) {
            $item = MediaRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $actions = $this->actions($item, $user);
            $target = $data['target'];
            abort_unless(isset($actions[$target]), 403, 'Bạn không có quyền thực hiện thao tác này.');
            $data = Validator::make($data, ['note' => [Rule::requiredIf(in_array($target, ['needs_info', 'cancelled', 'completed'])), 'nullable', 'string', 'max:5000']])->validate() + $data;
            $before = $item->status->value;
            if ($target === 'approved') {
                $this->setCoordination($item, $data);
                $item->approved_by = $user->id;
                $item->approved_at = now();
            }
            if ($target === 'submitted') {
                $item->submitted_at ??= now();
            }
            if ($target === 'completed') {
                $item->completed_at = now();
                $item->progress = 100;
            }
            $item->status = S::from($target);
            $item->save();
            $note = $data['note'] ?? '';
            if ($target === 'approved') {
                $note .= ' | '.$item->modeLabel().' | Hỗ trợ: '.($item->supportDepartments()->pluck('name')->implode(', ') ?: 'Không');
            }
            $item->histories()->create(['user_id' => $user->id, 'action' => $actions[$target], 'from_status' => $before, 'to_status' => $target, 'note' => $note]);
            $this->notifyParticipants($item, $user, $actions[$target]);
        });
    }

    public function coordinate(MediaRequest $item, User $user, array $data): void
    {
        abort_unless($user->canReview(), 403);
        DB::transaction(function () use ($item, $user, $data) {
            $item = MediaRequest::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($item->status, [S::Approved, S::InProgress]), 422, 'Chỉ thay đổi phối hợp với sự kiện đã duyệt và chưa đóng.');
            $validated = Validator::make($data, ['note' => 'required|string|max:5000'])->validate();
            $this->setCoordination($item, $data);
            $item->save();
            $item->histories()->create(['user_id' => $user->id, 'action' => 'Cập nhật phương án phối hợp', 'note' => $validated['note'].' | '.$item->modeLabel().' | Hỗ trợ: '.($item->supportDepartments()->pluck('name')->implode(', ') ?: 'Không')]);
            $this->notifyParticipants($item, $user, 'Phương án phối hợp đã cập nhật');
        });
    }

    private function setCoordination(MediaRequest $item, array $data): void
    {
        $data = Validator::make($data, ['coordination_mode' => 'required|in:autonomous,support',
            'support_department_ids' => 'nullable|array|max:50', 'support_department_ids.*' => ['required', 'integer', 'distinct', Rule::exists('departments', 'id'), Rule::notIn([$item->department_id])],
            'support_notes' => 'nullable|string|max:5000', 'due_at' => 'nullable|date'])->validate();
        $ids = $data['support_department_ids'] ?? [];
        if ($data['coordination_mode'] === 'support' && ! $ids) {
            throw ValidationException::withMessages(['support_department_ids' => 'Chọn ít nhất một phòng ban hỗ trợ khác đơn vị chủ trì.']);
        }
        if (! empty($data['due_at']) && Carbon::parse($data['due_at'])->lt($item->event_at)) {
            throw ValidationException::withMessages(['due_at' => 'Hạn thực hiện không được trước thời gian bắt đầu sự kiện.']);
        }
        $item->coordination_mode = $data['coordination_mode'];
        $item->support_notes = $data['support_notes'] ?? null;
        $item->due_at = $data['due_at'] ?? $item->event_ends_at ?? $item->event_at;
        $item->supportDepartments()->sync($data['coordination_mode'] === 'support' ? $ids : []);
        $item->unsetRelation('supportDepartments');
    }

    public function notifyParticipants(MediaRequest $item, User $actor, string $message): void
    {
        $units = [$item->department_id, ...$item->supportDepartments()->pluck('departments.id')->all()];
        $recipients = User::where('active', true)->where('id', '!=', $actor->id)->where(function ($q) use ($item, $units) {
            $q->whereIn('role', ['admin', 'office'])->orWhere('reviewer_access', true)->orWhereIn('department_id', $units)->orWhereIn('id', array_filter([$item->assignee_id, $item->media_assignee_id]));
        })->get()->filter(fn (User $recipient) => $item->canNotify($recipient));
        foreach ($recipients as $recipient) {
            $recipient->notify(new RequestUpdated($item, $message));
        }
    }
}
