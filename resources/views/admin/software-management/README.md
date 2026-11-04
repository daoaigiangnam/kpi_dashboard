# Software Management Validation Notes

Run on a staging copy of the application.

- [ ] `php artisan migrate --pretend`
- [ ] `php artisan migrate`
- [ ] `php artisan db:seed --class="Database\\Seeders\\SoftwareManagementPermissionSeeder"`
- [ ] Assign Software Management permissions to relevant groups.
- [ ] `php artisan route:list --path=admin/software-management`
- [ ] `php artisan view:cache` then `php artisan view:clear`
- [ ] Run `php artisan test --filter=SoftwareManagementWorkflowTest`
- [ ] Confirm navigation appears for authorized users only.
- [ ] Test HOD approval, IT assessment, Borrow, Buy, installation, acceptance, closure checklist, exception approval, attachments and license catalog.
- [ ] Verify KPI routes and route cache still work.

This module is on a separate feature branch. No production deployment or merge has been performed.
