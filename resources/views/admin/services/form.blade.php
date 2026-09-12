@extends('layouts.admin')
@section('title', $service->exists ? 'Edit Service' : 'Add Service')
@section('content')
<div class="card form">
    <form method="post" action="{{ $service->exists ? route('admin.services.update',$service) : route('admin.services.store') }}">
        @csrf
        @if($service->exists) @method('PUT') @endif
        <div class="field"><label>Customer *</label><select class="input" name="customer_id" required><option value="">Select customer</option>@foreach($customers as $x)<option value="{{ $x->id }}" @selected((int)old('customer_id',$service->customer_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service Type *</label><select class="input" id="service_type_id" name="service_type_id" required><option value="">Select service type</option>@foreach($serviceTypes as $x)<option value="{{ $x->id }}" @selected((int)old('service_type_id',$service->service_type_id)===$x->id) data-terms='@json($x->terms->pluck("months")->values())'>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service Name *</label><input class="input" name="service_name" value="{{ old('service_name',$service->service_name) }}" required></div>
        <div class="field"><label>Value</label><input class="input" name="value" value="{{ old('value',$service->value) }}" placeholder="Domain / IP / license / contract number..."></div>
        <div class="field"><label>Provider</label><select class="input" name="provider_id"><option value="">Select provider</option>@foreach($providers as $x)<option value="{{ $x->id }}" @selected((int)old('provider_id',$service->provider_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service Term</label><select class="input" id="service_term_months" name="service_term_months"></select></div>
        <div class="field"><label>Expiry Date</label><input class="input" id="expiry_date" type="date" name="expiry_date" value="{{ old('expiry_date', optional($service->expiry_date)->format('Y-m-d')) }}"><small class="muted">Leave blank for services without an expiry date.</small></div>
        <div class="field"><label>Alert Policy</label><select class="input" id="alert_policy_id" name="alert_policy_id"><option value="">Select policy</option>@foreach($policies as $x)<option value="{{ $x->id }}" data-type="{{ $x->service_type_id }}" @selected((int)old('alert_policy_id',$service->alert_policy_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Responsible IT</label><select class="input" name="responsible_it_id"><option value="">Select user</option>@foreach($responsibleUsers as $x)<option value="{{ $x->id }}" @selected((int)old('responsible_it_id',$service->responsible_it_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Status *</label><select class="input" name="status" required><option value="active" @selected(old('status',$service->status)==='active')>Active</option><option value="suspended" @selected(old('status',$service->status)==='suspended')>Suspended</option><option value="expired" @selected(old('status',$service->status)==='expired')>Expired</option></select></div>
        <div class="field"><label><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew',$service->auto_renew))> Auto Renew</label></div>
        <div class="field"><label>Note</label><textarea class="input" name="note" rows="4">{{ old('note',$service->note) }}</textarea></div>
        <div class="actions"><button class="btn" type="submit">Save</button><a class="btn gray" href="{{ route('admin.services.index') }}">Cancel</a></div>
    </form>
</div>
<script>
(function(){
 const type=document.getElementById('service_type_id'), term=document.getElementById('service_term_months'), expiry=document.getElementById('expiry_date'), policy=document.getElementById('alert_policy_id');
 let currentTerm='{{ old('service_term_months',$service->service_term_months) }}', currentPolicy='{{ old('alert_policy_id',$service->alert_policy_id) }}';
 function refresh(){
   const opt=type.options[type.selectedIndex]; let terms=[];
   try{terms=JSON.parse(opt?.dataset.terms||'[]')}catch(e){}
   term.innerHTML='<option value="">No term</option>'+terms.map(m=>`<option value="${m}" ${String(m)===String(currentTerm)?'selected':''}>${m} tháng</option>`).join('');
   [...policy.options].forEach(o=>{ if(!o.value) return; const ok=o.dataset.type===type.value; o.hidden=!ok; if(!ok && o.selected) o.selected=false; });
   if([...policy.options].some(o=>o.value===String(currentPolicy)&&!o.hidden)) policy.value=currentPolicy;
   syncExpiryFields();
 }
 function syncExpiryFields(){
   const hasExpiry=!!expiry.value;
   term.required=hasExpiry;
   expiry.required=false;
   policy.required=hasExpiry;
   term.disabled=!hasExpiry;
   policy.disabled=!hasExpiry;
   if(!hasExpiry){ term.value=''; policy.value=''; }
 }
 type.addEventListener('change',()=>{ currentTerm=''; currentPolicy=''; refresh(); });
 expiry.addEventListener('change',syncExpiryFields);
 refresh();
})();
</script>
@endsection
