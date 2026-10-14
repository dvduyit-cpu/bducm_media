<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="menu-user" content="{{ auth()->id() }}"><script src="{{ asset('js/menu-state.js') }}?v={{ filemtime(public_path('js/menu-state.js')) }}"></script>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml"><title>@yield('title', 'Tổng quan') · {{ \App\Models\Setting::softwareName() }}</title>
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
<a href="{{ route(auth()->user()->landingRoute()) }}" class="brand">@include('partials.brand-logo')<span><strong>{{ \App\Models\Setting::softwareName() }}</strong><small>ĐIỀU PHỐI TRUYỀN THÔNG</small></span></a>
<nav aria-label="Điều hướng chính">
@php
    $workLinks = collect([['dashboard','dashboard','Tổng quan truyền thông'],['requests.index','requests','Sự kiện phòng ban'],['calendar','calendar','Lịch sự kiện'],['approvals','check','Chờ VP duyệt'],['reports','chart','Báo cáo & thống kê'],['library','folder','Kho truyền thông']])->filter(fn ($link) => auth()->user()->canAccess($link[0] === 'requests.index' ? 'requests' : $link[0]));
@endphp
@if($workLinks->isNotEmpty())
<section class="nav-group" data-nav-group="work">
<button type="button" class="nav-group-toggle" data-toggle-group="work" aria-expanded="true" aria-controls="menu-group-work"><span>CÔNG VIỆC & SỰ KIỆN</span><span class="nav-group-chevron" aria-hidden="true">⌄</span></button>
<div class="nav-group-links" id="menu-group-work">
@foreach($workLinks as [$route,$icon,$label])
<a class="nav-link {{ request()->routeIs($route === 'requests.index' ? 'requests.*' : $route) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>
@endforeach
</div></section>
@endif
@if(auth()->user()->isAdmin())
<section class="nav-group" data-nav-group="organization">
<button type="button" class="nav-group-toggle" data-toggle-group="organization" aria-expanded="true" aria-controls="menu-group-organization"><span>TỔ CHỨC & TÀI KHOẢN</span><span class="nav-group-chevron" aria-hidden="true">⌄</span></button>
<div class="nav-group-links" id="menu-group-organization">
@foreach([['departments','building','Phòng ban & đơn vị','departments*'],['users','users','Quản lý tài khoản','users*'],['roles.index','check','Quản lý vai trò','roles.*']] as [$route,$icon,$label,$pattern])
<a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>
@endforeach
</div></section>
@endif
<section class="nav-group" data-nav-group="system">
<button type="button" class="nav-group-toggle" data-toggle-group="system" aria-expanded="true" aria-controls="menu-group-system"><span>CÀI ĐẶT & HƯỚNG DẪN</span><span class="nav-group-chevron" aria-hidden="true">⌄</span></button>
<div class="nav-group-links" id="menu-group-system">
<a class="nav-link {{ request()->routeIs('settings*') ? 'active' : '' }}" href="{{ route('settings') }}"><x-icon name="settings"/><span>Cài đặt {{ auth()->user()->isAdmin() ? 'chung & giao diện' : 'giao diện' }}</span></a>
<a class="nav-link {{ request()->routeIs('help') ? 'active' : '' }}" href="{{ route('help') }}"><x-icon name="folder"/><span>Hướng dẫn & phân quyền</span></a>
</div></section>
</nav>
</aside>
<div class="main-shell">
<header class="topbar"><div class="topbar-left"><button class="icon-button mobile-menu" data-toggle-menu aria-label="Mở hoặc ẩn menu" aria-controls="sidebar" aria-expanded="true"><span data-menu-pinned-icon><x-icon name="sidebar"/></span><span data-menu-drawer-icon hidden><x-icon name="menu"/></span></button><div class="topbar-heading"><strong class="topbar-title">@yield('title', 'Tổng quan')</strong><nav class="header-breadcrumb" aria-label="Đường dẫn"><a href="{{ route(auth()->user()->landingRoute()) }}" aria-label="Trang tổng quan"><x-icon name="home" size="14"/><span>Tổng quan</span></a><span aria-hidden="true">›</span><span aria-current="page">@yield('title', 'Tổng quan')</span></nav></div></div><div class="topbar-right"><span class="today">{{ now()->format('d/m/Y') }}</span><a class="icon-button notification-button" href="{{ route('notifications') }}" aria-label="Thông báo"><x-icon name="bell"/>@if(auth()->user()->visibleNotifications()->whereNull('read_at')->exists())<i></i>@endif</a><span class="topbar-divider"></span><details class="profile-menu"><summary><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="profile-name"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span><span>⌄</span></summary><div class="profile-dropdown"><a href="{{ route('settings') }}">Cài đặt giao diện</a><form action="{{ route('logout') }}" method="post">@csrf<button type="submit"><x-icon name="logout" size="16"/> Đăng xuất</button></form></div></details></div></header>
<main class="content">
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer"><span>{{ \App\Models\Setting::footerText() }}</span></footer>
</main>
</div>
<div class="page-loading" data-page-loading data-loading-style="{{ auth()->user()->loading_style ?? 'center' }}" hidden><div class="page-loading-bar"></div><div class="page-loading-label" role="status" aria-live="polite"><span class="page-loading-spinner" aria-hidden="true"></span><span data-loading-label>Đang tải…</span></div></div>
</body></html>
