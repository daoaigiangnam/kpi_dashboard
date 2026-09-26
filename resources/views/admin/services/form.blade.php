@extends('layouts.admin')
@section('title', $service->exists ? 'Edit Service' : 'Add Service')
@section('content')
<div class="card form">
    <form method="post" action="{{ $service->exists ? route('admin.services.update',$service) : route('admin.services.store') }}">
        @csrf
        @if($service->exists) @method('PUT') @endif
        <div class="field"><label>Customer *</label><select class="input" name="customer_id" required><option value="">Select customer</option>@foreach($customers as $x)<option value="{{ $x->id }}" @selected((int)old('customer_id',$service->customer_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service Type *</label><select class="input" id="service_type_id" name="service_type_id" required>@foreach($serviceTypes as $x)<option value="{{ $x->id }}" data-code="{{ $x->code }}" @selected((int)old('service_type_id',$service->service_type_id)===$x->id) data-terms='@json($x->terms->pluck("months")->values())'>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Service Name *</label><input class="input" name="service_name" value="{{ old('service_name',$service->service_name) }}" required></div>
        <div class="field" id="website-title-field" style="display:none"><label>Website Title</label><input class="input" name="website_title" id="website_title" value="{{ old('website_title',$service->website_title) }}" maxlength="255" placeholder="Ví dụ: Review360 - IT Service Management"><small class="muted">Tiêu đề website dùng để nhận diện dịch vụ trên Dashboard, Monitoring và báo cáo. Không bắt buộc.</small></div>
        <div class="field"><label>Value</label><input class="input" name="value" id="service_value" value="{{ old('value',$service->value) }}" placeholder="Domain / Website URL / IP / license / contract number..."></div>
        <div class="field"><label>Provider</label><select class="input" name="provider_id"><option value="">Select provider</option>@foreach($providers as $x)<option value="{{ $x->id }}" @selected((int)old('provider_id',$service->provider_id)===$x->id)>{{ $x->name }}</option>@endforeach</select><small class="muted">Website: không bắt buộc. Chỉ khai báo khi có nhà cung cấp/đơn vị hosting.</small></div>
        <div class="field"><label>Service Cost</label><div style="display:flex;gap:8px"><input class="input" style="flex:1" type="number" min="0" step="0.01" name="cost_amount" value="{{ old('cost_amount',$service->cost_amount) }}" placeholder="Không bắt buộc"><select class="input" style="width:110px" name="cost_currency"><option value="VND" @selected(old('cost_currency',$service->cost_currency ?: 'VND')==='VND')>VND</option><option value="USD" @selected(old('cost_currency',$service->cost_currency)==='USD')>USD</option><option value="EUR" @selected(old('cost_currency',$service->cost_currency)==='EUR')>EUR</option></select></div><small class="muted">Website: chỉ nhập khi dịch vụ có chi phí.</small></div>
        <div class="field"><label>Billing Cycle</label><select class="input" id="cost_billing_cycle" name="cost_billing_cycle"><option value="">Not specified</option><option value="monthly" @selected(old('cost_billing_cycle',$service->cost_billing_cycle)==='monthly')>Monthly</option><option value="quarterly" @selected(old('cost_billing_cycle',$service->cost_billing_cycle)==='quarterly')>Quarterly</option><option value="yearly" @selected(old('cost_billing_cycle',$service->cost_billing_cycle)==='yearly')>Yearly</option><option value="one_time" @selected(old('cost_billing_cycle',$service->cost_billing_cycle)==='one_time')>One-time</option></select></div>

        <div id="ftth-payment-fields" style="display:none;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:10px 0 14px">
            <div style="font-weight:600;margin-bottom:10px">💰 FTTH Payment Schedule</div>
            <div class="field"><label>Payment Due Day *</label><input class="input" type="number" min="1" max="31" name="payment_due_day" value="{{ old('payment_due_day',$service->payment_due_day ?: 15) }}" placeholder="15"><small class="muted">Chỉ nhập ngày trong tháng. Ví dụ 15 = hàng tháng thanh toán trước/ngày 15.</small></div>
            <div class="field"><label>Payment Alert Threshold (%) *</label><input class="input" type="number" min="1" max="100" name="payment_alert_percent" value="{{ old('payment_alert_percent',$service->payment_alert_percent ?: 20) }}" placeholder="20"><small class="muted">Mặc định 20%. Đây là tỷ lệ thời gian của kỳ thanh toán, không phải % số tiền.</small></div>
        </div>

        <div id="monitoring-fields" style="display:none;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:10px 0 14px">
            <div style="font-weight:600;margin-bottom:10px" id="monitoring-title">📡 Network Monitoring</div>
            <div class="field"><label id="monitor-target-label">Monitor Target *</label><input class="input" id="monitor_target" name="monitor_target" value="{{ old('monitor_target',$service->monitor_target) }}" placeholder="IP / hostname / website domain"><small class="muted" id="monitor-target-help">Địa chỉ cần giám sát.</small></div>
            <div class="field"><label>Check Method</label><select class="input" id="monitor_check_method" name="monitor_check_method"><option value="">Disabled</option><option value="ping" @selected(old('monitor_check_method',$service->monitor_check_method)==='ping')>PING</option><option value="port" @selected(old('monitor_check_method',$service->monitor_check_method)==='port')>Check TCP Port(s)</option></select></div>
            <div class="field" id="monitor-port-field" style="display:none"><label id="monitor-port-label">Check Port(s) *</label><input class="input" id="monitor_ports" type="text" name="monitor_ports" value="{{ old('monitor_ports', is_array($service->monitor_ports) ? implode(',', $service->monitor_ports) : ($service->monitor_port ?: '')) }}" placeholder="80,443"><small class="muted" id="monitor-port-help">Nhập nhiều port, phân cách bằng dấu phẩy. Website mặc định kiểm tra HTTP 80 và HTTPS 443. VPS tối đa 20 port.</small></div>
            <div class="field"><label>Check Interval</label><select class="input" name="monitor_interval_seconds"><option value="30" @selected((int)old('monitor_interval_seconds',$service->monitor_interval_seconds ?: 60)===30)>30 seconds</option><option value="60" @selected((int)old('monitor_interval_seconds',$service->monitor_interval_seconds ?: 60)===60)>1 minute</option><option value="300" @selected((int)old('monitor_interval_seconds',$service->monitor_interval_seconds ?: 60)===300)>5 minutes</option><option value="600" @selected((int)old('monitor_interval_seconds',$service->monitor_interval_seconds ?: 60)===600)>10 minutes</option></select></div>
            <div class="field"><label>Timeout</label><select class="input" name="monitor_timeout_seconds"><option value="3" @selected((int)old('monitor_timeout_seconds',$service->monitor_timeout_seconds ?: 5)===3)>3 seconds</option><option value="5" @selected((int)old('monitor_timeout_seconds',$service->monitor_timeout_seconds ?: 5)===5)>5 seconds</option><option value="10" @selected((int)old('monitor_timeout_seconds',$service->monitor_timeout_seconds ?: 5)===10)>10 seconds</option></select></div>
            @if($service->exists)
            <div class="field"><button type="button" class="btn gray" id="test-network">🔌 Test Connection</button><div id="network-test-result" class="muted" style="margin-top:8px"></div></div>
            @endif
        </div>

        <div id="website-ssl-fields" style="display:none;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:10px 0 14px">
            <div style="font-weight:600;margin-bottom:10px">🔐 Website / SSL Monitoring</div>
            <div class="field"><label>SSL Certificate</label><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><span id="ssl-detected-badge" class="muted">Not checked</span>@if($service->exists)<button type="button" class="btn gray" id="detect-ssl">🔎 Detect SSL</button>@endif</div><small class="muted">Hệ thống kết nối HTTPS, lấy certificate hiện tại và lưu ngày hết hạn SSL.</small></div>
            <div class="field"><label>SSL Expiry Date</label><input class="input" id="ssl_expiry_date" type="date" value="{{ optional($service->ssl_expiry_date)->format('Y-m-d') }}" readonly><small class="muted" id="ssl-help">Chưa phát hiện SSL.</small><div id="ssl-detect-result" class="muted" style="margin-top:6px"></div></div>
        </div>

        <div id="expiry-fields">
            <div class="field"><label>Service Term</label><select class="input" id="service_term_months" name="service_term_months"></select></div>
            <div class="field"><label>Expiry Date</label><div style="display:flex;gap:8px;align-items:center"><input class="input" style="flex:1" id="expiry_date" type="date" name="expiry_date" value="{{ old('expiry_date', optional($service->expiry_date)->format('Y-m-d')) }}"><button type="button" class="btn gray" id="detect-expiry" style="display:none;white-space:nowrap">🔎 Detect Expiry</button></div><small class="muted" id="expiry-help">For Domain / VPS / License / fixed-term services. Website: chỉ nhập khi có thời hạn thuê dịch vụ.</small><div id="detect-result" class="muted" style="margin-top:6px"></div></div>
            <div class="field"><label>Alert Policy</label><select class="input" id="alert_policy_id" name="alert_policy_id"><option value="">Select policy</option>@foreach($policies as $x)<option value="{{ $x->id }}" data-type="{{ $x->service_type_id }}" @selected((int)old('alert_policy_id',$service->alert_policy_id)===$x->id)>{{ $x->name }}</option>@endforeach</select><small class="muted">Website: policy này được dùng cho cảnh báo SSL nếu certificate sắp hết hạn.</small></div>
        </div>
        <div class="field"><label>Responsible IT</label><select class="input" name="responsible_it_id"><option value="">Select user</option>@foreach($responsibleUsers as $x)<option value="{{ $x->id }}" @selected((int)old('responsible_it_id',$service->responsible_it_id)===$x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="field"><label>Status *</label><select class="input" name="status" required><option value="active" @selected(old('status',$service->status)==='active')>Active</option><option value="suspended" @selected(old('status',$service->status)==='suspended')>Suspended</option><option value="expired" @selected(old('status',$service->status)==='expired')>Expired</option></select></div>
        <div class="field"><label><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew',$service->auto_renew))> Auto Renew</label></div>
        <div class="field"><label>Note</label><textarea class="input" name="note" rows="4">{{ old('note',$service->note) }}</textarea></div>
        <div class="actions"><button class="btn" type="submit">Save</button><a class="btn gray" href="{{ route('admin.services.index') }}">Cancel</a></div>
    </form>
</div>
<script>
(function(){
 const type=document.getElementById('service_type_id'),term=document.getElementById('service_term_months'),expiry=document.getElementById('expiry_date'),policy=document.getElementById('alert_policy_id'),billing=document.getElementById('cost_billing_cycle');
 const websiteTitleField=document.getElementById('website-title-field'),websiteTitle=document.getElementById('website_title');
 const method=document.getElementById('monitor_check_method'),target=document.getElementById('monitor_target'),ports=document.getElementById('monitor_ports'),monitoring=document.getElementById('monitoring-fields'),portField=document.getElementById('monitor-port-field'),ftthPayment=document.getElementById('ftth-payment-fields'),expiryFields=document.getElementById('expiry-fields'),sslFields=document.getElementById('website-ssl-fields');
 const targetLabel=document.getElementById('monitor-target-label'),targetHelp=document.getElementById('monitor-target-help'),portLabel=document.getElementById('monitor-port-label'),portHelp=document.getElementById('monitor-port-help'),monitorTitle=document.getElementById('monitoring-title');
 const detect=document.getElementById('detect-expiry'),result=document.getElementById('detect-result');
 const testButton=document.getElementById('test-network'),testResult=document.getElementById('network-test-result');
 const detectSsl=document.getElementById('detect-ssl'),sslResult=document.getElementById('ssl-detect-result'),sslExpiry=document.getElementById('ssl_expiry_date'),sslBadge=document.getElementById('ssl-detected-badge'),sslHelp=document.getElementById('ssl-help');
 let currentTerm='{{ old('service_term_months',$service->service_term_months) }}',currentPolicy='{{ old('alert_policy_id',$service->alert_policy_id) }}',currentMethod='{{ old('monitor_check_method',$service->monitor_check_method) }}';
 const existingSsl={{ $service->ssl_detected ? 'true' : 'false' }};
 function refresh(){
   const opt=type.options[type.selectedIndex];
   let terms=[];try{terms=JSON.parse(opt?.dataset.terms||'[]')}catch(e){}
   term.innerHTML='<option value="">No term</option>'+terms.map(m=>'<option value="'+m+'" '+(String(m)===String(currentTerm)?'selected':'')+'>'+m+' tháng</option>').join('');
   [...policy.options].forEach(o=>{if(!o.value)return;const ok=o.dataset.type===type.value;o.hidden=!ok;if(!ok&&o.selected)o.selected=false;});
   if([...policy.options].some(o=>o.value===String(currentPolicy)&&!o.hidden))policy.value=currentPolicy;
   const code=String(opt?.dataset.code||'').toUpperCase();
   const isInternet=code==='INTERNET';
   const isVps=code==='VPS';
   const isWebsite=code==='WEBSITE';
   const isNetworkService=isInternet||isVps||isWebsite;
   const isMonthlyFtth=isInternet && billing.value==='monthly';
   monitoring.style.display=isNetworkService?'block':'none';
   sslFields.style.display=isWebsite?'block':'none';
   websiteTitleField.style.display=isWebsite?'block':'none';
   ftthPayment.style.display=isMonthlyFtth?'block':'none';
   expiryFields.style.display=isMonthlyFtth?'none':'block';
   detect.style.display=code==='DOMAIN'?'inline-block':'none';
   if(isWebsite){
      monitorTitle.textContent='🌐 Website Monitoring';
      targetLabel.textContent='Website / Monitor Target *';
      targetHelp.textContent='Nhập hostname/domain của website, ví dụ review360.id.vn. Không cần https://.';
      portLabel.textContent='HTTP / HTTPS Ports *';
      portHelp.textContent='Mặc định: 80 (HTTP) và 443 (HTTPS). Hệ thống báo UP/DOWN từng port.';
      if(!target.value && document.getElementById('service_value')?.value) target.value=document.getElementById('service_value').value.replace(/^https?:\/\//,'').split('/')[0];
      if(!ports.value) ports.value='80,443';
      if(!currentMethod) currentMethod='port';
   } else if(isVps){
      monitorTitle.textContent='📡 VPS Monitoring';
      targetLabel.textContent='VPS IP / Hostname *';
      targetHelp.textContent='IP hoặc hostname của VPS cần giám sát.';
      portLabel.textContent='TCP Port(s) *';
      portHelp.textContent='Có thể kiểm tra nhiều dịch vụ. Ví dụ: 22,80,443,3306. Tối đa 20 port.';
   } else {
      monitorTitle.textContent='📡 FTTH / Internet Monitoring';
      targetLabel.textContent='WAN IP / Monitor Target *';
      targetHelp.textContent='IP WAN / hostname của đường Internet cần giám sát.';
      portLabel.textContent='Check Port';
      portHelp.textContent='Internet có thể kiểm tra một TCP port, ví dụ 443.';
   }
   if(isNetworkService){
      method.value=currentMethod||method.value||'';
      portField.style.display=method.value==='port'?'block':'none';
      ports.required=(isVps||isWebsite)&&method.value==='port';
   } else {
      method.value='';target.value='';ports.value='';portField.style.display='none';ports.required=false;
   }
   if(isWebsite && sslBadge){
      sslBadge.textContent=existingSsl?'🟢 SSL detected':'⚪ SSL not checked';
      sslHelp.textContent=existingSsl ? 'Certificate đã được lưu. Bấm Detect SSL để cập nhật lại.' : 'Chưa phát hiện SSL.';
   }
   syncExpiryFields();
 }
 function syncExpiryFields(){
   const code=String(type.options[type.selectedIndex]?.dataset.code||'').toUpperCase();
   const isMonthlyFtth=code==='INTERNET' && billing.value==='monthly';
   if(isMonthlyFtth){term.value='';policy.value='';term.disabled=true;policy.disabled=true;expiry.value='';expiry.disabled=true;}
   else{term.disabled=false;policy.disabled=false;expiry.disabled=false;term.required=!!expiry.value;policy.required=!!expiry.value;}
 }
 type.addEventListener('change',()=>{currentTerm='';currentPolicy='';currentMethod='';result.textContent='';if(testResult)testResult.textContent='';refresh();});
 billing.addEventListener('change',()=>{refresh();});
 method?.addEventListener('change',()=>{currentMethod=method.value;portField.style.display=method.value==='port'?'block':'none';const code=String(type.options[type.selectedIndex]?.dataset.code||'').toUpperCase();ports.required=(code==='VPS'||code==='WEBSITE')&&method.value==='port';if(method.value!=='port' && code!=='WEBSITE')ports.value='';if(testResult)testResult.textContent='';});
 expiry.addEventListener('change',syncExpiryFields);
 detect?.addEventListener('click',async()=>{if(!{{ $service->exists?'true':'false' }}){result.textContent='Save the service first, then Detect Expiry.';return;}detect.disabled=true;detect.textContent='Detecting...';result.textContent='';try{const r=await fetch('{{ $service->exists ? route('admin.services.detect_expiry',$service) : '#' }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});const d=await r.json();if(d.ok&&d.result?.saved_expiry_date){expiry.value=d.result.saved_expiry_date;result.textContent='Detected: '+d.result.saved_expiry_date+(d.result.days_remaining!=null?' ('+d.result.days_remaining+' days remaining)':'');syncExpiryFields();}else{result.textContent=d.result?.error||d.message||'Could not detect domain expiry.';}}catch(e){result.textContent='Detect failed: '+e.message;}finally{detect.disabled=false;detect.textContent='🔎 Detect Expiry';}});
 detectSsl?.addEventListener('click',async()=>{detectSsl.disabled=true;detectSsl.textContent='Detecting SSL...';sslResult.textContent='';try{const r=await fetch('{{ $service->exists ? route('admin.services.detect_expiry',$service) : '#' }}?ssl=1',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams({monitor_target:target?.value||''})});const d=await r.json();if(d.ok&&d.result?.saved_ssl_expiry_date){sslExpiry.value=d.result.saved_ssl_expiry_date;sslBadge.textContent='🟢 SSL detected';sslHelp.textContent='Issuer: '+(d.result.issuer||'-')+' · Subject: '+(d.result.subject_cn||'-')+' · Valid From: '+(d.result.valid_from||'-')+' · Expiry: '+d.result.saved_ssl_expiry_date+' · Remaining: '+(d.result.days_remaining??'-')+' days';sslResult.textContent='✅ Certificate detected and saved.';}else{sslBadge.textContent='🔴 SSL not detected';sslHelp.textContent='Không lấy được certificate HTTPS.';sslResult.textContent=d.result?.error||d.message||'SSL detection failed.';}}catch(e){sslResult.textContent='SSL detection failed: '+e.message;}finally{detectSsl.disabled=false;detectSsl.textContent='🔎 Detect SSL';}});
 testButton?.addEventListener('click',async()=>{testButton.disabled=true;testButton.textContent='Testing...';testResult.textContent='Checking '+(target?.value||'target')+' via '+(method?.value||'method')+'...';try{const url='{{ $service->exists ? route('admin.services.edit',$service) : '#' }}'+('{{ $service->exists ? "?network_test=1" : "" }}');const r=await fetch(url,{headers:{'Accept':'application/json'}});const d=await r.json();const x=d.result||{};let detail='';if(Array.isArray(x.port_results)){detail=' — '+x.port_results.map(p=>'Port '+p.port+': '+(p.online?'UP':'DOWN')+(p.latency_ms!=null?' ('+p.latency_ms+' ms)':'')).join(', ');}testResult.textContent=(d.ok?'🟢 ONLINE':'🔴 OFFLINE')+' — '+(x.error||'Connectivity test completed.')+detail;}catch(e){testResult.textContent='❌ Test failed: '+e.message;}finally{testButton.disabled=false;testButton.textContent='🔌 Test Connection';}});
 refresh();
})();
</script>
@endsection
