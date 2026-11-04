<?php

namespace App\Providers;

use App\Http\Controllers\Admin\ServiceController;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ItTools\NetworkMonitoringService;
use App\Services\ItTools\WebsiteNetworkMonitoringService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ServiceController keeps the existing NetworkMonitoringService API,
        // but Website SSL detection is handled by the implementation that
        // reuses SslAuditService (the same engine used by Check Domain).
        $this->app->when(ServiceController::class)
            ->needs(NetworkMonitoringService::class)
            ->give(fn () => app(WebsiteNetworkMonitoringService::class));
    }

    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
        Gate::define('access-admin', fn (User $user) => $user->isSuperAdmin() || $user->hasPermission('admin.view'));

        $this->applyDatabaseMailSettings();
    }

    private function applyDatabaseMailSettings(): void
    {
        try {
            if (!Schema::hasTable('system_settings')) return;

            $settings = SystemSetting::query()
                ->whereIn('key', ['mail.mailer','mail.host','mail.port','mail.encryption','mail.username','mail.password','mail.from_address','mail.from_name'])
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value])
                ->all();

            if (!empty($settings['mail.mailer'])) config(['mail.default' => $settings['mail.mailer']]);
            if (!empty($settings['mail.host'])) config(['mail.mailers.smtp.host' => $settings['mail.host']]);
            if (!empty($settings['mail.port'])) config(['mail.mailers.smtp.port' => (int)$settings['mail.port']]);

            $encryption = $settings['mail.encryption'] ?? 'tls';
            config([
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $settings['mail.username'] ?? null,
                'mail.mailers.smtp.password' => $settings['mail.password'] ?? null,
                'mail.from.address' => $settings['mail.from_address'] ?? config('mail.from.address'),
                'mail.from.name' => $settings['mail.from_name'] ?? config('mail.from.name'),
            ]);
        } catch (\Throwable) {}
    }
}
