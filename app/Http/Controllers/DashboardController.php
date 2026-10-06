<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaRequestController::filtered($request);
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $metrics = [
            ['label' => 'Kế hoạch tuần này', 'value' => (clone $query)->whereBetween('publish_at', [$start, $end])->count(), 'tone' => 'blue', 'filter' => ['from' => $start->toDateString(), 'to' => $end->toDateString()]],
            ['label' => 'Yêu cầu mới', 'value' => $counts['submitted'] ?? 0, 'tone' => 'blue', 'filter' => ['status' => 'submitted']],
            ['label' => 'Cần bổ sung', 'value' => $counts['needs_info'] ?? 0, 'tone' => 'orange', 'filter' => ['status' => 'needs_info']],
            ['label' => 'Chưa phân công', 'value' => $counts['awaiting_assignment'] ?? 0, 'tone' => 'orange', 'filter' => ['status' => 'awaiting_assignment']],
            ['label' => 'Đang thực hiện', 'value' => $counts['in_progress'] ?? 0, 'tone' => 'blue', 'filter' => ['status' => 'in_progress']],
            ['label' => 'Sắp đến hạn', 'value' => (clone $query)->active()->whereBetween('due_at', [now(), now()->addDays(2)])->count(), 'tone' => 'orange', 'filter' => ['deadline' => 'soon']],
            ['label' => 'Đã quá hạn', 'value' => (clone $query)->active()->where('due_at', '<', now())->count(), 'tone' => 'red', 'filter' => ['deadline' => 'overdue']],
            ['label' => 'Đã hoàn thành', 'value' => $counts['completed'] ?? 0, 'tone' => 'green', 'filter' => ['status' => 'completed']],
        ];

        return view('dashboard', ['metrics' => $metrics, 'items' => (clone $query)->active()->orderBy('due_at')->limit(6)->get(),
            'weekItems' => (clone $query)->whereBetween('publish_at', [$start, $end])->orderBy('publish_at')->get(),
            'recent' => (clone $query)->latest('updated_at')->limit(5)->get(), ...MediaRequestController::filterOptions($request)]);
    }

    public function calendar(Request $request)
    {
        $request->validate(['week' => 'nullable|date']);
        $start = $request->filled('week') ? Carbon::parse($request->week)->startOfWeek() : now()->startOfWeek();
        $items = MediaRequestController::filtered($request)->whereBetween('publish_at', [$start, $start->copy()->endOfWeek()])->orderBy('publish_at')->get();

        return view('calendar', ['start' => $start, 'items' => $items, ...MediaRequestController::filterOptions($request)]);
    }

    public function approvals(Request $request)
    {
        return view('requests.index', ['items' => MediaRequestController::filtered($request)->whereIn('status', ['internal_review', 'pending_approval'])->orderBy('due_at')->paginate(12)->withQueryString(), 'pageTitle' => 'Hàng đợi phê duyệt', ...MediaRequestController::filterOptions($request)]);
    }

    public function library(Request $request)
    {
        return view('library', ['items' => MediaRequestController::filtered($request)->whereHas('attachments')->with('attachments')->latest()->paginate(12)->withQueryString(), ...MediaRequestController::filterOptions($request)]);
    }

    public function notifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return view('notifications', ['notifications' => $request->user()->notifications()->paginate(20)]);
    }

    public function reports(Request $request)
    {
        $items = MediaRequestController::filtered($request)->get();
        $rows = $items->groupBy('department_id')->map(fn ($group) => ['name' => $group->first()->department->name,
            'total' => $group->count(), 'completed' => $group->where('status', RequestStatus::Completed)->count(),
            'working' => $group->filter(fn ($i) => in_array($i->status, [RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Revision]))->count(),
            'needs_info' => $group->where('status', RequestStatus::NeedsInfo)->count(),
            'overdue' => $group->filter(fn ($i) => $i->overdue)->count(),
            'on_time' => $group->filter(fn ($i) => $i->completed_at && $i->completed_at->lte($i->due_at))->count()]);
        if ($request->boolean('export')) {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Đơn vị', 'Tổng yêu cầu', 'Hoàn thành', 'Đang thực hiện', 'Cần bổ sung', 'Quá hạn', 'Hoàn thành đúng hạn'], ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r\n]/u', $v) ? "'".$v : $v, array_values($row)), ',', '"', '');
                }
                fclose($out);
            }, 'bao-cao-truyen-thong-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('reports', ['rows' => $rows, 'total' => $items->count(), 'completed' => $items->where('status', RequestStatus::Completed)->count(), ...MediaRequestController::filterOptions($request)]);
    }
}
