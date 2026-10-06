@props(['name', 'size' => 20])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('dashboard')<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2m4 0h2M8 18h2"/>@break
@case('check')<path d="m8 12 3 3 6-7"/><rect x="3" y="3" width="18" height="18" rx="4"/>@break
@case('chart')<path d="M4 3v18h17M8 17v-4m5 4V8m5 9V5"/>@break
@case('folder')<path d="M3 7V5a2 2 0 0 1 2-2h5l3 4h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m1 4a5 5 0 0 1 4 5"/>@break
@case('building')<path d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M10 21v-5h4v5"/>@break
@case('settings')<path d="m9 3-1 3-3 1-2 4 2 2v4l4 3 3-1 3 1 4-3v-4l2-2-2-4-3-1-1-3Z"/><circle cx="12" cy="12" r="3"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('sidebar')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>@break
@case('home')<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
@case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>@break
@case('logout')<path d="M9 4H4v16h5m1-8h11m-4-4 4 4-4 4"/>@break
@case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>@break
@default<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>
@endswitch
</svg>