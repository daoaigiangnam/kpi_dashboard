<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitored_servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id')->unique();
            $table->string('name', 150);
            $table->string('hostname', 190)->nullable();
            $table->string('server_code', 80)->unique();
            $table->char('token_hash', 64);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('agent_version', 40)->nullable();
            $table->json('os_info')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('audit_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('agent_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->cascadeOnDelete();
            $table->uuid('command_id')->unique();
            $table->string('type', 40);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('command_audit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->cascadeOnDelete();
            $table->uuid('session_id')->unique();
            $table->string('status', 20)->default('queued')->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedBigInteger('event_count')->default(0);
            $table->string('report_path', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('command_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->cascadeOnDelete();
            $table->uuid('session_id')->index();
            $table->string('event_key', 190);
            $table->timestamp('event_time')->index();
            $table->string('username', 190)->nullable();
            $table->unsignedInteger('uid')->nullable();
            $table->unsignedInteger('auid')->nullable();
            $table->unsignedBigInteger('pid')->nullable();
            $table->unsignedBigInteger('ppid')->nullable();
            $table->text('executable')->nullable();
            $table->json('arguments')->nullable();
            $table->string('audit_serial', 100)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->json('raw_event')->nullable();
            $table->timestamp('created_at');
            // An audit serial/event key may be reused in a later session.
            $table->unique(['server_id', 'session_id', 'event_key']);
            $table->index(['server_id', 'session_id', 'event_time']);
        });

        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('user_groups')
            && Schema::hasTable('group_permissions')
            && Schema::hasColumn('permissions', 'code')
            && Schema::hasColumn('permissions', 'module')
            && Schema::hasColumn('permissions', 'name')
            && Schema::hasColumn('group_permissions', 'user_group_id')
            && Schema::hasColumn('group_permissions', 'permission_id')
        ) {
            $permissions = [
                ['Server Monitoring', 'View Servers', 'server_monitoring.view'],
                ['Server Monitoring', 'Manage Servers', 'server_monitoring.manage'],
                ['Server Monitoring', 'Control Command Audit', 'server_monitoring.audit'],
            ];

            foreach ($permissions as [$module, $name, $code]) {
                DB::table('permissions')->updateOrInsert(
                    ['code' => $code],
                    ['module' => $module, 'name' => $name, 'description' => $name.' permission',
                        'created_at' => now(), 'updated_at' => now()]
                );

                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                if ($permissionId && Schema::hasColumn('user_groups', 'name')) {
                    $groupIds = DB::table('user_groups')
                        ->whereIn('name', ['Super Admin', 'KPI Admin'])
                        ->pluck('id');

                    foreach ($groupIds as $groupId) {
                        DB::table('group_permissions')->updateOrInsert([
                            'user_group_id' => $groupId,
                            'permission_id' => $permissionId,
                        ], []);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately retain RBAC rows: some may have existed before this
        // migration or may have been assigned manually after deployment.
        // Removing them here could revoke permissions unrelated to rollback.
        Schema::dropIfExists('command_audit_events');
        Schema::dropIfExists('command_audit_sessions');
        Schema::dropIfExists('agent_commands');
        Schema::dropIfExists('monitored_servers');
    }
};
