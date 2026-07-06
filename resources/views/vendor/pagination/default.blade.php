@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Paginacao">
        <div class="pager-summary">
            {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </div>
        <div class="pager-actions">
            @if ($paginator->onFirstPage())
                <span class="pager-btn disabled">Anterior</span>
            @else
                <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pager-btn disabled">...</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pager-btn active">{{ $page }}</span>
                        @elseif ($page === 1 || $page === $paginator->lastPage() || abs($page - $paginator->currentPage()) <= 1)
                            <a class="pager-btn" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Proxima</a>
            @else
                <span class="pager-btn disabled">Proxima</span>
            @endif
        </div>
    </nav>
@endif
