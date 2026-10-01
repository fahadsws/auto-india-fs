@if ($paginator->hasPages())
<nav class="pagination-wrap" role="navigation" aria-label="Pagination">
  <ul class="pagination">
    @if ($paginator->onFirstPage())
      <li class="disabled"><span>&lsaquo;</span></li>
    @else
      <li><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo;</a></li>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))<li class="disabled"><span>{{ $element }}</span></li>@endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())<li class="active"><span>{{ $page }}</span></li>
          @else<li><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>@endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <li><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">&rsaquo;</a></li>
    @else
      <li class="disabled"><span>&rsaquo;</span></li>
    @endif
  </ul>
</nav>
@endif
