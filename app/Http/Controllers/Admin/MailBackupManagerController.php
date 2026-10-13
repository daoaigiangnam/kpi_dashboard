<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MailBackupAuditLog;
use App\\Models\\MailBackupBatch;
use App\\Models\\MailBackupJob;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class MailBackupManagerController extends Controller
{
    public function index()
    {
        $batches = MailBackupBatch::with('creator')->latest()->paginate(20);
        $summary = [
            'batches' => MailBackupBatch::count(),
            'jobs' => MailBackupJob::count(),
            'pending' => MailBackupJob::whereIn('status', ['PENDING', 'RETRY_WAIT'])->count(),
            'running' => MailBackupJob::whereIn('status', ['RUNNING', 'VERIFYING'])->count(),
            'completed' => MailBackupJob::where('status', 'COMPLETED')->count(),
            'failed' => MailBackupJob::where('status', 'FAILED')->count(),
        ];
        return view('admin.mail-backup.index', compact('batches', 'summary'));
    }

    public function create()
    {
        return view('admin.mail-backup.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'mail_scope' => ['required', 'in:all,inbox,sent'],
            'accounts_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['accounts_csv']->getRealPath(), 'rb');
        if (!$handle) throw ValidationException::withMessages(['accounts_csv' => 'Không đọc được CSV.']);
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw ValidationException::withMessages(['accounts_csv' => 'CSV trống.']);
        }
        $header[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($v) => strtolower(trim((string) $v)), $header);
        $emailIndex = array_search('email', $header, true);
        $nameIndex = array_search('display_name', $header, true);
        if ($emailIndex === false) {
            fclose($handle);
            throw ValidationException::withMessages(['accounts_csv' => 'CSV bắt buộc có cột email; display_name là tùy chọn.']);
        }

        $accounts = [];
        $issues = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            if (++$line > 5001) {
                fclose($handle);
                throw ValidationException::withMessages(['accounts_csv' => 'CSV chỉ được tối đa 5.000 dòng dữ liệu.']);
            }
            $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));
            $displayName = $nameIndex === false ? '' : trim((string) ($row[$nameIndex] ?? ''));
            if ($email === '' && $displayName === '') continue;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                $issues[] = "Dòng {$line}: email không hợp lệ.";
                continue;
            }
            if (isset($accounts[$email])) {
                $issues[] = "Dòng {$line}: email {$email} bị trùng; giữ dòng đầu tiên.";
                continue;
            }
            $accounts[$email] = mb_substr($displayName, 0, 190);
        }
        fclose($handle);
        if (!$accounts) throw ValidationException::withMessages(['accounts_csv' => 'Không có tài khoản email hợp lệ.']);

        $batch = DB::transaction(function () use ($data, $accounts) {
            $code = 'MB-' . now()->format('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $batch = MailBackupBatch::create([
                'batch_code' => $code, 'name' => $data['name'],
                'date_from' => $data['date_from'], 'date_to' => $data['date_to'],
                'mail_scope' => $data['mail_scope'], 'status' => 'PENDING',
                'created_by' => auth()->id(),
            ]);
            foreach ($accounts as $email => $displayName) {
                $batch->jobs()->create(['email' => $email, 'display_name' => $displayName, 'status' => 'PENDING']);
            }
            $batch->refreshCounters();
            return $batch;
        });

        $this->audit('BATCH_IMPORTED', 'batch', $batch->id, [
            'batch_code' => $batch->batch_code, 'valid_accounts' => count($accounts),
            'skipped_rows' => count($issues), 'date_from' => $batch->date_from->toDateString(),
            'date_to' => $batch->date_to->toDateString(), 'scope' => $batch->mail_scope,
        ]);

        $message = 'Đã tạo batch ' . $batch->batch_code . ' với ' . count($accounts) . ' tài khoản.';
        if ($issues) $message .= ' Đã bỏ qua ' . count($issues) . ' dòng lỗi/trùng.';
        return redirect()->route('admin.mail_backup.show', $batch)->with('success', $message)->with('import_issues', array_slice($issues, 0, 100));
    }

    public function show(MailBackupBatch $batch)
    {
        $batch->load(['creator', 'jobs' => fn ($q) => $q->orderBy('id')]);
        return view('admin.mail-backup.show', compact('batch'));
    }

    public function job(MailBackupJob $job)
    {
        $job->load(['batch', 'logs']);
        return view('admin.mail-backup.job', compact('job'));
    }

    public function retryJob(Request $request, MailBackupJob $job)
    {
        $job = DB::transaction(function () use ($job) {
            $locked = MailBackupJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'FAILED') {
                throw ValidationException::withMessages(['job' => 'Chỉ được retry job có trạng thái FAILED.']);
            }
            $locked->update([
                'status' => 'PENDING', 'attempt_count' => $locked->attempt_count + 1,
                'last_error' => null, 'processed_messages' => 0, 'expected_messages' => null,
                'pst_path' => null, 'pst_size' => null, 'started_at' => null,
                'finished_at' => null, 'worker_id' => null, 'locked_at' => null,
            ]);
            $locked->logs()->create([
                'attempt_no' => $locked->attempt_count, 'level' => 'INFO',
                'message' => 'Job được đưa lại vào queue bởi người dùng #' . auth()->id(),
            ]);
            $locked->batch->refreshCounters();
            return $locked;
        });
        $this->audit('RETRY_JOB', 'job', $job->id, ['email' => $job->email]);
        return back()->with('success', 'Đã đưa job về queue. Job hoàn tất không bị chạy lại.');
    }

    public function retryFailed(Request $request, MailBackupBatch $batch)
    {
        $request->validate(['confirm_retry' => ['required', 'accepted']]);

        $retried = DB::transaction(function () use ($batch) {
            $jobs = MailBackupJob::where('batch_id', $batch->id)->where('status', 'FAILED')->lockForUpdate()->get();
            foreach ($jobs as $job) {
                $job->update([
                    'status' => 'PENDING', 'attempt_count' => $job->attempt_count + 1,
                    'last_error' => null, 'processed_messages' => 0, 'expected_messages' => null,
                    'pst_path' => null, 'pst_size' => null, 'started_at' => null,
                    'finished_at' => null, 'worker_id' => null, 'locked_at' => null,
                ]);
                $job->logs()->create(['attempt_no' => $job->attempt_count, 'level' => 'INFO', 'message' => 'Retry batch bởi user #' . auth()->id()]);
            }
            $batch->refreshCounters();
            return $jobs->count();
        });
        $this->audit('RETRY_FAILED_JOBS', 'batch', $batch->id, ['retried_jobs' => $retried]);
        return back()->with('success', "Đã đưa {$retried} job FAILED về queue.");
    }

    private function audit(string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        MailBackupAuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action, 'entity_type' => $entityType,
            'entity_id' => $entityId, 'result' => 'SUCCESS', 'details' => $details, 'ip_address' => request()->ip(),
        ]);
    }
}
