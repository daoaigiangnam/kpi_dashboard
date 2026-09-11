<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItToolAudit;
use Illuminate\Http\Request;

class ItToolsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = ItToolAudit::query()->latest();
        $days = max(1, min((int) $request->input('days', 30), 365));
        $since = now()->subDays($days);

        $audits = (clone $query)->where('created_at', '>=', $since)->get();
        $expiredDomains = $audits->filter(function ($audit) {
            return is_numeric(data_get($audit->result, 'domain_audit.days_remaining'))
                && (int) data_get($audit->result, 'domain_audit.days_remaining') <= 30;
        })->count();
        $expiringSsl = $audits->filter(function ($audit) {
            return is_numeric(data_get($audit->result, 'ssl_audit.days_remaining'))
                && (int) data_get($audit->result, 'ssl_audit.days_remaining') <= 30;
        })->count();
        $offline = $audits->filter(fn ($audit) => data_get($audit->result, 'website_audit.https.online') === false)->count();
        $failed = $audits->where('status', '!=', 'completed')->count();

        return view('admin.it-tools.dashboard', [
            'days' => $days,
            'total' => $audits->count(),
            'expiredDomains' => $expiredDomains,
            'expiringSsl' => $expiringSsl,
            'offline' => $offline,
            'failed' => $failed,
            'recent' => $audits->take(20),
        ]);
    }
}
