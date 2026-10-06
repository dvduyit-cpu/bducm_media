@extends('layouts.app')
@section('title', $pageTitle ?? 'Sự kiện phòng ban')
@section('content')
<div class="page-heading"><div><div class="eyebrow">QUẢN LÝ CÔNG VIỆC</div><h1>{{ $pageTitle ?? 'Sự kiện phòng ban' }}</h1><p>{{ isset($pageTitle) ? 'Kiểm tra nội dung và chọn tự chủ hoặc phòng ban hỗ trợ.' : 'Theo dõi sự kiện chủ trì và sự kiện được phân công hỗ trợ.' }}</p></div>@if(in_array(auth()->user()->role,[\App\Enums\Role::Admin,\App\Enums\Role::Office,\App\Enums\Role::Head,\App\Enums\Role::Staff]))<a href="{{ route('requests.create') }}" class="button primary"><x-icon name="plus" size="18"/>Gửi sự kiện mới</a>@endif</div>
@include('partials.filters')
<div class="panel"><div class="panel-heading"><h2>{{ $items->total() }} sự kiện</h2><span class="muted">Dữ liệu theo quyền truy cập của bạn</span></div>@include('partials.request-table')<div class="pagination-wrap">{{ $items->links('partials.pagination') }}</div></div>
@endsection