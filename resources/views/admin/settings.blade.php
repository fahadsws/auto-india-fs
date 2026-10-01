@extends('admin.layout')
@section('title', 'Settings')

@section('content')
<h4 class="mb-4">Settings</h4>
<form method="POST" action="{{ route('admin.settings.update') }}">
  @csrf @method('PUT')
  <div class="row g-4">
    <div class="col-lg-3">
      <div class="nav flex-column nav-pills" role="tablist">
        @foreach ($groups as $name => [$icon])
          <button type="button" class="nav-link text-start {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#g{{ $loop->index }}"><i class="ti {{ $icon }} me-2"></i>{{ $name }}</button>
        @endforeach
      </div>
    </div>
    <div class="col-lg-9">
      <div class="tab-content p-0 shadow-none bg-transparent">
        @foreach ($groups as $name => [$icon, $fields])
          <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="g{{ $loop->index }}">
            <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ $name }}</h5></div><div class="card-body">
              @foreach ($fields as [$key, $label, $type, $help])
                @php $name_ = str_replace('.', '__', $key); $val = old($name_, $values[$key]); @endphp
                <div class="mb-3">
                  @if ($type === 'bool')
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $name_ }}" value="1" id="{{ $name_ }}" @checked((string) $val === '1' || ($val === null && in_array($key, ['assistant.enabled', 'assistant.require_lead', 'assistant.otp_required', 'news.auto_publish', 'youtube.auto_attach']) || ($val === null && str_starts_with($key, 'cron.enabled'))))><label class="form-check-label" for="{{ $name_ }}">{{ $label }}</label></div>
                  @else
                    <label class="form-label" for="{{ $name_ }}">{{ $label }}</label>
                    @if ($type === 'textarea')<textarea class="form-control" rows="3" name="{{ $name_ }}" id="{{ $name_ }}">{{ $val }}</textarea>
                    @elseif ($type === 'secret')<input type="password" class="form-control" name="{{ $name_ }}" id="{{ $name_ }}" value="{{ $val }}" autocomplete="new-password" placeholder="Not set">
                    @else<input type="{{ $type === 'number' ? 'number' : 'text' }}" class="form-control" name="{{ $name_ }}" id="{{ $name_ }}" value="{{ $val }}">@endif
                  @endif
                  @if ($help)<div class="form-text">{{ $help }}</div>@endif
                </div>
              @endforeach
            </div></div>
          </div>
        @endforeach
      </div>
      <div class="mt-4"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save settings</button></div>
    </div>
  </div>
</form>
@endsection
