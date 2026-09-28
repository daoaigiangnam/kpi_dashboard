@extends('layouts.admin')
@section('title','Customers')
@section('content')
<style>
.customer-page .head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}.customer-page .title{font-size:18px;font-weight:750}.customer-page .note{color:#66736b;font-size:13px;margin-top:4px}.customer-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:#eef8f2;color:#24613f;font-size:12px;margin:2px}.customer-empty{color:#94a39a;font-size:12px}.customer-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.customer-person{font-size:13px;line-height:1.35}.customer-person small{display:block;color:#66736b;margin-top:2px}
</style>
<div class="card customer-page">
<div class="head"><div><div class="title">Customers</div><div class="note">Danh mục khách hàng sử dụng cho Service, Monitoring và Alert. Quyền quản lý Customer đi theo Group của Đầu mối vận hành IT.</div></div>@if(auth()->user()->hasPermission('service_customers.create'))<a class="btn" href="{{ route('admin.service_customers.create') }}">+ Add Customer</a>@endif</div>
<form method="get" class="actions" style="margin-bottom:16px"><input class="input" style="max-width:360px;margin:0" name="search" value="{{ $search }}" placeholder="Search code, name, email..."><button class="btn gray">Search</button>@if($search!=='')<a class="btn gray" href="{{ route('admin.service_customers.index') }}">Clear</a>@endif<a class="btn gray" href="{{ route('admin.service_customers.index',['deleted'=>$showDeleted?null:1,'search'=>$search]) }}">{{ $showDeleted?'Hide Deleted':'Show Deleted' }}</a></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Customer</th><th>Contact</th><th>IT Vận hành</th><th>Sales</th><th>Nhóm IT</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($customers as $x)
<tr>
<td><strong>{{ $x->code }}</strong></td>
<td>{{ $x->name }}</td>
<td>{{ $x->contact_name }}</td>
<td><div class="customer-person">{{ $x->responsibleIt?->name ?: '—' }}<small>{{ $x->responsibleIt?->email ?: '' }}</small></div></td>
<td><div class="customer-person">{{ $x->salesContact?->name ?: '—' }}<small>{{ $x->salesContact?->email ?: '' }}</small></div></td>
<td>@forelse($x->groups as $group)<span class="customer-badge">{{ $group->name }}</span>@empty<span class="customer-empty">Chưa gán</span>@endforelse</td>
<td>{{ $x->email }}</td><td>{{ $x->phone }}</td>
<td>{{ $x->trashed()?'Deleted':($x->is_active?'Active':'Inactive') }}</td>
<td class="customer-actions">
@if(!$x->trashed())
@if(auth()->user()->hasPermission('service_customers.edit'))<a class="btn gray" href="{{ route('admin.service_customers.edit',$x) }}">Edit</a>@endif
@if(auth()->user()->hasPermission('service_customers.delete'))<form method="post" action="{{ route('admin.service_customers.destroy',$x) }}" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="btn red">Delete</button></form>@endif
@else
@if(auth()->user()->hasPermission('service_customers.delete'))<form method="post" action="{{ route('admin.service_customers.restore',$x->id) }}">@csrf @method('PATCH')<button class="btn">Restore</button></form>@endif
@endif
</td></tr>
@empty<tr><td colspan="10" class="muted">No customers found for your permissions / group assignment.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:16px">{{ $customers->links() }}</div>
</div>
@endsection
