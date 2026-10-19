@props(['name', 'label', 'checked' => true])

<label class="flex cursor-pointer items-center gap-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked($checked)
        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-2 focus:ring-emerald-500/40"
    >
    <span class="text-sm text-slate-700">{{ $label }}</span>
</label>
