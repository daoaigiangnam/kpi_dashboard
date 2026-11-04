@extends('layouts.admin')
@section('title', 'Chi tiết Batch Backup')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div><h2 style="margin:0 0 5px">{{ $batch->name }}</h2><div class="muted">{{ $batch->batch_code }} · {{ $batch->date_from->format('d/m/Y') }} – {{ $batch->date_to->format('d/m/Y') }} · {{ strtoupper($batch->mail_scope) }}</div></div>
        <span class="btn gray">{{ $batch->status }}</span>
    </div>
    <div class="grid" style="grid-template-columns:repeat(5,minmax(0,1fr));margin:18px 0">
        @foreach([['Tổng',$batch->total_jobs],['Chờ',$batch->pending_jobs],['Đang chạy',$batch->running_jobs],['Hoàn tất',$batch->completed_jobs],['Lỗi',$batch->failed_jobs]] as [$label,$value])
            <div style="padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px"><div class="muted">{{ $label }}</div><strong style="font-size:22px">{{ $value }}</strong></div>
        @endforeach
    </div>
    @if($batch->failed_jobs > 0 && auth()->user()->hasPermission('mail_backup.manage'))
        <form method="post" action="{{ route('admin.mail_backup.retry_failed',$batch) }}" onsubmit="return confirm('Chỉ retry các job FAILED trong batch này. Job hoàn tất không bị chạy lại. Tiếp tục?')">
            @csrf
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="confirm_retry" value="1" required style="width:auto"> Tôi xác nhận retry các tài khoản thất bại.</label>
            <button class="btn" type="submit">Retry Failed ({{ $batch->failed_jobs }})</button>
        </form>
    @endif
    @if(session('import_issues'))
        <details style="margin-top:16px"><summary>Dòng CSV bị bỏ qua (tối đa 100)</summary><ul>@foreach(session('import_issues') as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>
    @endif
</div>
<div class="card" style="margin-top:18px">
    <h2>Trạng thái từng tài khoản</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Email</th><th>Trạng thái</th><th>Lần thử</th><th>Email đã xử lý</th><th>Lỗi gần nhất</th><th></th></tr></thead><tbody>
        @forelse($batch->jobs as $job)
            <tr><td>#{{ $job->id }}</td><td>{{ $job->email }}<br><small class="muted">{{ $job->display_name }}</small></td><td>{{ $job->status }}</td><td>{{ $job->attempt_count }}</td><td>{{ number_format($job->processed_messages) }}{{ $job->expected_messages !== null ? ' / '.number_format($job->expected_messages) : '' }}</td><td style="max-width:300px;overflow-wrap:anywhere;color:#b42318">{{ $job->last_error }}</td><td><a href="{{ route('admin.mail_backup.job',$job) }}">Log</a></td></tr>
        @empty
            <tr><td colspan="7" class="muted">Batch chưa có job.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endsection
