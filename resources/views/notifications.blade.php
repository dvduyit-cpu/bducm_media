@extends('layouts.app')
@section('title','Thông báo')
@section('content')
<div class="page-heading"><div><div class="eyebrow">CẬP NHẬT CÔNG VIỆC</div><h1>Thông báo của bạn</h1><p>Thay đổi trạng thái, yêu cầu phối hợp và nhắc hạn công việc.</p></div></div>
<div class="panel activity-list">@forelse($notifications as $notification)<a class="activity-item" href="{{ route('requests.show',$notification->data['request_id']) }}"><span class="activity-icon"><x-icon name="bell"/></span><div><strong>{{ $notification->data['title'] }}</strong><p>{{ $notification->data['message'] }}</p><small>{{ $notification->created_at->format('d/m/Y H:i') }}</small></div><x-icon name="arrow" size="18"/></a>@empty<div class="empty-state"><x-icon name="bell" size="36"/><strong>Bạn chưa có thông báo</strong><p>Các cập nhật liên quan sẽ xuất hiện tại đây.</p></div>@endforelse</div><div class="pagination-wrap">{{ $notifications->links('partials.pagination') }}</div>
@endsection