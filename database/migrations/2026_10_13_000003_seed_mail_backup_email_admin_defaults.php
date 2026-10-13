<?php

use App\Models\MailBackupSettingDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defs = [
            ['mail.gmail.domain', 'Google Workspace Domain', 'Google Workspace', 'text', '', 'Domain áp dụng cho các tài khoản cần backup.', false, true, 10],
            ['mail.google.service_account_json_path', 'Service Account JSON Path', 'Google Workspace', 'text', '', 'Đường dẫn trên máy chủ tới file JSON; không upload khóa qua trình duyệt.', false, true, 20],
            ['mail.google.service_account_private_key', 'Service Account Private Key (optional)', 'Google Workspace', 'textarea', '', 'Chỉ dùng nếu quản trị viên chủ động nhập key trực tiếp; khuyến nghị để trống và dùng file JSON ngoài web root.', true, false, 25],
            ['mail.google.delegated_admin_email', 'Delegated Admin Email', 'Google Workspace', 'email', '', 'Tài khoản quản trị Workspace được dùng để ủy quyền.', false, true, 30],
            ['mail.google.read_scope', 'Gmail Read Scope', 'Google Workspace', 'text', 'https://www.googleapis.com/auth/gmail.readonly', 'Scope cần cấp trong Domain-Wide Delegation cho giai đoạn backup.', false, true, 40],
            ['mail.backup.storage_path', 'PST / Staging Storage Path', 'Backup', 'text', '/srv/mail-backup-manager/storage', 'Thư mục ngoài web root để lưu dữ liệu tạm và PST.', false, true, 50],
            ['mail.backup.max_accounts_per_batch', 'Maximum Accounts per Batch', 'Backup', 'number', '5000', 'Giới hạn số tài khoản trong một batch.', false, true, 60],
            ['mail.backup.max_retry_attempts', 'Maximum Retry Attempts', 'Backup', 'number', '3', 'Số lần retry tối đa sau lỗi tạm thời.', false, true, 70],
            ['mail.backup.cleanup_source_enabled', 'Allow Gmail Source Cleanup', 'Retention', 'boolean', '0', 'Tắt mặc định. Chỉ bật sau khi xác minh manifest PST và có quy trình phê duyệt riêng.', false, false, 80],
        ];

        foreach ($defs as [$key, $label, $group, $type, $value, $description, $secret, $required, $sort]) {
            MailBackupSettingDefinition::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $label, 'group' => $group, 'type' => $type,
                    'value' => $value, 'description' => $description,
                    'is_secret' => $secret, 'is_required' => $required,
                    'is_active' => true, 'sort_order' => $sort,
                ]
            );
        }
    }

    public function down(): void
    {
        MailBackupSettingDefinition::whereIn('key', [
            'mail.gmail.domain',
            'mail.google.service_account_json_path',
            'mail.google.service_account_private_key',
            'mail.google.delegated_admin_email',
            'mail.google.read_scope',
            'mail.backup.storage_path',
            'mail.backup.max_accounts_per_batch',
            'mail.backup.max_retry_attempts',
            'mail.backup.cleanup_source_enabled',
        ])->delete();
    }
};
