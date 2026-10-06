<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml"><title>@yield('title', 'Tổng quan') · BDU Media</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@php
    $theme = auth()->user()->theme_color ?? \App\Models\Setting::valueOf('default_color', '#2563eb');
    $theme = preg_match('/^#[0-9a-fA-F]{6}$/', $theme) ? $theme : '#2563eb';
    $channels = array_map(fn ($value) => $value / 255, sscanf($theme, '#%02x%02x%02x'));
    $linear = array_map(fn ($value) => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4, $channels);
    $luminance = $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
@endphp
<style>:root { --primary: {{ $theme }}; --on-primary: {{ $luminance > 0.179 ? '#111827' : '#ffffff' }}; }</style>
</head>
<body>
<div class="sidebar-overlay" data-close-menu></div>
<aside class="sidebar" id="sidebar">
<a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">b<span>du</span><i></i></span><span><strong>BDU Media</strong><small>ĐIỀU PHỐI TRUYỀN THÔNG</small></span></a>
<div class="workspace"><span class="workspace-icon"><x-icon name="building"/></span><div><strong>Không gian làm việc</strong><small>{{ auth()->user()->role->label() }}</small></div><span class="workspace-dot"></span></div>
<span class="nav-caption">KHÔNG GIAN QUẢN LÝ</span>
<nav aria-label="Điều hướng chính">
@foreach([['dashboard','dashboard','Tổng quan'],['requests.index','requests','Yêu cầu truyền thông'],['calendar','calendar','Lịch truyền thông'],['approvals','check','Phê duyệt'],['reports','chart','Báo cáo & thống kê'],['library','folder','Kho truyền thông']] as [$route,$icon,$label])
<a class="nav-link {{ request()->routeIs($route === 'requests.index' ? 'requests.*' : $route) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>
@endforeach
@if(auth()->user()->isLeadership())
<span class="nav-caption">TỔ CHỨC & HỆ THỐNG</span>
<a class="nav-link {{ request()->routeIs('departments*') ? 'active' : '' }}" href="{{ route('departments') }}"><x-icon name="building"/><span>Phòng ban & đơn vị</span></a>
@endif
@if(auth()->user()->isOffice())
<a class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}" href="{{ route('users') }}"><x-icon name="users"/><span>Quản lý tài khoản</span></a>
@endif
<a class="nav-link {{ request()->routeIs('settings*') ? 'active' : '' }}" href="{{ route('settings') }}"><x-icon name="settings"/><span>Cài đặt giao diện</span></a>
</nav>
<div class="sidebar-bottom"><div class="help-card"><span class="help-symbol">✦</span><strong>Một đầu mối. Một quy trình.</strong><p>Kết nối đơn vị, điều phối công việc và theo dõi từng bước thực hiện.</p><a href="{{ route('calendar') }}">Xem kế hoạch tuần <x-icon name="arrow" size="16"/></a></div><small>BDU Media · Hệ thống nội bộ</small></div>
</aside>
<div class="main-shell">
<header class="topbar"><div class="topbar-left"><button class="icon-button mobile-menu" data-toggle-menu aria-label="Mở menu" aria-expanded="false"><x-icon name="menu"/></button><span class="breadcrumb">Không gian quản lý <span>/</span> <strong>@yield('title', 'Tổng quan')</strong></span></div><div class="topbar-right"><span class="today">{{ now()->format('d/m/Y') }}</span><a class="icon-button notification-button" href="{{ route('notifications') }}" aria-label="Thông báo"><x-icon name="bell"/>@if(auth()->user()->unreadNotifications()->exists())<i></i>@endif</a><span class="topbar-divider"></span><details class="profile-menu"><summary><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="profile-name"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->label() }}</small></span><span>⌄</span></summary><div class="profile-dropdown"><a href="{{ route('settings') }}">Cài đặt giao diện</a><form action="{{ route('logout') }}" method="post">@csrf<button type="submit"><x-icon name="logout" size="16"/> Đăng xuất</button></form></div></details></div></header>
<main class="content">
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer"><span>{{ \App\Models\Setting::valueOf('organization_name', 'BDU Media') }}</span><span>Quản lý tập trung · Phối hợp hiệu quả</span></footer>
</main>
</div>
</body></html>
