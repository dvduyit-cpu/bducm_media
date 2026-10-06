@extends('layouts.app')
@section('title','Tổng quan truyền thông')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ĐIỀU PHỐI SỰ KIỆN</div><h1>Tổng quan truyền thông</h1><p>Phòng ban gửi sự kiện · VP BGĐ duyệt phương án · Cùng thực hiện · VP BGĐ đóng sự kiện.</p></div><div class="heading-actions"><a class="button secondary" href="{{ route('settings') }}#board-settings">Đặt tên & màu bảng</a>@if(in_array(auth()->user()->role,[\App\Enums\Role::Admin,\App\Enums\Role::Office,\App\Enums\Role::Head,\App\Enums\Role::Staff]))<a class="button primary" href="{{ route('requests.create') }}">+ Gửi sự kiện mới</a>@endif</div></div>
@include('partials.filters')
<div class="board-summary"><span><strong>{{ $items->count() }}</strong> sự kiện trong bộ lọc</span><span><strong>{{ $items->where('coordination_mode','support')->count() }}</strong> cần hỗ trợ</span><span><strong>{{ $items->filter(fn($i)=>$i->overdue)->count() }}</strong> quá hạn</span></div>
<div class="event-board" style="--board-bg:{{ $boardBackground }}">
@foreach($columns as $key=>$column)
@php($cards=$items->filter(fn($i)=>$key==='submitted' ? in_array($i->status,[\App\Enums\RequestStatus::Submitted,\App\Enums\RequestStatus::NeedsInfo]) : $i->status->value===$key))
<section class="board-column" style="--column-color:{{ $column['color'] }}" data-board-column="{{ $key }}"><header class="board-column-heading"><h2>{{ $column['title'] }}</h2><span>{{ $cards->count() }}</span></header><div class="board-cards">
@forelse($cards as $item)
<a class="event-card" href="{{ route('requests.show',$item) }}" style="--task-color:{{ $item->task_color }}" data-event-id="{{ $item->id }}"><div class="event-card-color"></div><div class="event-card-content"><div class="event-card-meta"><span>{{ $item->code }}</span><span class="priority {{ $item->priority }}">{{ ['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn cấp'][$item->priority] }}</span></div><h3>{{ $item->title }}</h3><p class="event-card-unit">{{ $item->department->name }}</p><span class="badge {{ $item->coordination_mode==='support' ? 'orange' : 'gray' }}">{{ $item->modeLabel() }}</span>@if($item->status===\App\Enums\RequestStatus::NeedsInfo)<span class="badge orange">Cần bổ sung</span>@endif
@if($item->supportDepartments->isNotEmpty())<div class="event-card-support">Hỗ trợ: {{ $item->supportDepartments->pluck('code')->implode(', ') }}</div>@endif
<div class="event-card-date">{{ $item->event_at->format('d/m/Y H:i') }}@if($item->location)<br>{{ $item->location }}@endif</div>
@if(in_array($item->status,[\App\Enums\RequestStatus::Approved,\App\Enums\RequestStatus::InProgress]))<div class="progress-track"><span style="width:{{ $item->progress }}%"></span></div><small>{{ $item->progress }}% @if($item->overdue)<strong class="text-red">· Quá hạn</strong>@endif</small>@endif</div></a>
@empty<div class="board-empty">Chưa có sự kiện</div>@endforelse
</div></section>
@endforeach
</div>
@endsection