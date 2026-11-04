@extends('layouts.admin')
@section('title', 'Tạo Batch Backup')
@section('content')
<div class="card form" style="max-width:850px">
    <h2>Import danh sách tài khoản Google Workspace</h2>
    <p class="muted">CSV UTF-8, tối đa 5.000 dòng. Bắt buộc cột <code>email</code>; tùy chọn <code>display_name</code>.</p>
    <div class="info">Màn hình này chỉ tạo batch và job queue. Chưa kết nối Gmail API và chưa tạo file PST ở giai đoạn 1.</div>
    <form method="post" action="{{ route('admin.mail_backup.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="field"><label>Tên Batch</label><input class="input" name="name" required maxlength="190" value="{{ old('name') }}" placeholder="Backup email năm 2026"></div>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="field"><label>Từ ngày</label><input class="input" type="date" name="date_from" required value="{{ old('date_from',now()->startOfYear()->format('Y-m-d')) }}"></div>
            <div class="field"><label>Đến ngày (bao gồm)</label><input class="input" type="date" name="date_to" required value="{{ old('date_to',now()->format('Y-m-d')) }}"></div>
        </div>
        <div class="field"><label>Phạm vi email</label><select class="input" name="mail_scope" required><option value="all" @selected(old('mail_scope','all')==='all')>All Mail</option><option value="inbox" @selected(old('mail_scope')==='inbox')>Inbox</option><option value="sent" @selected(old('mail_scope')==='sent')>Sent</option></select></div>
        <div class="field"><label>Danh sách CSV</label><input class="input" type="file" name="accounts_csv" accept=".csv,.txt,text/csv" required><div class="muted">Ví dụ: email,display_name</div></div>
        <div class="actions"><button class="btn" type="submit">Kiểm tra CSV và tạo Batch</button><a class="btn gray" href="{{ route('admin.mail_backup.index') }}">Hủy</a></div>
    </form>
</div>
@endsection
