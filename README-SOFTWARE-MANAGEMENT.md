# Software Installation Management (QP-HRD-16)

Module under development for the KPI Dashboard Laravel application. This implementation follows the supplied QP-HRD-16, FR-16-01 through FR-16-06, and REG-16-01 source documents.

## Current scope
- Electronic request form based on FR-16-01.
- HOD approval/reject/return.
- IT technical, security, and license assessment (FR-16-02).
- Borrow / Buy workflow, with a purchase approval gate for Buy.
- Installation record and user acceptance (FR-16-04).
- Close-ticket checklist (FR-16-05) with server-side blocking checks.
- Exception request and approval (FR-16-06).
- Software and license catalog (REG-16-01).
- Private local evidence uploads/downloads, guarded by permissions.
- Dashboard counters and request search/filter.

## Branch
`feature/software-installation-management` (branched from `feature/admin-foundation`).

## Before testing
1. Back up the database and application.
2. Deploy this branch to a staging environment first; do not point it directly at production before review.
3. Run `php artisan migrate --pretend` to inspect the new migration.
4. Run `php artisan migrate` to create the software management tables.
5. Run `php artisan db:seed --class=Database\\Seeders\\SoftwareManagementPermissionSeeder` to add the module permissions.
6. Assign the relevant permissions to User Groups through the existing permission administration UI.
7. Run `php artisan route:list --path=admin/software-management` and confirm route names.
8. Run the test suite and conduct the UAT scenarios below.

## Key routes
- `/admin/software-management` - dashboard and request list
- `/admin/software-management/create` - request form
- `/admin/software-management/{id}` - workflow detail
- `/admin/software-management/licenses` - software/license catalog

## Important controls
- No installation step is available until the request reaches `approved_for_installation`.
- Requesters cannot approve their own HOD or purchase request.
- Only the assigned HOD or a user with explicit approval permission can approve HOD need.
- Buy requests require purchase approval before installation.
- Borrow requests require recorded evidence of lawful entitlement.
- Ticket closure is blocked unless the final checklist, IT assessment, installation checks, user acceptance, and branch-specific approval/evidence requirements pass.
- Passwords, product keys, access tokens, and other secrets must never be put in ticket comments or license-evidence text.
- Uploaded evidence is stored on the local private disk. Confirm filesystem permissions, backup coverage, retention rules, and download authorization before production use.

## UAT minimum
1. Request submitted with missing required fields is rejected by validation.
2. HOD can approve, reject, or return a request; requester cannot approve their own request.
3. Non-authorized user receives 403 when attempting assessment, purchase approval, installation, exception approval, license administration, or closure.
4. Borrow request without lawful entitlement evidence cannot be approved by IT.
5. Buy request cannot move to installation until purchase approval.
6. Installation result must be recorded before acceptance.
7. Ticket cannot close with missing checklist items, failed checks, missing user acceptance, or missing branch-specific evidence/approval.
8. Attachment upload limits/type validation and private download permissions are enforced.
9. License entries nearing expiry appear on the dashboard and catalog.
10. Existing KPI dashboard, admin routes, and route cache remain functional.

## Limitations to verify before production
- This is an initial application slice, not a formal approval of UOA's approval matrix. Confirm approval limits, workflow authorities, record retention and document controller decisions with UOA.
- Email notifications, robust append-only audit logging, purchase document completeness, automated expiry alerts, PDF export and deeper ticket integration remain to be completed or validated.
- The browser connector cannot execute Laravel/PHP tests. Validate syntax, migrations, route registration, permissions and UI in staging before merging/deploying.
