@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Purchase Requests</h1>
        <p>View and manage Pantry, General, and Lunch claims.</p>
    </div>
    @role('Stocker')
        <a href="{{ route('tuntutan.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>Submit a request</span>
        </a>
    @endrole
</div>

<x-tuntutan-week-filter
    :calendar-month="$calendarMonth"
    :calendar-weeks="$calendarWeeks"
    :selected-weeks="$selectedWeeks"
    :selected-type="$selectedType"
    :selected-status="$selectedStatus"
/>

@forelse($claimsGrouped as $week => $claims)
    @php
        $startOfWeek = '-';
        $endOfWeek = '-';

        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches)) {
            $weekDate = \Carbon\Carbon::now()->setISODate((int) $matches[1], (int) $matches[2]);
            $startOfWeek = $weekDate->startOfWeek()->format('d/m/Y');
            $endOfWeek = $weekDate->endOfWeek()->format('d/m/Y');
        }
    @endphp
    <section class="card claims-week-card">
        <header class="card-header-flex claims-week-header">
            <div>
                <h2>Week: {{ $week }}</h2>
                <p>Dates: {{ $startOfWeek }} to {{ $endOfWeek }}</p>
            </div>
        </header>

        <div class="table-wrapper claims-desktop-table">
            <table class="custom-table claims-table {{ Auth::user()->hasRole('Superadmin') ? 'claims-table-with-actions' : '' }}">
                <colgroup>
                    <col class="claims-requester-column">
                    <col class="claims-type-column">
                    <col class="claims-details-column">
                    <col class="claims-dates-column">
                    <col class="claims-amount-column">
                    <col class="claims-status-column">
                    @role('Superadmin')
                        <col class="claims-actions-column">
                    @endrole
                </colgroup>
                <thead>
                    <tr>
                        <th>Requester</th>
                        <th>Type</th>
                        <th class="claim-details-header">Request details</th>
                        <th>Dates</th>
                        <th>Amount</th>
                        <th class="claim-status-cell">Status</th>
                        @role('Superadmin')
                            <th class="claim-actions-cell">Superadmin action</th>
                        @endrole
                    </tr>
                </thead>
                <tbody>
                    @foreach($claims as $claim)
                        @php
                            $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
                            $requestDate = $claim->request_date ?? $claim->tarikh_beli;
                        @endphp
                        <tr>
                            <td>
                                <div class="table-item-info">
                                    <strong>{{ $claim->requestor_name ?: $claim->user->name }}</strong>
                                    <div class="table-secondary-text">{{ $claim->user->email }}</div>
                                </div>
                            </td>
                            <td><x-tuntutan-type-badge :claim="$claim" /></td>
                            <td class="claim-details-cell"><x-tuntutan-details :claim="$claim" context="desktop" /></td>
                            <td class="claim-dates-cell">
                                @if($claim->isPurchaseRequest())
                                    <div><span>Requested</span><strong>{{ $requestDate?->format('d/m/Y') ?? '-' }}</strong></div>
                                    <div><span>Received</span><strong>{{ $claim->date_receive?->format('d/m/Y') ?? '-' }}</strong></div>
                                @else
                                    <strong>{{ $claim->tarikh_beli?->format('d/m/Y') ?? '-' }}</strong>
                                @endif
                            </td>
                            <td class="claim-value-cell"><strong>RM {{ number_format($amount, 2) }}</strong></td>
                            <td class="claim-status-cell"><x-tuntutan-status :claim="$claim" /></td>
                            @role('Superadmin')
                                <td class="claim-actions-cell"><x-tuntutan-actions :claim="$claim" /></td>
                            @endrole
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="claims-mobile-list">
            @foreach($claims as $claim)
                @php($modalId = "claim-mobile-modal-{$claim->id}")
                <article class="claim-mobile-card">
                    <x-tuntutan-mobile-summary :claim="$claim" :modal-id="$modalId" />
                    <x-tuntutan-mobile-dialog :claim="$claim" :modal-id="$modalId" />
                </article>
            @endforeach
        </div>
    </section>
@empty
    <div class="card claims-empty-state">
        <i class="fa-solid fa-file-circle-plus"></i>
        No purchase requests found.
    </div>
@endforelse

