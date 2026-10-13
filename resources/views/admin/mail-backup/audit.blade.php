@extends('layouts.admin')
@section('title', 'Mail Backup Audit Log')
@section('content')
<div class="card">
    <h2>Audit Log</h2>
    <p class="muted">Lịch sử import batch, retry job và thay đổi cấu hình. Các giá trị bí mật không được đưa vào log.</p>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Thời gian</th><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th><th>Kết quả</th><th>Chi tiết</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr><td>{{ $log->created_at }}</td><td>{{ $log->actor?->email ?? 'System/Unknown' }}</td><td>{{ $log->action }}</td><td>{{ $log->entity_type }} #{{ $log->entity_id }}</td><td>{{ $log->result }}</td><td><code style="white-space:pre-wrap">{{ json_encode($log->details, JSON_UNESCAPED_UNICODE) }}</code></td><td>{{ $log->ip_address }}</td></tr>
        @empty<tr><td colspan="7" class="muted">Chưa có nhật ký.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div style="margin-top:14px">{{ $logs->links() }}</div>
</div>
@endsection
