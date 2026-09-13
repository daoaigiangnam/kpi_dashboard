<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
use Illuminate\Http\Request;

class ServiceCustomerAlertRecipientController extends Controller
{
    public function edit(ServiceCustomer $serviceCustomer)
    {
        $serviceCustomer->load('alertRecipients');
        $recipients = $serviceCustomer->alertRecipients->keyBy('level');

        return view('admin.service-customers.alert-recipients', compact('serviceCustomer', 'recipients'));
    }

    public function update(Request $request, ServiceCustomer $serviceCustomer)
    {
        $data = $request->validate([
            'recipients' => ['nullable', 'array'],
            'recipients.*.name' => ['nullable', 'string', 'max:150'],
            'recipients.*.email' => ['nullable', 'email', 'max:190'],
            'recipients.*.phone' => ['nullable', 'string', 'max:50'],
            'recipients.*.is_active' => ['nullable', 'boolean'],
        ]);

        $kept = [];
        foreach (range(1, 4) as $level) {
            $row = $data['recipients'][$level] ?? [];
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $active = !empty($row['is_active']);

            if ($name === '' && $email === '' && $phone === '') {
                continue;
            }

            $serviceCustomer->alertRecipients()->updateOrCreate(
                ['level' => $level],
                [
                    'recipient_name' => $name !== '' ? $name : $serviceCustomer->name,
                    'recipient_email' => $email,
                    'recipient_phone' => $phone !== '' ? $phone : null,
                    'is_active' => $active,
                ]
            );
            $kept[] = $level;
        }

        if ($kept === []) {
            $serviceCustomer->alertRecipients()->delete();
        } else {
            $serviceCustomer->alertRecipients()->whereNotIn('level', $kept)->delete();
        }

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer alert recipients updated.');
    }
}
