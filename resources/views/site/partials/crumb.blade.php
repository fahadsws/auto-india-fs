{{-- Slim breadcrumb that replaces the big page-header banner. The page's <h1> is the last crumb, so there is still exactly one h1.
     $title (string), $trail = [[label, url], ...] between Home and the title, $tag = optional small status text. --}}
<nav class="crumb" aria-label="Breadcrumb">
  <div class="w">
    <ol>
      <li><a href="{{ url('/') }}">Home</a></li>
      @foreach (($trail ?? []) as [$label, $href])
        <li><a href="{{ $href }}">{{ $label }}</a></li>
      @endforeach
      <li aria-current="page"><h1>{{ $title }}</h1></li>
    </ol>
    @if (! empty($tag))<span class="crumb-tag">{{ $tag }}</span>@endif
  </div>
</nav>
