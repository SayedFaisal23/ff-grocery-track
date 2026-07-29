@props(['kategori'])

@if($kategori)
    <span
        {{ $attributes->class(['badge', 'kategori-pill']) }}
        style="background-color: {{ $kategori->pill_background_color }}; color: {{ $kategori->warna }};"
    >{{ $kategori->nama }}</span>
@endif
