{{-- DataTable shell. $columns = [['title'=>..., 'orderable'=>bool, 'class'=>..], ...], $url, $order, $length.
     If $bulk (shared by the controller) is set, a checkbox column and bulk-action bar are added. --}}
@php $hasBulk = ! empty($bulk); @endphp
@if ($hasBulk)
  @php
    $icons = ['enable' => 'ti-circle-check', 'publish' => 'ti-world-upload', 'draft' => 'ti-file-pencil', 'category' => 'ti-tag', 'delete' => 'ti-trash', 'activate' => 'ti-circle-check', 'pause' => 'ti-player-pause',
              'hide' => 'ti-eye-off', 'show' => 'ti-eye', 'sold' => 'ti-currency-rupee', 'status' => 'ti-flag', 'assign' => 'ti-user-plus', 'disable' => 'ti-circle-x', 'role' => 'ti-shield-check', 'refresh' => 'ti-sparkles', 'ai_seo' => 'ti-sparkles'];
  @endphp
  <div class="dt-bulkbar d-none px-4 py-2 border-bottom" data-url="{{ $bulkUrl }}">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <span class="dt-bulk-count"><i class="ti ti-checks me-1"></i><b class="dt-count">0</b> selected</span>
      <div class="dropdown">
        <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false"><i class="ti ti-list-check me-1"></i>Bulk actions</button>
        <ul class="dropdown-menu dt-bulk-menu">
          @foreach ($bulk as $i => $b)
            @if ($b['danger'] && $i > 0 && ! $bulk[$i - 1]['danger'])<li><hr class="dropdown-divider"></li>@endif
            <li><a href="#" class="dropdown-item dt-bulk-item {{ $b['danger'] ? 'text-danger' : '' }}" data-key="{{ $b['key'] }}" data-label="{{ $b['label'] }}" data-danger="{{ $b['danger'] ? 1 : 0 }}" data-confirm="{{ $b['confirm'] }}" data-options='@json($b['options'])'>
              <i class="ti {{ $icons[$b['key']] ?? 'ti-point' }} me-2"></i>{{ $b['label'] }}</a></li>
          @endforeach
        </ul>
      </div>
      <button type="button" class="btn btn-sm btn-text-secondary dt-bulk-clear"><i class="ti ti-x me-1"></i>Clear selection</button>
      <span class="dt-selectall small d-none"></span>
    </div>
  </div>
@endif
<div class="table-responsive">
  <table class="table table-hover dt w-100" data-dt-url="{{ $url }}" data-dt-cols='@json($columns)' data-dt-order='@json($order ?? [[0, "desc"]])' @isset($length) data-dt-length="{{ $length }}" @endisset
         @if ($hasBulk) data-bulk="1" @if (! empty($bulkAll)) data-bulk-all="1" @endif @endif>
    <thead><tr>@if ($hasBulk)<th class="dt-check"><input type="checkbox" class="form-check-input dt-all" aria-label="Select all on this page"></th>@endif
      @foreach ($columns as $c)<th class="{{ $c['class'] ?? '' }}">{{ $c['title'] }}</th>@endforeach</tr></thead>
  </table>
</div>