<div hidden>
<form id="delete-event-{{ $item->id }}" data-modal data-modal-title="Xóa sự kiện {{ $item->code }}" method="post" action="{{ route('requests.destroy',$item) }}" class="stack-form">
@csrf @method('DELETE')
<p>Xóa sự kiện <strong>{{ $item->title }}</strong>?</p>
<p>Thao tác này xóa sự kiện cùng tài liệu, liên kết và lịch sử xử lý. Không thể khôi phục sau khi xóa.</p>
<label class="check-label"><input type="checkbox" name="confirm_delete" value="1" required>Tôi xác nhận xóa sự kiện này</label>
<button class="button danger" type="submit">Xóa sự kiện</button>
</form>
</div>
