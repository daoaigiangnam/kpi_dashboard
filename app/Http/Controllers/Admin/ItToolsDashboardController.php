<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItToolAudit;
use App\Services\ItTools\ItToolStatusService;
use Illuminate\Http\Request;

class ItToolsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = ItToolAudit::query()->latest();
        $days = max(1, min((int) $request->input('days', 30), 365));
        $since = now()->subDays($days);

        $audits = (clone $query)->where('created_at', '>=', $since)->get();
        $domainLevels = $audits->map(fn ($audit) => ItToolStatusService::level(data_get($audit->result, 'domain_audit.days_remaining')));
        $sslLevels = $audits->map(fn ($audit) => ItToolStatusService::level(data_get($audit->result, 'ssl_audit.days_remaining')));

        $expiredDomains = $domainLevels->filter(fn ($level) => $level === 'expired')->count();
        $criticalDomains = $domainLevels->filter(fn ($level) => $level === 'critical')->count();
        $warningDomains = $domainLevels->filter(fn ($level) => $level === 'warning')->count();
        $expiringSsl = $sslLevels->filter(fn ($level) => in_array($level, ['expired', 'critical', 'warning'], true))->count();
        $offline = $audits->filter(fn ($audit) => data_get($audit->result, 'website_audit.https.online') === false)->count();
        $failed = $audits->where('status', '!=', 'completed')->count();

        return view('admin.it-tools.dashboard', [
            'days' => $days,
            'total' => $audits->count(),
            'expiredDomains' => $expiredDomains,
            'criticalDomains' => $criticalDomains,
            'warningDomains' => $warningDomains,
            'expiringSsl' => $expiringSsl,
            'offline' => $offline,
            'failed' => $failed,
            'recent' => $audits->take(20),
        ]);
    }
}
