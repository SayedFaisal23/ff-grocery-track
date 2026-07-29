@props(['sort', 'label', 'activeSort' => null, 'alignment' => 'left'])

@php
    $ascendingSort = "{$sort}_asc";
    $descendingSort = "{$sort}_desc";
    $nextSort = match ($activeSort) {
        $ascendingSort => $descendingSort,
        $descendingSort => null,
        default => $ascendingSort,
    };
    $query = array_filter(
        request()->only(['carian', 'kategori']),
        fn ($value) => $value !== null && $value !== ''
    );

    if ($nextSort) {
        $query['sort'] = $nextSort;
    }

    $ariaSort = match ($activeSort) {
        $ascendingSort => 'ascending',
        $descendingSort => 'descending',
        default => 'none',
    };
    $nextLabel = match ($nextSort) {
        $ascendingSort => 'menaik',
        $descendingSort => 'menurun',
        default => 'susunan asal',
    };
@endphp

<th class="sortable-table-header" aria-sort="{{ $ariaSort }}">
    <a
        href="{{ route('inventori.index', $query) }}"
        class="sortable-table-header-link {{ $ariaSort !== 'none' ? 'is-active' : '' }} {{ $alignment === 'center' ? 'is-centered' : '' }}"
        title="Susun {{ $label }} {{ $nextLabel }}"
    >
        <span>{{ $label }}</span>
        @if($ariaSort === 'ascending')
            <i class="fa-solid fa-sort-up" aria-hidden="true"></i>
        @elseif($ariaSort === 'descending')
            <i class="fa-solid fa-sort-down" aria-hidden="true"></i>
        @else
            <i class="fa-solid fa-sort" aria-hidden="true"></i>
        @endif
    </a>
</th>
