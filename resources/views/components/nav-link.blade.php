@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge([
        'class' => $active
            ? 'flex items-center gap-3 rounded-lg bg-white/10 px-3 py-2.5 text-sm font-medium text-white ring-1 ring-inset ring-white/10'
            : 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white',
    ]) }}
>{{ $slot }}</a>
