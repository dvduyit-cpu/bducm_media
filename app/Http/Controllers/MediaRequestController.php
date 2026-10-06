<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\RequestUpdated;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MediaRequestController extends Controller
{
    public static function filtered(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:200', 'department_id' => 'nullable|integer',
            'status' => ['nullable', Rule::enum(RequestStatus::class)], 'priority' => 'nullable|in:low,normal,high,urgent',
            'assignee_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'deadline' => 'nullable|in:overdue,soon']);

        return MediaRequest::visibleTo($request->user())->with(['department', 'assignee'])
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%'.$v.'%'))
            ->when($data['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($data['assignee_id'] ?? null, fn ($q, $v) => $q->where('assignee_id', $v))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('publish_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('publish_at', '<=', $v))
            ->when(($data['deadline'] ?? '') === 'overdue', fn ($q) => $q->active()->where('due_at', '<', now()))
            ->when(($data['deadline'] ?? '') === 'soon', fn ($q) => $q->active()->whereBetween('due_at', [now(), now()->addDays(2)]));
    }

    public static function filterOptions(Request $request): array
    {
        return ['departments' => Department::when(! $request->user()->isLeadership(), fn ($q) => $q->whereKey($request->user()->department_id))->orderBy('name')->get(),
            'assignees' => User::where('role', 'media')->where('active', true)->orderBy('name')->get(), 'statuses' => RequestStatus::cases()];
    }

    public function index(Request $request)
    {
        return view('requests.index', ['items' => self::filtered($request)->latest()->paginate(12)->withQueryString(), ...self::filterOptions($request)]);
    }

    public function create(Request $request)
    {
        abort_unless(in_array($request->user()->role, [Role::Office, Role::Head, Role::Staff]), 403);

        return view('requests.form', ['item' => new MediaRequest, ...$this->formOptions($request)]);
    }

    private function formOptions(Request $request): array
    {
        return ['departments' => Department::when(! $request->user()->isOffice(), fn ($q) => $q->whereKey($request->user()->department_id))->get(),
            'contacts' => User::where('active', true)->whereIn('role', ['head', 'staff'])->when(! $request->user()->isOffice(), fn ($q) => $q->where('department_id', $request->user()->department_id))->get()];
    }

    private function validated(Request $request): array
    {
        $department = $request->user()->isOffice() ? $request->input('department_id') : $request->user()->department_id;
        abort_unless($request->user()->isOffice() || (int) $request->input('department_id') === $request->user()->department_id, 403);
        $data = $request->validate(['title' => 'required|string|max:255', 'description' => 'required|string|max:30000',
            'department_id' => 'required|exists:departments,id', 'contact_id' => ['required', Rule::exists('users', 'id')->where('department_id', $department)->where('active', true)],
            'event_at' => 'required|date', 'publish_at' => 'required|date', 'due_at' => 'required|date|before_or_equal:publish_at',
            'channel' => 'required|in:Website,Facebook,Zalo,Email,Đa kênh', 'priority' => 'required|in:low,normal,high,urgent',
            'important' => 'nullable|boolean']);
        abort_unless($request->user()->isOffice() || (int) $data['department_id'] === $request->user()->department_id, 403);
        $data['important'] = $request->boolean('important');

        return $data;
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, [Role::Office, Role::Head, Role::Staff]), 403);
        $data = $this->validated($request);
        $item = DB::transaction(function () use ($data, $request) {
            $item = MediaRequest::create([...$data, 'creator_id' => $request->user()->id]);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Tạo yêu cầu', 'to_status' => 'draft']);

            return $item;
        });

        return redirect()->route('requests.show', $item)->with('success', 'Đã lưu bản nháp. Đính kèm tài liệu và gửi yêu cầu khi sẵn sàng.');
    }

    public function show(Request $request, MediaRequest $mediaRequest, RequestWorkflow $workflow)
    {
        $this->authorizeView($request, $mediaRequest);

        return view('requests.show', ['item' => $mediaRequest->load(['department', 'creator', 'contact', 'assignee', 'histories.user', 'attachments']),
            'actions' => $workflow->actions($mediaRequest, $request->user()),
            'assignees' => User::where('role', 'media')->where('active', true)->get()]);
    }

    public function edit(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        abort_unless($mediaRequest->canEdit($request->user()), 403);

        return view('requests.form', ['item' => $mediaRequest, ...$this->formOptions($request)]);
    }

    public function update(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request, $mediaRequest) {
            $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->canEdit($request->user()), 403);
            // A draft cannot be moved to another unit after creation.
            abort_unless((int) $data['department_id'] === $item->department_id, 422);
            $item->update($data);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Cập nhật nội dung', 'note' => 'Thông tin, lịch hoặc người phối hợp đã được cập nhật.']);
        });

        return redirect()->route('requests.show', $mediaRequest)->with('success', 'Đã cập nhật yêu cầu.');
    }

    public function transition(Request $request, MediaRequest $mediaRequest, RequestWorkflow $workflow)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['target' => 'required|string', 'note' => 'nullable|string|max:5000',
            'assignee_id' => 'nullable|integer', 'published_url' => 'nullable|url:http,https|max:2048']);
        $workflow->transition($mediaRequest, $request->user(), $data);

        return back()->with('success', 'Đã cập nhật trạng thái và thông báo cho người liên quan.');
    }

    public function coordinate(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        abort_unless($request->user()->isOffice() || ($request->user()->role === Role::Head && $request->user()->department_id === $mediaRequest->department_id), 403);
        $data = $request->validate(['contact_id' => ['required', Rule::exists('users', 'id')->where('department_id', $mediaRequest->department_id)->where('active', true)],
            'priority' => 'required|in:low,normal,high,urgent', 'important' => 'nullable|boolean', 'publish_at' => 'required|date', 'due_at' => 'required|date|before_or_equal:publish_at', 'assignee_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'media')->where('active', true)], 'note' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $data, $mediaRequest) {
            $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($item->status, [RequestStatus::Approved, RequestStatus::Published, RequestStatus::Completed, RequestStatus::Cancelled]), 422, 'Yêu cầu đã chốt, không thể thay đổi điều phối.');
            $changes = ['contact_id' => $data['contact_id']];
            if ($request->user()->isOffice()) {
                $changes += ['priority' => $data['priority'], 'publish_at' => $data['publish_at'], 'due_at' => $data['due_at'], 'important' => $request->boolean('important')];
                if (in_array($item->status, [RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Revision])) {
                    $changes['assignee_id'] = $data['assignee_id'] ?? $item->assignee_id;
                }
            }
            $item->update($changes);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Cập nhật điều phối', 'note' => $data['note'].' | Đầu mối: '.$item->contact->name.' | Ưu tiên: '.$item->priority.' | Hạn: '.$item->due_at->format('d/m/Y H:i').' | Phụ trách: '.($item->assignee?->name ?? 'Chưa phân công')]);
            if ($item->assignee && $item->assignee_id !== $request->user()->id) {
                $item->assignee->notify(new RequestUpdated($item, 'Lịch, người phụ trách hoặc thông tin điều phối đã thay đổi'));
            }
        });

        return back()->with('success', 'Đã cập nhật điều phối.');
    }

    public function progress(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['progress' => 'required|integer|min:0|max:100', 'product_notes' => 'nullable|string|max:30000', 'note' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $data, $mediaRequest) {
            $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless(($request->user()->isOffice() || ($request->user()->role === Role::Media && $item->assignee_id === $request->user()->id)) && in_array($item->status, [RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Revision]), 403);
            $item->update(['progress' => $data['progress'], 'product_notes' => $data['product_notes'] ?? null, 'professional_checked_by' => null]);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Cập nhật tiến độ '.$data['progress'].'%', 'note' => $data['note']]);
        });

        return back()->with('success', 'Đã lưu tiến độ và nội dung sản phẩm.');
    }

    public function upload(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,mp4,mp3,txt', 'kind' => 'required|in:source,product']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $data, $mediaRequest, &$path) {
                $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
                $canProduct = ($request->user()->isOffice() || ($request->user()->role === Role::Media && $item->assignee_id === $request->user()->id)) && in_array($item->status, [RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Revision]);
                abort_unless($data['kind'] === 'product' ? $canProduct : $item->canEdit($request->user()), 403);
                $file = $request->file('file');
                $path = $file->store('requests/'.$item->id, 'local');
                $item->attachments()->create(['user_id' => $request->user()->id, 'name' => $file->getClientOriginalName(), 'path' => $path, 'kind' => $data['kind'], 'size' => $file->getSize()]);
                if ($data['kind'] === 'product') {
                    $item->update(['professional_checked_by' => null]);
                }
                $item->histories()->create(['user_id' => $request->user()->id, 'action' => $data['kind'] === 'product' ? 'Nộp tệp sản phẩm' : 'Bổ sung tài liệu', 'note' => $file->getClientOriginalName()]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return back()->with('success', 'Đã tải tệp lên kho lưu trữ riêng.');
    }

    public function download(Request $request, Attachment $attachment)
    {
        $this->authorizeView($request, $attachment->mediaRequest);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    private function authorizeView(Request $request, MediaRequest $item): void
    {
        abort_unless(MediaRequest::visibleTo($request->user())->whereKey($item->id)->exists(), 404);
    }
}
