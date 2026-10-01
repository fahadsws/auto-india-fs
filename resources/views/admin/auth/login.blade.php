<!DOCTYPE html>
<html lang="en" class="light-style" dir="ltr" data-theme="theme-red" data-assets-path="{{ asset('vuexy') }}/" data-template="vertical-menu-template" data-style="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Sign in · {{ config('app.name') }} Admin</title>
  <link rel="icon" href="{{ asset('vuexy/img/favicon/favicon.ico') }}">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('vuexy/vendor/fonts/tabler-icons.full.css') }}">
  <link rel="stylesheet" href="{{ asset('vuexy/vendor/css/rtl/core.css') }}">
  <link rel="stylesheet" href="{{ asset('vuexy/vendor/css/rtl/theme-red.css') }}">
  <link rel="stylesheet" href="{{ asset('vuexy/css/demo.css') }}">
  <link rel="stylesheet" href="{{ asset('vuexy/vendor/css/pages/page-auth.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="auth-bg">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner py-6">
      <div class="card">
        <div class="card-body">
          <div class="app-brand justify-content-center mb-4">
            <span class="app-brand-link"><i class="ti ti-car" style="font-size:38px;color:var(--bs-primary)"></i>
              <span class="app-brand-text demo text-heading fw-bold ms-2">Automobil <span style="color:var(--bs-primary)">India</span></span></span>
          </div>
          <h4 class="mb-1">Welcome back 👋</h4>
          <p class="mb-4 text-muted">Sign in to manage your AI-powered site.</p>

          @if ($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

          <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com">
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
            </div>
            <div class="mb-4 form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Remember me</label></div>
            <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
          </form>
          <p class="text-center mt-4 mb-0"><a href="{{ route('home') }}"><i class="ti ti-arrow-left ti-xs"></i> Back to website</a></p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
