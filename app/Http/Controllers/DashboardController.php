<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\MediaRequest;
use App\Services\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $items = MediaRequestController::filtered($request)->whereNotIn('status', ['cancelled', 'on_hold'])->orderBy('event_at')->get();
        $columns = MediaRequest::boardColumns();
        $preferences = $request->user()->board_preferences ?? [];
        foreach ($columns as $key => &$column) {
            $column['title'] = $preferences['columns'][$key]['title'] ?? $column['title'];
            $column['color'] = $preferences['columns'][$key]['color'] ?? $column['color'];
        }
        unset($column);

        return view('dashboard', ['items' => $items, 'columns' => $columns, 'boardBackground' => $preferences['background'] ?? '#eef2f7', ...MediaRequestController::filterOptions($request)]);
    }

    public function calendar(Request $request)
    {
        $data = $request->validate(['week' => 'nullable|date', 'view' => 'nullable|in:week,day,table', 'export' => 'nullable|in:xlsx,pdf']);
        $view = $data['view'] ?? 'week';
        $anchor = $request->filled('week') ? Carbon::parse($request->week) : now();
        $start = $view === 'day' ? $anchor->copy()->startOfDay() : $anchor->copy()->startOfWeek();
        $end = $view === 'day' ? $start->copy()->endOfDay() : $start->copy()->endOfWeek();
        $items = MediaRequestController::filtered($request)->whereNotIn('status', ['draft', 'cancelled'])->where('event_at', '<=', $end)
            ->where(fn ($q) => $q->where('event_ends_at', '>=', $start)->orWhere(fn ($q) => $q->whereNull('event_ends_at')->where('event_at', '>=', $start)))->orderBy('event_at')->get();
        if ($request->filled('export')) {
            $rows = $items->map(fn ($i) => [$i->title, $i->department->name, $i->supportDepartments->pluck('name')->implode(', '), $i->event_at->format('d/m/Y H:i'), $i->event_ends_at?->format('d/m/Y H:i'), $i->location, $i->modeLabel(), $i->status->label()])->all();

            return app(TabularExport::class)->download($data['export'], 'Lịch sự kiện '.$start->format('d/m/Y').' – '.$end->format('d/m/Y'), ['Sự kiện', 'Đơn vị chủ trì', 'Đơn vị hỗ trợ', 'Bắt đầu', 'Kết thúc', 'Địa điểm', 'Phương án', 'Trạng thái'], $rows);
        }

        return view('calendar', ['start' => $start, 'end' => $end, 'items' => $items, 'calendarView' => $view, ...MediaRequestController::filterOptions($request)]);
    }

    public function approvals(Request $request)
    {
        return view('requests.index', ['items' => MediaRequestController::filtered($request)->where('status', 'submitted')->with('latestHistory.user')->orderBy('due_at')->paginate(12)->withQueryString(), 'pageTitle' => 'Sự kiện chờ Văn phòng BGĐ duyệt', ...MediaRequestController::filterOptions($request)]);
    }

    public function library(Request $request)
    {
        return view('library', ['items' => MediaRequestController::filtered($request)->where(fn ($q) => $q->whereHas('attachments')->orWhereHas('links'))->with(['attachments', 'links'])->latest()->paginate(12)->withQueryString(), ...MediaRequestController::filterOptions($request)]);
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()->visibleNotifications();
        (clone $notifications)->whereNull('read_at')->update(['read_at' => now()]);

        return view('notifications', ['notifications' => $notifications->paginate(20)]);
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['period' => 'nullable|in:month,quarter,all', 'period_date' => 'nullable|date', 'export' => 'nullable|in:1,xlsx,pdf']);
        $period = $data['period'] ?? 'all';
        $anchor = $request->filled('period_date') ? Carbon::parse($request->period_date) : now();
        $query = MediaRequestController::filtered($request)->whereNotIn('status', ['draft', 'cancelled']);
        if ($period !== 'all') {
            $start = $period === 'quarter' ? $anchor->copy()->startOfQuarter() : $anchor->copy()->startOfMonth();
            $end = $period === 'quarter' ? $anchor->copy()->endOfQuarter() : $anchor->copy()->endOfMonth();
            $query->whereBetween('event_at', [$start, $end]);
        }
        $items = $query->get();
        $units = MediaRequestController::filterOptions($request)['departments'];
        if ($request->filled('department_id')) {
            $units = $units->where('id', (int) $request->department_id);
        }
        $rows = $units->map(function ($unit) use ($items) {
            $hosted = $items->where('department_id', $unit->id);
            $supported = $items->filter(fn ($i) => $i->supportDepartments->contains('id', $unit->id));
            $group = $hosted->merge($supported)->unique('id');
            $finished = $group->filter(fn ($i) => $i->completed_at && $i->due_at);

            return ['name' => $unit->name, 'hosted' => $hosted->count(), 'supported' => $supported->count(), 'completed' => $group->where('status', RequestStatus::Completed)->count(),
                'working' => $group->whereIn('status', [RequestStatus::Approved, RequestStatus::InProgress])->count(), 'overdue' => $group->filter(fn ($i) => $i->overdue)->count(),
                'on_time' => $finished->filter(fn ($i) => $i->completed_at->lte($i->due_at))->count(), 'late' => $finished->filter(fn ($i) => $i->completed_at->gt($i->due_at))->count()];
        });
        $headers = ['Đơn vị', 'Chủ trì', 'Hỗ trợ', 'Đã đóng', 'Đang thực hiện', 'Quá hạn', 'Đúng hạn', 'Trễ hạn'];
        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            return app(TabularExport::class)->download($data['export'], 'Báo cáo sự kiện · '.$anchor->format('m/Y'), $headers, $rows->map(fn ($row) => array_values($row))->all());
        }
        if ($request->input('export') === '1') {
            return response()->streamDownload(function () use ($rows, $headers) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers, ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r\n]/u', $v) ? "'".$v : $v, array_values($row)), ',', '"', '');
                }
                fclose($out);
            }, 'bao-cao-su-kien-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('reports', ['rows' => $rows, 'items' => $items, 'total' => $items->count(), 'completed' => $items->where('status', RequestStatus::Completed)->count(), 'period' => $period, 'anchor' => $anchor, ...MediaRequestController::filterOptions($request)]);
    }
}
