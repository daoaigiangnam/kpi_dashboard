<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PcAuditDeleteController extends Controller
{
    /**
     * Permanently delete one PC Audit and every child inventory/detail record.
     * The Audit Code itself is intentionally kept for future audits.
     */
    public function destroy(PcAudit $pcAudit): RedirectResponse
    {
        $computerName = $pcAudit->computer_name ?: ('Audit #' . $pcAudit->id);

        DB::transaction(function () use ($pcAudit): void {
            $pcAudit->details()->delete();
            $pcAudit->memory()->delete();
            $pcAudit->storage()->delete();
            $pcAudit->monitors()->delete();
            $pcAudit->gpu()->delete();
            $pcAudit->batteries()->delete();
            $pcAudit->network()->delete();
            $pcAudit->antivirus()->delete();
            $pcAudit->bitlocker()->delete();
            $pcAudit->firewall()->delete();
            $pcAudit->licenses()->delete();
            $pcAudit->software()->delete();

            // PcAudit has no SoftDeletes: this is a physical delete.
            $pcAudit->delete();
        });

        return redirect()
            ->route('admin.pc_audit.index')
            ->with('success', "Đã xóa vĩnh viễn PC Audit của máy {$computerName}, bao gồm toàn bộ dữ liệu phần cứng, phần mềm và chi tiết Audit.");
    }
}
