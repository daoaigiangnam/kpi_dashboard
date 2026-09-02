@extends('layouts.admin')
@section('title','Pending Registrations')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px">
        <div><strong>Pending User Registrations</strong><div class="muted">Review self-service registrations before granting login access.</div></div>
        <a class="btn gray" href="{{ route('admin.users.index') }}">← User Management</a>
    </div>

    @forelse($users as $u)
        <div style="border:1px solid #dbe4ef;border-radius:12px;padding:18px;margin-bottom:14px;background:#fbfdff">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
                <div>
                    <div style="font-size:18px;font-weight:750">{{ $u->name }}</div>
                    <div class="muted">{{ $u->employee_code }} · {{ $u->email }}</div>
                </div>
                <span style="display:inline-block;padding:5px 9px;border-radius:999px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:12px;font-weight:700">PENDING</span>
            </div>
            <div class="grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:16px;gap:12px">
                <div><div class="muted">Phone</div><strong>{{ $u->phone ?: '—' }}</strong></div>
                <div><div class="muted">Date of Birth</div><strong>{{ $u->date_of_birth?->format('Y-m-d') ?: '—' }}</strong></div>
                <div><div class="muted">Gender</div><strong>{{ $u->gender ?: '—' }}</strong></div>
                <div><div class="muted">Join Date</div><strong>{{ $u->join_date?->format('Y-m-d') ?: '—' }}</strong></div>
                <div><div class="muted">Department</div><strong>{{ $u->departmentRelation?->name ?: '—' }}</strong></div>
                <div><div class="muted">Unit</div><strong>{{ $u->unit?->name ?: '—' }}</strong></div>
                <div><div class="muted">Job Title</div><strong>{{ $u->jobTitle?->name ?: '—' }}</strong></div>
                <div><div class="muted">Submitted</div><strong>{{ $u->created_at?->format('Y-m-d H:i') }}</strong></div>
            </div>
            @if($u->notes)<div style="margin-top:13px;padding:10px 12px;background:#f1f5f9;border-radius:8px"><span class="muted">Notes:</span> {{ $u->notes }}</div>@endif
            <div class="actions" style="margin-top:16px">
                <form method="post" action="{{ route('admin.users.approve',$u) }}" onsubmit="return confirm('Approve this registration? The user will be allowed to sign in.')">
                    @csrf<button class="btn" type="submit">✓ Approve</button>
                </form>
                <form method="post" action="{{ route('admin.users.reject',$u) }}" style="display:flex;gap:8px;align-items:center" onsubmit="return confirm('Reject this registration? The account will remain inactive.')">
                    @csrf<input class="input" name="reason" maxlength="1000" placeholder="Optional rejection reason" style="width:260px;margin-top:0"><button class="btn red" type="submit">Reject</button>
                </form>
            </div>
        </div>
    @empty
        <div style="padding:28px;text-align:center;border:1px dashed #cbd5e1;border-radius:10px" class="muted">No pending registrations.</div>
    @endforelse
</div>
@endsection
