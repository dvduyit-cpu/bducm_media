@if($paginator->hasPages())
<nav aria-label="Phân trang">
@if($paginator->onFirstPage())<span class="muted">← Trang trước</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Trang trước</a>@endif
<span class="muted">Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Trang sau →</a>@else<span class="muted">Trang sau →</span>@endif
</nav>
@endif
