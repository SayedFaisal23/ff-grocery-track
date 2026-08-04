@props(['claim'])

@role('Superadmin')
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
        @elseif($claim->canUploadAttachment())
            <span class="claim-action-note">Waiting for requester receipt</span>
        @else
            <span class="claim-action-note">No further action</span>
        @endif
    </div>
@endrole
