<div class="table-scroll"><table class="data-table request-table"><thead><tr><th>SỰ KIỆN</th><th>ĐƠN VỊ CHỦ TRÌ</th><th>PHƯƠNG ÁN / HỖ TRỢ</th><th>THỜI GIAN</th><th>TRẠNG THÁI</th><th>TIẾN ĐỘ</th><th>XỬ LÝ</th></tr></thead><tbody>@forelse($items as $item)<tr><td><a class="request-title" href="{{ route('requests.show',$item) }}">{{ $item->title }}</a><small class="cell-meta">{{ $item->code }}</small></td><td data-label="Chủ trì">{{ $item->department->name }}</td><td data-label="Phương án">{{ $item->modeLabel() }}<small class="cell-meta">{{ $item->supportDepartments->pluck('name')->implode(', ') }}</small></td><td data-label="Thời gian">{{ $item->event_at->format('d/m/Y H:i') }}<small class="cell-meta {{ $item->overdue ? 'text-red' : '' }}">{{ $item->overdue ? 'Quá hạn thực hiện' : $item->location }}</small></td><td data-label="Trạng thái"><span class="badge {{ $item->status->tone() }}">{{ $item->status->label() }}</span><small class="cell-meta">Cập nhật {{ $item->updated_at->format('d/m/Y H:i') }}</small>@if($item->latestHistory)<small class="cell-meta">{{ $item->latestHistory->user?->name }}</small>@if($item->status===\App\Enums\RequestStatus::NeedsInfo && $item->latestHistory->to_status==='needs_info')<small class="cell-meta request-status-note">Trả lại: {{ $item->latestHistory->note }}</small>@endif @endif</td><td data-label="Tiến độ">{{ $item->progress }}%</td><td data-label="Xử lý"><div class="review-actions">@if(auth()->user()->canReview() && $item->status===\App\Enums\RequestStatus::Submitted)<button type="button" class="button primary" data-modal-trigger="approve-event-{{ $item->id }}">Duyệt</button><button type="button" class="button secondary" data-modal-trigger="return-event-{{ $item->id }}">Trả lại</button>@endif<a class="row-arrow" href="{{ route('requests.show',$item) }}" aria-label="Xem {{ $item->code }}"><x-icon name="arrow" size="17"/></a></div></td></tr>@empty<tr><td colspan="7"><div class="empty-state">Chưa có sự kiện trong bộ lọc.</div></td></tr>@endforelse</tbody></table></div>
@if(auth()->user()->canReview())
@foreach($items as $item)
@if($item->status===\App\Enums\RequestStatus::Submitted)
<div hidden>
<form id="approve-event-{{ $item->id }}" action="{{ route('requests.transition',$item) }}" method="post" data-modal data-modal-title="Duyệt sự kiện {{ $item->code }}" data-coordination-form>
@csrf<input type="hidden" name="target" value="approved">
<p><strong>{{ $item->title }}</strong></p>
@include('requests.coordination-fields',['supportOptions'=>$departments->where('id','!=',$item->department_id)])
<label>Ghi chú duyệt<textarea name="note" maxlength="5000" rows="3">{{ old('note') }}</textarea></label>
<button class="button primary" type="submit">Duyệt sự kiện</button>
</form>
<form id="return-event-{{ $item->id }}" action="{{ route('requests.transition',$item) }}" method="post" data-modal data-modal-title="Trả lại sự kiện {{ $item->code }}">
@csrf<input type="hidden" name="target" value="needs_info">
<p><strong>{{ $item->title }}</strong></p><p>Đơn vị chủ trì sẽ nhận thông báo để chỉnh sửa và gửi lại kiểm duyệt.</p>
<label>Lý do trả lại / nội dung cần bổ sung <b>*</b><textarea name="note" required maxlength="5000" rows="4">{{ old('note') }}</textarea></label>
<button class="button primary" type="submit">Trả lại để bổ sung</button>
</form>
</div>
@endif
@endforeach
@endif
