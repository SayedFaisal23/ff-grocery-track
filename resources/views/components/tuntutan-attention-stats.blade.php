@props(['cards' => []])

@php
    $cards = is_iterable($cards)
        ? (is_array($cards) ? $cards : iterator_to_array($cards))
        : [];
    $allowedTones = ['warning', 'primary', 'success'];
    $gridClass = count($cards) === 2
        ? 'purchase-request-attention-grid--two'
        : 'purchase-request-attention-grid--three';
@endphp

<section class="purchase-request-attention-grid {{ $gridClass }}" aria-label="Purchase request attention summary">
    @forelse($cards as $card)
        @php
            $card = is_array($card) ? $card : [];
            $title = isset($card['title']) && is_scalar($card['title'])
                ? trim((string) $card['title'])
                : 'Requests';
            $title = $title !== '' ? $title : 'Requests';

            $count = filter_var($card['count'] ?? 0, FILTER_VALIDATE_INT);
            $count = $count === false ? 0 : max(0, $count);

            $tone = isset($card['tone']) && is_string($card['tone'])
                ? trim($card['tone'])
                : 'warning';
            $tone = in_array($tone, $allowedTones, true) ? $tone : 'warning';

            $icon = isset($card['icon']) && is_string($card['icon'])
                ? trim($card['icon'])
                : 'fa-solid fa-circle-info';
            $icon = $icon !== '' && preg_match('/^[A-Za-z0-9 _-]+$/', $icon) === 1
                ? $icon
                : 'fa-solid fa-circle-info';

            $claims = [];
            $rawClaims = isset($card['claims']) && is_iterable($card['claims'])
                ? $card['claims']
                : [];

            foreach ($rawClaims as $claim) {
                if (! is_array($claim)) {
                    continue;
                }

                $claimId = filter_var($claim['id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $claimLabel = isset($claim['label']) && is_scalar($claim['label'])
                    ? trim((string) $claim['label'])
                    : '';

                if ($claimId === false || $claimLabel === '') {
                    continue;
                }

                $claims[] = [
                    'id' => (int) $claimId,
                    'label' => $claimLabel,
                ];
            }

            $visibleClaims = array_slice($claims, 0, 3);
            $remainingClaims = array_slice($claims, 3);
        @endphp

        <article class="purchase-request-attention-card" data-attention-tone="{{ $tone }}">
            <header class="purchase-request-attention-card-header">
                <span class="purchase-request-attention-card-icon" aria-hidden="true">
                    <i class="{{ $icon }}"></i>
                </span>
                <h2 class="purchase-request-attention-card-title">{{ $title }}</h2>
            </header>

            <p class="purchase-request-attention-card-value">
                <span class="sr-only">Requests requiring attention: </span>{{ number_format($count) }}
            </p>

            <div class="purchase-request-attention-card-content">
                @if($claims !== [])
                    <ul class="purchase-request-attention-card-claims" aria-label="{{ $title }} requests">
                        @foreach($visibleClaims as $claim)
                            <li>
                                <a
                                    href="#claim-{{ $claim['id'] }}"
                                    class="purchase-request-attention-link"
                                    data-claim-focus-link
                                    data-claim-focus-tone="{{ $tone }}"
                                    aria-controls="claim-{{ $claim['id'] }}"
                                    aria-label="Focus Claim #{{ $claim['id'] }}: {{ $claim['label'] }}"
                                >
                                    <span class="purchase-request-attention-link-label">{{ $claim['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if($remainingClaims !== [])
                        @php($remainingCount = count($remainingClaims))
                        <details class="purchase-request-attention-card-more">
                            <summary>
                                +{{ number_format($remainingCount) }} more
                            </summary>
                            <ul class="purchase-request-attention-card-claims" aria-label="Additional {{ $title }} requests">
                                @foreach($remainingClaims as $claim)
                                    <li>
                                        <a
                                            href="#claim-{{ $claim['id'] }}"
                                            class="purchase-request-attention-link"
                                            data-claim-focus-link
                                            data-claim-focus-tone="{{ $tone }}"
                                            aria-controls="claim-{{ $claim['id'] }}"
                                            aria-label="Focus Claim #{{ $claim['id'] }}: {{ $claim['label'] }}"
                                        >
                                            <span class="purchase-request-attention-link-label">{{ $claim['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @else
                    <p class="purchase-request-attention-card-empty">No matching requests require attention.</p>
                @endif
            </div>
        </article>
    @empty
        <p class="purchase-request-attention-grid-empty">No purchase-request attention categories are available.</p>
    @endforelse
</section>
