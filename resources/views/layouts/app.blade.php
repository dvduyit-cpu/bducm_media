<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml"><title>@yield('title', 'Tổng quan') · BDU Media</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
<script defer src="{{ asset('js/modals.js') }}?v={{ filemtime(public_path('js/modals.js')) }}"></script>
@php
    $theme = auth()->user()->theme_color ?? \App\Models\Setting::valueOf('default_color', '#2563eb');
    $theme = preg_match('/^#[0-9a-fA-F]{6}$/', $theme) ? $theme : '#2563eb';
    $channels = array_map(fn ($value) => $value / 255, sscanf($theme, '#%02x%02x%02x'));
    $linear = array_map(fn ($value) => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4, $channels);
    $luminance = $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
    $font = \App\Models\User::fontOptions()[auth()->user()->font_family ?? 'system']['css'] ?? \App\Models\User::fontOptions()['system']['css'];
    $fontScale = max(14, min(20, auth()->user()->font_size ?? 16)) / 16;
@endphp
<style>:root { --app-font: {!! $font !!}; --font-scale: {{ $fontScale }}; --primary: {{ $theme }}; --on-primary: {{ $luminance > 0.179 ? '#111827' : '#ffffff' }}; }</style>
</head>
<body data-menu-user="{{ auth()->id() }}">
<script type="application/json" id="modal-old-input">{!! \Illuminate\Support\Js::encode(collect(session()->getOldInput())->except(['password', 'password_confirmation', '_token'])->all()) !!}</script>
<div class="sidebar-overlay" data-close-menu></div>
<aside class="sidebar" id="sidebar">
<a href="{{ route(auth()->user()->landingRoute()) }}" class="brand"><span class="brand-mark">b<span>du</span><i></i></span><span><strong>BDU Media</strong><small>ĐIỀU PHỐI TRUYỀN THÔNG</small></span></a>
<span class="nav-caption">KHÔNG GIAN QUẢN LÝ</span>
<nav aria-label="Điều hướng chính">
@foreach([['dashboard','dashboard','Tổng quan truyền thông'],['requests.index','requests','Sự kiện phòng ban'],['calendar','calendar','Lịch sự kiện'],['approvals','check','Chờ VP duyệt'],['reports','chart','Báo cáo & thống kê'],['library','folder','Kho truyền thông']] as [$route,$icon,$label])
@if(auth()->user()->canAccess($route==='requests.index' ? 'requests' : $route))<a class="nav-link {{ request()->routeIs($route === 'requests.index' ? 'requests.*' : $route) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>@endif
@endforeach
@if(auth()->user()->isAdmin())
<span class="nav-caption">TỔ CHỨC & HỆ THỐNG</span>
<a class="nav-link {{ request()->routeIs('departments*') ? 'active' : '' }}" href="{{ route('departments') }}"><x-icon name="building"/><span>Phòng ban & đơn vị</span></a>
@endif
@if(auth()->user()->isAdmin())
<a class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}" href="{{ route('users') }}"><x-icon name="users"/><span>Quản lý tài khoản</span></a>
<a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><x-icon name="check"/><span>Quản lý vai trò</span></a>
@endif
<a class="nav-link {{ request()->routeIs('settings*') ? 'active' : '' }}" href="{{ route('settings') }}"><x-icon name="settings"/><span>Cài đặt giao diện</span></a>
<a class="nav-link {{ request()->routeIs('help') ? 'active' : '' }}" href="{{ route('help') }}"><x-icon name="folder"/><span>Hướng dẫn & phân quyền</span></a>
</nav>
<div class="sidebar-bottom"><div class="help-card"><span class="help-symbol">✦</span><strong>Một đầu mối. Một quy trình.</strong><p>Kết nối đơn vị, điều phối công việc và theo dõi từng bước thực hiện.</p><a href="{{ route('calendar') }}">Xem kế hoạch tuần <x-icon name="arrow" size="16"/></a></div><small>BDU Media · Hệ thống nội bộ</small></div>
</aside>
<div class="main-shell">
<header class="topbar"><div class="topbar-left"><button class="icon-button mobile-menu" data-toggle-menu aria-label="Mở hoặc ẩn menu" aria-controls="sidebar" aria-expanded="true"><span data-menu-pinned-icon><x-icon name="sidebar"/></span><span data-menu-drawer-icon hidden><x-icon name="menu"/></span></button><div class="topbar-heading"><strong class="topbar-title">@yield('title', 'Tổng quan')</strong><nav class="header-breadcrumb" aria-label="Đường dẫn"><a href="{{ route(auth()->user()->landingRoute()) }}" aria-label="Trang tổng quan"><x-icon name="home" size="14"/><span>Tổng quan</span></a><span aria-hidden="true">›</span><span aria-current="page">@yield('title', 'Tổng quan')</span></nav></div></div><div class="topbar-right"><span class="today">{{ now()->format('d/m/Y') }}</span><a class="icon-button notification-button" href="{{ route('notifications') }}" aria-label="Thông báo"><x-icon name="bell"/>@if(auth()->user()->visibleNotifications()->whereNull('read_at')->exists())<i></i>@endif</a><span class="topbar-divider"></span><details class="profile-menu"><summary><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="profile-name"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span><span>⌄</span></summary><div class="profile-dropdown"><a href="{{ route('settings') }}">Cài đặt giao diện</a><form action="{{ route('logout') }}" method="post">@csrf<button type="submit"><x-icon name="logout" size="16"/> Đăng xuất</button></form></div></details></div></header>
<main class="content">
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer"><span>{{ \App\Models\Setting::valueOf('organization_name', 'BDU Media') }}</span></footer>
</main>
</div>
<div class="page-loading" data-page-loading data-loading-style="{{ auth()->user()->loading_style ?? 'center' }}" hidden><div class="page-loading-bar"></div><div class="page-loading-label" role="status" aria-live="polite"><span class="page-loading-spinner" aria-hidden="true"></span><span data-loading-label>Đang tải…</span></div></div>
</body></html>
