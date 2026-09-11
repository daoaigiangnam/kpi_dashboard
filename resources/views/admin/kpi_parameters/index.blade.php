@extends('layouts.admin')

@section('title','KPI Parameters')

@section('content')
<form method="post" action="{{ route('admin.kpi_parameters.update') }}">
    @csrf
    @method('PUT')

    <div class="card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px">
            <div>
                <div style="font-weight:700;font-size:16px">KPI Weight (%)</div>
                <div class="muted">Define the contribution of each KPI component. The total must equal exactly 100%.</div>
            </div>
            <div style="padding:8px 12px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-weight:700">
                Total: <span id="weight-total">{{ $weights->sum('weight') }}</span>%
            </div>
        </div>

        <div class="table-wrap">
            <table class="table" style="min-width:620px">
                <thead>
                    <tr>
                        <th style="width:60%">KPI Component</th>
                        <th style="width:25%">Weight (%)</th>
                        <th>Code</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($weights as $weight)
                        <tr>
                            <td><strong>{{ $weight->name }}</strong></td>
                            <td>
                                <input class="input kpi-weight-input" type="number" name="weights[{{ $weight->id }}]" value="{{ old('weights.'.$weight->id, $weight->weight) }}" min="0" max="100" step="1" style="margin-top:0" required>
                            </td>
                            <td class="muted">{{ $weight->code }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px">
            <div>
                <div style="font-weight:700;font-size:16px">SLA by Priority</div>
                <div class="muted">Maintain the response target, resolution target, priority weight and Workload Point used by KPI calculation.</div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table" style="min-width:900px">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Description</th>
                        <th>SLA Response (min)</th>
                        <th>SLA Resolution (min)</th>
                        <th>Weight</th>
                        <th>Workload Point</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($priorities as $priority)
                        <tr>
                            <td><strong>{{ $priority->code }}</strong></td>
                            <td>{{ $priority->description }}</td>
                            <td>
                                <input class="input" type="number" name="priorities[{{ $priority->id }}][response_minutes]" value="{{ old('priorities.'.$priority->id.'.response_minutes', $priority->response_minutes) }}" min="0" max="525600" step="1" style="margin-top:0" required>
                            </td>
                            <td>
                                <input class="input" type="number" name="priorities[{{ $priority->id }}][resolution_minutes]" value="{{ old('priorities.'.$priority->id.'.resolution_minutes', $priority->resolution_minutes) }}" min="0" max="525600" step="1" style="margin-top:0" required>
                            </td>
                            <td>
                                <input class="input" type="number" name="priorities[{{ $priority->id }}][weight]" value="{{ old('priorities.'.$priority->id.'.weight', $priority->weight) }}" min="0" max="100" step="1" style="margin-top:0" required>
                            </td>
                            <td>
                                <input class="input" type="number" name="priorities[{{ $priority->id }}][workload_point]" value="{{ old('priorities.'.$priority->id.'.workload_point', $priority->workload_point) }}" min="0" max="999999" step="1" style="margin-top:0" required>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px">
            <div class="muted">Changes are stored in the KPI configuration tables and will be used by subsequent KPI calculations.</div>
            <button class="btn" type="submit">Save KPI Parameters</button>
        </div>
    </div>
</form>

<script>
(function(){
    const inputs=document.querySelectorAll('.kpi-weight-input');
    const total=document.getElementById('weight-total');
    function refresh(){
        let sum=0;
        inputs.forEach(function(input){sum+=Number(input.value||0);});
        total.textContent=sum;
        total.style.color=sum===100?'#166534':'#b91c1c';
    }
    inputs.forEach(function(input){input.addEventListener('input',refresh);});
    refresh();
})();
</script>
@endsection
