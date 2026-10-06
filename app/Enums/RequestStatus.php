<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case InternalReview = 'internal_review';
    case Submitted = 'submitted';
    case Received = 'received';
    case NeedsInfo = 'needs_info';
    case Ready = 'ready';
    case AwaitingAssignment = 'awaiting_assignment';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case PendingApproval = 'pending_approval';
    case Revision = 'revision';
    case Approved = 'approved';
    case Published = 'published';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp', self::InternalReview => 'Chờ duyệt nội bộ', self::Submitted => 'Đã gửi',
            self::Received => 'VP BGĐ tiếp nhận', self::NeedsInfo => 'Yêu cầu bổ sung', self::Ready => 'Đã đủ thông tin',
            self::AwaitingAssignment => 'Chờ phân công', self::Assigned => 'Đã phân công', self::InProgress => 'Đang thực hiện',
            self::PendingApproval => 'Chờ duyệt', self::Revision => 'Yêu cầu chỉnh sửa', self::Approved => 'Đã duyệt',
            self::Published => 'Đã đăng', self::Completed => 'Đã đóng', self::OnHold => 'Tạm hoãn', self::Cancelled => 'Đã hủy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed, self::Approved, self::Published => 'green',
            self::NeedsInfo, self::Revision, self::OnHold => 'orange',
            self::Cancelled => 'red', self::Draft => 'gray', default => 'blue',
        };
    }
}
