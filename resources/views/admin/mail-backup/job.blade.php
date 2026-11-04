@extends('layouts.admin')
@section('title', 'Chi tiết Backup Job')
@section('content')
<div class="card">
    <h2>Job #{{ $job->id }}</h2>
    <p class="muted">{{ $job->batch?->batch_code }} · {{ $job->email }}</p>
    <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        @foreach([['Trạng thái',$job->status],['Lần thử',$job->attempt_count],['Email đã xử lý',number_format($job->processed_messages)],['Worker',$job->worker_id ?: '—'],['Bắt đầu',$job->started_at ?: '—'],['Kết thúc',$job->finished_at ?: '—']] as [$label,$value])
            <div style="padding:12px;background:#f8fafc;border-radius:8px"><div class="muted">{{ $label }}</div><strong>{{ $value }}</strong></div>
        @endforeach
    </div>
    @if($job->last_error)<div class="error" style="margin-top:14px">{{ $job->last_error }}</div>@endif
    <p><a class="btn gray" href="{{ route('admin.mail_backup.show',$job->batch_id) }}">Quay lại Batch</a>
    @if($job->status === 'FAILED' && auth()->user()->hasPermission('mail_backup.manage'))
        <form method="post" action="{{ route('admin.mail_backup.retry_job',$job) }}" style="display:inline" onsubmit="return confirm('Retry job này?')">@csrf<button class="btn" type="submit">Retry Job</button></form>
    @endif</p>
</div>
<div class="card" style="margin-top:18px"><h2>Log xử lý</h2><div class="table-wrap"><table class="table"><thead><tr><th>Thời gian</th><th>Lần thử</th><th>Mức</th><th>Thông điệp</th></tr></thead><tbody>
    @forelse($job->logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->attempt_no }}</td><td>{{ $log->level }}</td><td>{{ $log->message }} @if($log->context)<pre style="white-space:pre-wrap">{{ json_encode($log->context,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif</td></tr>
    @empty<tr><td colspan="4" class="muted">Chưa có log.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
