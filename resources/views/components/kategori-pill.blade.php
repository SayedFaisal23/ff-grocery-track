@props(['kategori'])

@if($kategori)
    <span
        {{ $attributes->class(['badge', 'kategori-pill']) }}
        style="--kategori-color: {{ $kategori->warna }}; background-color: {{ $kategori->pill_background_color }};"
    >{{ $kategori->nama }}</span>
@endif
