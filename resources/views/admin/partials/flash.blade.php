{{-- Success/error flashes are shown as toasts (see admin.js). Validation problems stay inline so they can be read and fixed. --}}
@if ($errors->any())
  <div class="alert alert-danger" role="alert"><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif