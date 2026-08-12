@props(['claim'])

@role('Superadmin')
    @php
        $requesterDocument = $claim->isOwnExpensesPayment()
            ? 'receipt or invoice'
            : ($claim->isDirectorCreditCardPayment() ? 'invoice' : 'receipt');
    @endphp
    <div class="claim-actions">
        @if($claim->canBeReviewed())
            <form action="{{ route('tuntutan.status', $claim) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="approval_result" value="Approved">
                <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
            </form>
            <form action="{{ route('tuntutan.status', $claim) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="approval_result" value="Rejected">
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-xmark"></i> Reject</button>
            </form>
        @elseif($claim->canUploadPaymentProof())
            <span class="claim-action-note">Upload company payment proof in request details</span>
        @elseif($claim->canUploadAttachment())
            <span class="claim-action-note">Waiting for requester {{ $requesterDocument }}</span>
        @else
            <span class="claim-action-note">No further action</span>
        @endif
    </div>
@endrole