<script>
    (() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const desktopReviewedClaims = new Set();
        const claimReviewRequestVersions = new Map();

        const malaysiaDateTime = (value) => {
            if (typeof value !== 'string' || value === '') {
                return null;
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Kuala_Lumpur',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            }).format(date);
        };

        const reviewTimestampFromResponse = (payload) => {
            const formattedTimestamp = payload?.claim_details_viewed_at_display
                ?? payload?.formatted_claim_details_viewed_at
                ?? payload?.claim_details_viewed_at_formatted;

            return typeof formattedTimestamp === 'string'
                ? formattedTimestamp
                : malaysiaDateTime(payload?.claim_details_viewed_at);
        };

        const updateClaimReviewTimestamp = (claimId, timestamp) => {
            if (!timestamp) {
                return;
            }

            document.querySelectorAll('[data-claim-audit-for]').forEach((audit) => {
                if (audit.dataset.claimAuditFor !== claimId) {
                    return;
                }

                audit.querySelectorAll('[data-claim-details-viewed-at]').forEach((label) => {
                    label.textContent = timestamp;
                });
            });
        };

        const trackClaimDetailsReview = (details) => {
            const url = details?.dataset.claimReviewUrl;
            const claimId = details?.dataset.claimId;

            if (!url || !claimId || !csrfToken) {
                return;
            }

            const requestVersion = (claimReviewRequestVersions.get(claimId) ?? 0) + 1;
            claimReviewRequestVersions.set(claimId, requestVersion);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to record claim review.');
                    }

                    return response.status === 204 ? null : response.json();
                })
                .then((payload) => {
                    if (claimReviewRequestVersions.get(claimId) === requestVersion) {
                        updateClaimReviewTimestamp(claimId, reviewTimestampFromResponse(payload));
                    }
                })
                .catch(() => {
                    // Review tracking must never block use of the claim details UI.
                });
        };

        document.querySelectorAll('[data-claim-modal-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const dialog = document.getElementById(trigger.dataset.claimModalOpen);

                if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
                    return;
                }

                dialog.claimModalTrigger = trigger;
                dialog.showModal();
                dialog.querySelector('[data-claim-modal-close]')?.focus();
                trackClaimDetailsReview(dialog.querySelector('[data-claim-details-review]'));
            });
        });

        document.querySelectorAll('.claim-mobile-modal').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            dialog.addEventListener('close', () => {
                dialog.claimModalTrigger?.focus();
                dialog.claimModalTrigger = null;
            });
        });

        document.querySelectorAll('[data-claim-modal-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('.claim-mobile-modal')?.close());
        });

        document.querySelectorAll('[data-attachment-open-link]').forEach((link) => {
            const label = link.querySelector('[data-attachment-open-label]');
            const status = link.parentElement?.querySelector('[data-attachment-open-status]');
            const originalLabel = label?.textContent?.trim() || 'Supporting document';
            let resetTimer;

            const resetAttachmentLink = () => {
                window.clearTimeout(resetTimer);
                link.classList.remove('is-opening');
                delete link.dataset.attachmentOpening;
                label?.replaceChildren(originalLabel);
                status?.replaceChildren('');
            };

            link.addEventListener('click', () => {
                link.dataset.attachmentOpening = 'true';
                link.classList.add('is-opening');
                label?.replaceChildren('Opening attachment...');
                status?.replaceChildren('Opening attachment in a new tab.');

                // The link opens in a new tab, so restore the in-page control as
                // soon as that navigation has been launched and once focus returns.
                resetTimer = window.setTimeout(resetAttachmentLink, 0);
                window.addEventListener('focus', resetAttachmentLink, { once: true });
            });
        });

        const desktopClaimDetails = Array.from(document.querySelectorAll(
            '[data-claim-details-review][data-claim-details-context="desktop"]'
        ));

        const trackDesktopDetailOnce = (details) => {
            const claimId = details.dataset.claimId;

            if (!claimId || desktopReviewedClaims.has(claimId)) {
                return;
            }

            desktopReviewedClaims.add(claimId);
            trackClaimDetailsReview(details);
        };

        if ('IntersectionObserver' in window) {
            const desktopDetailObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting || entry.intersectionRatio < 0.5) {
                        return;
                    }

                    trackDesktopDetailOnce(entry.target);
                    desktopDetailObserver.unobserve(entry.target);
                });
            }, { threshold: [0, 0.5, 1] });

            desktopClaimDetails.forEach((details) => desktopDetailObserver.observe(details));
        } else {
            const evaluateDesktopDetails = () => {
                desktopClaimDetails.forEach((details) => {
                    if (desktopReviewedClaims.has(details.dataset.claimId)) {
                        return;
                    }

                    const bounds = details.getBoundingClientRect();
                    const visibleHeight = Math.max(0, Math.min(bounds.bottom, window.innerHeight) - Math.max(bounds.top, 0));

                    if (bounds.height > 0 && visibleHeight / bounds.height >= 0.5) {
                        trackDesktopDetailOnce(details);
                    }
                });
            };

            window.addEventListener('scroll', evaluateDesktopDetails, { passive: true });
            window.addEventListener('resize', evaluateDesktopDetails);
            evaluateDesktopDetails();
        }
    })();
</script>
@endsection
