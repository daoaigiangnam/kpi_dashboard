@extends('layouts.admin')
@section('title','Customers')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px"><div><strong>Customers</strong><div class="muted">Danh mục khách hàng sử dụng để gán cho các dịch vụ.</div></div><a class="btn" href="{{ route('admin.service_customers.create') }}">+ Add Customer</a></div>
    <form method="get" class="actions" style="margin-bottom:16px"><input class="input" style="max-width:360px;margin:0" name="search" value="{{ $search }}" placeholder="Search code, name, email..."><button class="btn gray">Search</button>@if($search!=='')<a class="btn gray" href="{{ route('admin.service_customers.index') }}">Clear</a>@endif<a class="btn gray" href="{{ route('admin.service_customers.index',['deleted'=>$showDeleted?null:1,'search'=>$search]) }}">{{ $showDeleted?'Hide Deleted':'Show Deleted' }}</a></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Customer</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($customers as $x)<tr><td><strong>{{ $x->code }}</strong></td><td>{{ $x->name }}</td><td>{{ $x->contact_name }}</td><td>{{ $x->email }}</td><td>{{ $x->phone }}</td><td>{{ $x->trashed()?'Deleted':($x->is_active?'Active':'Inactive') }}</td><td class="actions">@if(!$x->trashed())<a class="btn gray" href="{{ route('admin.service_customers.edit',$x) }}">Edit</a><form method="post" action="{{ route('admin.service_customers.destroy',$x) }}" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="btn red">Delete</button></form>@else<form method="post" action="{{ route('admin.service_customers.restore',$x->id) }}">@csrf @method('PATCH')<button class="btn">Restore</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="muted">No customers found.</td></tr>@endforelse
    </tbody></table></div><div style="margin-top:16px">{{ $customers->links() }}</div>
</div>
@endsection
