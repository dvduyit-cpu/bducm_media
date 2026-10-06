<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
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
            'deadline' => 'nullable|in:overdue,soon', 'basis' => 'nullable|in:activity,publication', 'coordination_mode' => 'nullable|in:pending,autonomous,support', 'tag' => 'nullable|string|max:80']);
        $dateColumn = 'event_at';

        return MediaRequest::visibleTo($request->user())->with(['department', 'contact', 'supportDepartments'])
            ->when($data['coordination_mode'] ?? null, fn ($q, $v) => $q->where('coordination_mode', $v))
            ->when($data['tag'] ?? null, fn ($q, $v) => $q->whereJsonContains('content_tags', $v))
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%'.$v.'%'))
            ->when($data['department_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('department_id', $v)->orWhereHas('supportDepartments', fn ($q) => $q->where('departments.id', $v))))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($data['assignee_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('assignee_id', $v)->orWhere('media_assignee_id', $v)))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate($dateColumn, '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate($dateColumn, '<=', $v))
            ->when(($data['deadline'] ?? '') === 'overdue', fn ($q) => $q->active()->where('due_at', '<', now()))
            ->when(($data['deadline'] ?? '') === 'soon', fn ($q) => $q->active()->whereBetween('due_at', [now(), now()->addDays(2)]));
    }

    public static function filterOptions(Request $request): array
    {
        return ['departments' => Department::when(! $request->user()->isLeadership(), fn ($q) => $q->whereKey($request->user()->department_id))->orderBy('name')->get(),
            'assignees' => User::where('role', 'media')->where('active', true)->whereNull('archived_at')->when(! $request->user()->isLeadership(), fn ($query) => $query->where(fn ($query) => $query->when($request->user()->department_id, fn ($query, $department) => $query->where('department_id', $department), fn ($query) => $query->whereKey($request->user()->id))->orWhereIn('id', MediaRequest::visibleTo($request->user())->select('assignee_id'))->orWhereIn('id', MediaRequest::visibleTo($request->user())->select('media_assignee_id'))))->orderBy('name')->get(), 'statuses' => MediaRequest::currentStatuses()];
    }

    public function index(Request $request)
    {
        return view('requests.index', ['items' => self::filtered($request)->with('latestHistory.user')->latest()->paginate(12)->withQueryString(), ...self::filterOptions($request)]);
    }

    public function create(Request $request)
    {
        abort_unless(in_array($request->user()->role, [Role::Admin, Role::Office, Role::Head, Role::Staff]), 403);

        return view('requests.form', ['item' => new MediaRequest, ...$this->formOptions($request)]);
    }

    private function formOptions(Request $request): array
    {
        return ['departments' => Department::when(! $request->user()->isLeadership(), fn ($q) => $q->whereKey($request->user()->department_id))->get(),
            'contacts' => User::where('active', true)->whereIn('role', ['head', 'staff'])->when(! $request->user()->isLeadership(), fn ($q) => $q->where('department_id', $request->user()->department_id))->get()];
    }

    private function validated(Request $request): array
    {
        $department = $request->user()->isLeadership() ? $request->input('department_id') : $request->user()->department_id;
        abort_unless($request->user()->isLeadership() || (int) $request->input('department_id') === $request->user()->department_id, 403);
        $data = $request->validate(['title' => 'required|string|max:255', 'description' => 'required|string|max:30000',
            'department_id' => 'required|exists:departments,id', 'contact_id' => ['required', Rule::exists('users', 'id')->where('department_id', $department)->where('active', true)],
            'event_at' => 'required|date', 'event_ends_at' => 'nullable|date|after_or_equal:event_at', 'location' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,normal,high,urgent', 'task_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);

        return [...$data, 'priority' => $data['priority'] ?? 'normal', 'task_color' => $data['task_color'] ?? $request->user()->theme_color ?? '#2563eb',
            'record_type' => 'activity', 'channel' => 'Website', 'due_at' => $data['event_ends_at'] ?? $data['event_at']];
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, [Role::Admin, Role::Office, Role::Head, Role::Staff]), 403);
        $data = $this->validated($request);
        $item = DB::transaction(function () use ($data, $request) {
            $item = MediaRequest::create([...$data, 'creator_id' => $request->user()->id]);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Tạo yêu cầu', 'to_status' => 'draft']);

            return $item;
        });

        return redirect()->route('requests.show', $item)->with('success', 'Đã lưu nháp. Thêm tài liệu rồi gửi sự kiện đến Văn phòng BGĐ.');
    }

    public function show(Request $request, MediaRequest $mediaRequest, RequestWorkflow $workflow)
    {
        $this->authorizeView($request, $mediaRequest);

        return view('requests.show', ['item' => $mediaRequest->load(['department', 'creator', 'contact', 'supportDepartments', 'approver', 'histories.user', 'attachments', 'links']),
            'actions' => $workflow->actions($mediaRequest, $request->user()),
            'supportOptions' => $request->user()->canReview() ? Department::whereKeyNot($mediaRequest->department_id)->orderBy('name')->get() : collect()]);
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
        $data = $request->validate(['target' => 'required|in:submitted,needs_info,approved,in_progress,completed,cancelled', 'note' => 'nullable|string|max:5000',
            'coordination_mode' => 'nullable|in:autonomous,support', 'support_department_ids' => 'nullable|array', 'support_department_ids.*' => 'integer', 'support_notes' => 'nullable|string|max:5000', 'due_at' => 'nullable|date']);
        $workflow->transition($mediaRequest, $request->user(), $data);

        return back()->with('success', 'Đã cập nhật trạng thái và thông báo cho người liên quan.');
    }

    public function coordinate(Request $request, MediaRequest $mediaRequest, RequestWorkflow $workflow)
    {
        $this->authorizeView($request, $mediaRequest);
        $workflow->coordinate($mediaRequest, $request->user(), $request->all());

        return back()->with('success', 'Đã cập nhật phương án phối hợp trên lịch các đơn vị.');
    }

    public function progress(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['progress' => 'required|integer|min:0|max:100', 'product_notes' => 'nullable|string|max:30000', 'note' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $data, $mediaRequest) {
            $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->canSubmitProduct($request->user()), 403);
            $item->update(['progress' => $data['progress'], 'product_notes' => $data['product_notes'] ?? null, 'status' => RequestStatus::InProgress]);
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Cập nhật tiến độ '.$data['progress'].'%', 'note' => $data['note']]);
        });

        return back()->with('success', 'Đã lưu tiến độ và nội dung sản phẩm.');
    }

    public function upload(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,mp4,mp3,txt|extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,mp4,mp3,txt', 'kind' => 'required|in:source,product']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $data, $mediaRequest, &$path) {
                $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
                $canProduct = $item->canSubmitProduct($request->user());
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

        $relative = str_replace('\\', '/', $attachment->path);
        abort_if(str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true) || str_contains($relative, ':') || str_contains($relative, "\0"), 404);
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($attachment->path));
        abort_unless($root && $path && str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/').'/'), 404);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $attachment->name))) ?: 'tai-lieu';

        return $disk->download($attachment->path, $name, ['Content-Type' => 'application/octet-stream']);
    }

    public function saveLink(Request $request, MediaRequest $mediaRequest)
    {
        $this->authorizeView($request, $mediaRequest);
        $data = $request->validate(['name' => 'required|string|max:255', 'url' => 'required|url:http,https|max:2048', 'kind' => 'required|in:source,product']);
        DB::transaction(function () use ($request, $mediaRequest, $data) {
            $item = MediaRequest::whereKey($mediaRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($data['kind'] === 'product' ? $item->canSubmitProduct($request->user()) : $item->canEdit($request->user()), 403);
            $item->links()->create([...$data, 'user_id' => $request->user()->id]);
            if ($data['kind'] === 'product') {
                $item->update(['professional_checked_by' => null]);
            }
            $item->histories()->create(['user_id' => $request->user()->id, 'action' => 'Bổ sung liên kết '.$data['kind'], 'note' => $data['name'].' | '.$data['url']]);
        });

        return back()->with('success', 'Đã lưu liên kết. Hãy cấp quyền truy cập tài liệu cho người phối hợp.');
    }

    private function authorizeView(Request $request, MediaRequest $item): void
    {
        abort_unless(MediaRequest::visibleTo($request->user())->whereKey($item->id)->exists(), 404);
    }
}
