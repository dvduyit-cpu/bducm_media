<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<title>Đăng nhập · BDU Media</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</head>
<body class="auth-login">
<div class="auth-shell">
<aside class="auth-story">
<a class="brand" href="{{ route('login') }}"><span class="brand-mark">b<span>du</span><i></i></span><span><strong>BDU Media</strong><small>ĐIỀU PHỐI TRUYỀN THÔNG</small></span></a>
<div class="auth-story-copy"><h1>Chào mừng trở lại!</h1><p>Kết nối cùng phòng ban, phối hợp công việc và cập nhật sự kiện trong một không gian chung.</p><a class="auth-outline-button" href="#login-form">Bắt đầu công việc <x-icon name="arrow" size="17"/></a></div>
<div class="auth-organization">{{ \App\Models\Setting::valueOf('organization_name','Phân hiệu Trường Đại học Bình Dương') }}</div>
</aside>
<main class="auth-entry">
<div class="auth-welcome"><h2>Xin chào!</h2><p>Đăng nhập để tiếp tục công việc của bạn.</p></div>
<form method="post" action="{{ route('login.submit') }}" class="auth-login-form" id="login-form">@csrf
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif
<label><span class="auth-field-label">Email</span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Email của bạn" maxlength="255"></label>
<label><span class="auth-field-label">Mật khẩu</span><input type="password" name="password" required autocomplete="current-password" placeholder="Mật khẩu" maxlength="128"></label>
<div class="auth-options"><label class="check-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Ghi nhớ đăng nhập</label><span>Hỗ trợ: VP BGĐ</span></div>
<button type="submit" class="button primary">Đăng nhập <x-icon name="arrow" size="18"/></button>
<div class="auth-register-link">Chưa có tài khoản? Liên hệ admin tổng để được cấp.</div>
</form>
<p class="auth-support">Cần đặt lại mật khẩu? Liên hệ admin tổng.</p>
</main>
</div>
<footer class="auth-footer">BDU Media · Quản lý tập trung, phối hợp hiệu quả</footer>
<div class="page-loading" data-page-loading hidden><div class="page-loading-bar"></div><div class="page-loading-label" role="status" aria-live="polite"><span class="page-loading-spinner" aria-hidden="true"></span><span data-loading-label>Đang xử lý…</span></div></div>
</body></html>
