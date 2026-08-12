@props(['calendarMonth', 'calendarWeeks', 'selectedWeeks', 'selectedType' => null, 'selectedStatus' => null])

@php
    $selectedWeekLookup = array_fill_keys($selectedWeeks, true);
    $previousMonth = $calendarMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $calendarMonth->copy()->addMonth()->format('Y-m');
    $preservedFilters = array_filter([
        'type' => $selectedType,
        'status' => $selectedStatus,
    ], fn ($value) => $value !== null && $value !== '');
    $filtersActive = $selectedWeeks !== [] || $preservedFilters !== [];
@endphp

<section class="card claims-week-filter" aria-labelledby="claims-week-filter-title">
    <header class="claims-week-filter-header">
        <div>
            <h2 id="claims-week-filter-title"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter requests</h2>
            <p>Choose one or more weeks, a type, or a status. Results update immediately.</p>
        </div>
        @if($filtersActive)
            <a href="{{ route('tuntutan.index', ['month' => $calendarMonth->format('Y-m')]) }}" class="btn btn-secondary btn-sm claims-week-filter-clear">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear all
            </a>
        @endif
    </header>

    <form action="{{ route('tuntutan.index') }}" method="GET" class="claims-select-filters">
        <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
        @foreach($selectedWeeks as $selectedWeek)
            <input type="hidden" name="weeks[]" value="{{ $selectedWeek }}">
        @endforeach
        <label>
            <span>Type</span>
            <select name="type" onchange="this.form.submit()" aria-label="Filter purchase requests by type">
                <option value="">All types</option>
                <option value="Pantry" @selected($selectedType === 'Pantry')>Pantry</option>
                <option value="General" @selected($selectedType === 'General')>General</option>
                <option value="Lunch" @selected($selectedType === 'Lunch')>Lunch</option>
            </select>
        </label>
        <label>
            <span>Status</span>
            <select name="status" onchange="this.form.submit()" aria-label="Filter purchase requests by status">
                <option value="">All statuses</option>
                <option value="submitted" @selected($selectedStatus === 'submitted')>Submitted</option>
                <option value="requester_document_required" @selected($selectedStatus === 'requester_document_required')>Approved - requester document required</option>
                <option value="payment_proof_required" @selected($selectedStatus === 'payment_proof_required')>Approved - payment proof required</option>
                <option value="receipt_required" @selected($selectedStatus === 'receipt_required')>Approved — receipt required</option>
                <option value="completed" @selected($selectedStatus === 'completed')>Completed</option>
                <option value="rejected" @selected($selectedStatus === 'rejected')>Rejected</option>
            </select>
        </label>
        <noscript><button type="submit" class="btn btn-secondary btn-sm">Apply filters</button></noscript>
    </form>

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
                    @foreach($preservedFilters as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
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
                <a href="{{ route('tuntutan.index', [...$preservedFilters, 'month' => $previousMonth, 'weeks' => $selectedWeeks]) }}" class="btn btn-secondary btn-sm" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </a>
                <strong>{{ $calendarMonth->format('F Y') }}</strong>
                <a href="{{ route('tuntutan.index', [...$preservedFilters, 'month' => $nextMonth, 'weeks' => $selectedWeeks]) }}" class="btn btn-secondary btn-sm" aria-label="Next month">
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
                            @foreach($preservedFilters as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
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
