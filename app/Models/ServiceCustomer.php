<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','contact_name','email','phone','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function alertRecipients()
    {
        return $this->hasMany(ServiceCustomerAlertRecipient::class, 'customer_id')->orderBy('level');
    }

    protected static function booted(): void
    {
        static::saved(function (ServiceCustomer $customer) {
            if (!app()->bound('request') || !request()->has('alert_recipients')) {
                return;
            }

            $rows = request()->input('alert_recipients', []);
            foreach (range(1, 4) as $level) {
                $row = $rows[$level] ?? [];
                $name = trim((string) ($row['name'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $phone = trim((string) ($row['phone'] ?? ''));
                $active = !empty($row['is_active']) && $name !== '' && $email !== '';

                $customer->alertRecipients()->updateOrCreate(
                    ['level' => $level],
                    [
                        'recipient_name' => $name !== '' ? $name : null,
                        'recipient_email' => $email !== '' ? $email : null,
                        'recipient_phone' => $phone !== '' ? $phone : null,
                        'is_active' => $active,
                    ]
                );
            }
        });
    }
}
