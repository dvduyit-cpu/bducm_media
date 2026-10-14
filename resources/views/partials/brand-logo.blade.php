@if($logoUrl = \App\Models\Setting::logoUrl())
<img class="brand-logo" src="{{ $logoUrl }}" alt="Logo {{ \App\Models\Setting::softwareName() }}" width="48" height="48">
@else
<span class="brand-mark">b<span>du</span><i></i></span>
@endif
