@extends('layouts.app')
@section('title', $pageTitle ?? 'Yêu cầu truyền thông')
@section('content')
<div class="page-heading"><div><div class="eyebrow">QUẢN LÝ CÔNG VIỆC</div><h1>{{ $pageTitle ?? 'Yêu cầu truyền thông' }}</h1><p>{{ isset($pageTitle) ? 'Duyệt nội bộ, kiểm tra chuyên môn và phê duyệt sản phẩm.' : 'Theo dõi toàn bộ hành trình từ đề xuất đến xuất bản.' }}</p></div>@if(in_array(auth()->user()->role,[\App\Enums\Role::Office,\App\Enums\Role::Head,\App\Enums\Role::Staff]))<a href="{{ route('requests.create') }}" class="button primary"><x-icon name="plus" size="18"/>Tạo yêu cầu</a>@endif</div>
@include('partials.filters')
<div class="panel"><div class="panel-heading"><h2>{{ $items->total() }} yêu cầu</h2><span class="muted">Dữ liệu theo quyền truy cập của bạn</span></div>@include('partials.request-table')<div class="pagination-wrap">{{ $items->links('partials.pagination') }}</div></div>
@endsection