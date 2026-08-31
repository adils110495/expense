@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination">
        <p>
            Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}
            of {{ $paginator->total() }}
        </p>

        <div class="relative inline-flex">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true"><span aria-hidden="true">&lsaquo;</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">&lsaquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true"><span>{{ $element }}</span></span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"><span>{{ $page }}</span></span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">&rsaquo;</a>
            @else
                <span aria-disabled="true"><span aria-hidden="true">&rsaquo;</span></span>
            @endif
        </div>
    </nav>
@else
    <p>Showing all {{ $paginator->total() }} record(s)</p>
@endif
