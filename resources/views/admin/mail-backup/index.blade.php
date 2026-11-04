@extends('layouts.admin')
@section('title', 'Mail Backup Manager')
@section('content')
<div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px">
    @foreach([['Batch',$summary['batches']],['Tổng tài khoản',$summary['jobs']],['Chờ xử lý',$summary['pending']],['Đang chạy',$summary['running']],['Hoàn tất',$summary['completed']],['Thất bại',$summary['failed']]] as [$label,$value])
        <div class="card"><div class="muted">{{ $label }}</div><div style="font-size:28px;font-weight:750;margin-top:6px">{{ number_format($value) }}</div></div>
    @endforeach
</div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><h2 style="margin:0 0 5px">Các đợt backup</h2><div class="muted">Giai đoạn 1: quản lý batch, job queue và retry; chưa chạy backup email/PST thật.</div></div>
        @if(auth()->user()->hasPermission('mail_backup.manage'))
            <a class="btn" href="{{ route('admin.mail_backup.create') }}">＋ Import tài khoản / Tạo Batch</a>
        @endif
    </div>
    <div class="table-wrap" style="margin-top:18px"><table class="table">
        <thead><tr><th>Batch</th><th>Khoảng ngày</th><th>Phạm vi</th><th>Tiến độ</th><th>Trạng thái</th><th>Người tạo</th><th></th></tr></thead>
        <tbody>
        @forelse($batches as $batch)
            @php($percent = $batch->total_jobs ? (int) floor($batch->completed_jobs / $batch->total_jobs * 100) : 0)
            <tr>
                <td><strong>{{ $batch->batch_code }}</strong><br>{{ $batch->name }}</td>
                <td>{{ $batch->date_from->format('d/m/Y') }} – {{ $batch->date_to->format('d/m/Y') }}</td>
                <td>{{ strtoupper($batch->mail_scope) }}</td>
                <td style="min-width:150px"><div style="height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden"><div style="height:8px;width:{{ $percent }}%;background:#16834a"></div></div><small>{{ $percent }}% · {{ $batch->completed_jobs }}/{{ $batch->total_jobs }}</small></td>
                <td><span class="btn {{ in_array($batch->status,['FAILED','PARTIAL_FAILED'])?'red':'gray' }}" style="padding:4px 8px">{{ $batch->status }}</span><br><small class="muted">Lỗi: {{ $batch->failed_jobs }}</small></td>
                <td>{{ $batch->creator?->email }}</td>
                <td><a href="{{ route('admin.mail_backup.show',$batch) }}">Chi tiết →</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Chưa có batch backup nào.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="margin-top:14px">{{ $batches->links() }}</div>
</div>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px">
    @if(auth()->user()->hasPermission('mail_backup.settings'))<a class="btn gray" href="{{ route('admin.mail_backup.settings') }}">⚙ Cấu hình Email Admin (động)</a>@endif
</div>
@endsection
