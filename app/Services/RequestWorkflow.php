<?php

namespace App\Services;

use App\Enums\RequestStatus as S;
use App\Enums\Role;
use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\RequestUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    public function actions(MediaRequest $item, User $user): array
    {
        $actions = [];
        $sameUnit = $user->department_id === $item->department_id;
        if ($sameUnit && ($user->role === Role::Head || $user->id === $item->creator_id)) {
            if ($item->status === S::Draft) {
                $actions[$user->role === Role::Head ? 'submitted' : 'internal_review'] = $user->role === Role::Head ? 'Duyệt nội bộ và gửi VP BGĐ' : 'Trình trưởng đơn vị';
            }
            if ($item->status === S::NeedsInfo) {
                $actions['submitted'] = 'Gửi thông tin bổ sung';
            }
        }
        if ($sameUnit && $user->role === Role::Head && $item->status === S::InternalReview) {
            $actions += ['submitted' => 'Duyệt và gửi VP BGĐ', 'draft' => 'Trả lại bản nháp'];
        }
        if ($user->isOffice()) {
            $actions += match ($item->status) {
                S::Draft => ['submitted' => 'Gửi yêu cầu đến VP BGĐ'],
                S::InternalReview => ['submitted' => 'Tiếp nhận thay đơn vị'],
                S::Submitted => ['received' => 'Tiếp nhận yêu cầu'],
                S::Received => ['needs_info' => 'Yêu cầu bổ sung', 'ready' => 'Xác nhận đủ thông tin'],
                S::Ready => ['awaiting_assignment' => 'Đưa vào kế hoạch'],
                S::AwaitingAssignment => ['assigned' => 'Phân công thực hiện'],
                S::Assigned => ['in_progress' => 'Bắt đầu thực hiện'],
                S::InProgress, S::Revision => ['pending_approval' => 'Nộp sản phẩm để duyệt'],
                S::PendingApproval => ['revision' => 'Yêu cầu chỉnh sửa'],
                S::Approved => ['published' => 'Xác nhận đã đăng'],
                S::Published => ['completed' => 'Hoàn thành và lưu trữ'],
                S::OnHold => ['received' => 'Tiếp tục xử lý'], default => [],
            };
            if (! in_array($item->status, [S::Draft, S::InternalReview, S::Completed, S::Cancelled, S::OnHold, S::Published])) {
                $actions['on_hold'] = 'Tạm hoãn';
            }
            if (! in_array($item->status, [S::Completed, S::Cancelled, S::Published])) {
                $actions['cancelled'] = 'Hủy yêu cầu';
            }
        }
        if ($user->role === Role::Media && $item->assignee_id === $user->id) {
            $actions += match ($item->status) {
                S::Assigned => ['in_progress' => 'Bắt đầu thực hiện'],
                S::InProgress, S::Revision => ['pending_approval' => 'Nộp sản phẩm để duyệt'],
                S::Approved => ['published' => 'Xác nhận đã đăng'],
                S::Published => ['completed' => 'Xác nhận hoàn thành'], default => [],
            };
        }
        if ($item->status === S::PendingApproval && $sameUnit && $user->role === Role::Head && ! $item->professional_checked_by) {
            $actions['professional_check'] = 'Xác nhận nội dung chuyên môn';
        }
        if ($item->status === S::PendingApproval && $item->professional_checked_by) {
            if (($item->important && $user->role === Role::Director) || (! $item->important && $user->isOffice())) {
                $actions['approved'] = 'Phê duyệt sản phẩm';
            }
        }
        if ($item->status === S::PendingApproval && $item->important && $user->role === Role::Director) {
            $actions['revision'] = 'Yêu cầu chỉnh sửa';
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
            $note = $data['note'] ?? null;
            if (in_array($target, ['needs_info', 'revision', 'on_hold', 'cancelled', 'draft']) && ! $note) {
                throw ValidationException::withMessages(['note' => 'Vui lòng ghi rõ lý do hoặc nội dung cần bổ sung.']);
            }
            $before = $item->status->value;
            if ($target === 'assigned') {
                $assignee = User::where('active', true)->where('role', Role::Media->value)->find($data['assignee_id'] ?? null);
                if (! $assignee) {
                    throw ValidationException::withMessages(['assignee_id' => 'Chọn nhân sự truyền thông đang hoạt động.']);
                }
                $item->assignee_id = $assignee->id;
            }
            if ($target === 'pending_approval' && ! $item->attachments()->where('kind', 'product')->exists() && ! $item->product_notes) {
                throw ValidationException::withMessages(['target' => 'Hãy nộp tệp sản phẩm hoặc nội dung sản phẩm trước khi trình duyệt.']);
            }
            if ($target === 'professional_check') {
                $item->professional_checked_by = $user->id;
            } else {
                $item->status = S::from($target);
            }
            if ($target === 'submitted') {
                $item->submitted_at ??= now();
            }
            if ($target === 'revision') {
                $item->professional_checked_by = null;
            }
            if ($target === 'published') {
                if (empty($data['published_url'])) {
                    throw ValidationException::withMessages(['published_url' => 'Cần đường dẫn nội dung đã đăng.']);
                }
                $item->published_url = $data['published_url'];
            }
            if ($target === 'completed') {
                $item->progress = 100;
                $item->completed_at = now();
            }
            $item->save();
            $item->histories()->create(['user_id' => $user->id, 'action' => $actions[$target], 'from_status' => $before, 'to_status' => $item->status->value, 'note' => $note]);
            $recipients = User::where('active', true)->where(function ($q) use ($item) {
                $q->whereIn('id', array_filter([$item->creator_id, $item->contact_id, $item->assignee_id]))
                    ->orWhere('role', 'office')->orWhere(fn ($q) => $q->where('role', 'head')->where('department_id', $item->department_id));
                if ($item->important && $item->status === S::PendingApproval) {
                    $q->orWhere('role', 'director');
                }
            })->where('id', '!=', $user->id)->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new RequestUpdated($item, $actions[$target]));
            }
        });
    }
}
