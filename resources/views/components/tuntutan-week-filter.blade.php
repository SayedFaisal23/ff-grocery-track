@props(['calendarMonth', 'calendarWeeks', 'selectedWeeks'])

@php
    $selectedWeekLookup = array_fill_keys($selectedWeeks, true);
    $previousMonth = $calendarMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $calendarMonth->copy()->addMonth()->format('Y-m');
@endphp

<section class="card claims-week-filter" aria-labelledby="claims-week-filter-title">
    <header class="claims-week-filter-header">
        <div>
            <h2 id="claims-week-filter-title"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Filter weeks</h2>
            <p>Choose one or more weeks. Results update immediately.</p>
        </div>
        @if($selectedWeeks !== [])
            <a href="{{ route('tuntutan.index', ['month' => $calendarMonth->format('Y-m')]) }}" class="btn btn-secondary btn-sm claims-week-filter-clear">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear
            </a>
        @endif
    </header>

    @if($selectedWeeks !== [])
        <div class="claims-selected-weeks" aria-label="Selected weeks">
            @foreach($selectedWeeks as $selectedWeek)
                @php
                    $remainingWeeks = array_values(array_filter(
                        $selectedWeeks,
                        fn ($week) => $week !== $selectedWeek,
                    ));
                @endphp
                <form action="{{ route('tuntutan.index') }}" method="GET" class="claims-selected-week-form">
                    <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                    @foreach($remainingWeeks as $remainingWeek)
                        <input type="hidden" name="weeks[]" value="{{ $remainingWeek }}">
                    @endforeach
                    <button type="submit" class="claims-selected-week" aria-label="Remove {{ $selectedWeek }} from the filter">
                        <span>Week {{ substr($selectedWeek, -2) }}, {{ substr($selectedWeek, 0, 4) }}</span>
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <details class="claims-calendar-disclosure">
        <summary>
            <span><i class="fa-solid fa-calendar-week" aria-hidden="true"></i> Week calendar — {{ $calendarMonth->format('F Y') }}</span>
            <i class="fa-solid fa-chevron-down claims-calendar-disclosure-icon" aria-hidden="true"></i>
        </summary>
        <div class="claims-calendar-panel">
    <nav class="claims-calendar-navigation" aria-label="Calendar month">
        <a href="{{ route('tuntutan.index', ['month' => $previousMonth, 'weeks' => $selectedWeeks]) }}" class="btn btn-secondary btn-sm" aria-label="Previous month">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </a>
        <strong>{{ $calendarMonth->format('F Y') }}</strong>
        <a href="{{ route('tuntutan.index', ['month' => $nextMonth, 'weeks' => $selectedWeeks]) }}" class="btn btn-secondary btn-sm" aria-label="Next month">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </a>
    </nav>

    <div class="claims-calendar" role="group" aria-label="Week calendar for {{ $calendarMonth->format('F Y') }}">
        <div class="claims-calendar-weekdays" aria-hidden="true">
            <span>Week</span>
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
            <span>Sun</span>
        </div>

        <div class="claims-calendar-weeks">
            @foreach($calendarWeeks as $calendarWeek)
                @php
                    $isSelected = isset($selectedWeekLookup[$calendarWeek['value']]);
                    $nextWeeks = $isSelected
                        ? array_values(array_filter($selectedWeeks, fn ($week) => $week !== $calendarWeek['value']))
                        : [...$selectedWeeks, $calendarWeek['value']];
                    $action = $isSelected ? 'Deselect' : 'Select';
                @endphp
                <form action="{{ route('tuntutan.index') }}" method="GET" class="claims-calendar-week-form">
                    <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                    @foreach($nextWeeks as $nextWeek)
                        <input type="hidden" name="weeks[]" value="{{ $nextWeek }}">
                    @endforeach
                    <button
                        type="submit"
                        class="claims-calendar-week {{ $isSelected ? 'is-selected' : '' }}"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        aria-label="{{ $action }} {{ $calendarWeek['value'] }}, {{ $calendarWeek['start']->format('j M Y') }} to {{ $calendarWeek['end']->format('j M Y') }}"
                    >
                        <span class="claims-calendar-week-number">W{{ str_pad((string) $calendarWeek['number'], 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="claims-calendar-week-range">{{ $calendarWeek['start']->format('d M') }} – {{ $calendarWeek['end']->format('d M') }}</span>
                        @foreach($calendarWeek['days'] as $day)
                            <span class="claims-calendar-day {{ ! $day->isSameMonth($calendarMonth) ? 'is-outside' : '' }}">
                                <span>{{ $day->format('d') }}</span>
                            </span>
                        @endforeach
                    </button>
                </form>
            @endforeach
        </div>
    </div>
        </div>
    </details>
</section>
