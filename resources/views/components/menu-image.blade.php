@props(['src' => null, 'name' => 'Menu'])
<span {{ $attributes->class(['relative grid overflow-hidden place-items-center bg-slate-100 text-slate-400']) }}>
    @if ($src)<img src="{{ $src }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy" data-fallback-image>@endif
    <span data-image-placeholder @if ($src) hidden @endif aria-hidden="true"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg></span>
</span>
