# ERD TỔNG THỂ – UOA SERVER ROOM MANAGEMENT
**Phiên bản:** 0.1 – Thiết kế logic để rà soát, chưa phải migration chính thức  
**Ngày:** 22/11/2026  
**Phạm vi:** PL1–PL5, Admin, Email Queue, Evidence và Audit Center  
**Nhánh:** `feature/ITSMWEB`

## 1. Mục tiêu và nguyên tắc thiết kế

Tài liệu này xác định các thực thể, khóa chính/khóa ngoại và quan hệ dữ liệu trước khi chuẩn hóa MySQL Migration.

Nguyên tắc:
1. MySQL là nguồn dữ liệu chính; không phụ thuộc Google Sheets/Forms khi vận hành.
2. Mỗi phân hệ PL1–PL5 có bảng nghiệp vụ và lịch sử riêng; không gom mọi nghiệp vụ vào một bảng chung.
3. Dùng chung danh mục Server Room, tài khoản quản trị, cấu hình, Email Queue, Evidence và `system_audit_logs`.
4. Lưu snapshot người duyệt/phạm vi áp dụng tại thời điểm phát sinh yêu cầu để thay đổi cấu hình sau này không làm sai lịch sử.
5. Actor bên ngoài (Requester, Assignee, Executor, Inspector...) không bắt buộc phải có tài khoản Admin. Lưu thông tin actor dạng snapshot; chỉ liên kết `users.id` khi actor thực sự có tài khoản.
6. Không xóa hồ sơ nghiệp vụ đã phát sinh. Dùng trạng thái, lịch sử và cờ vô hiệu hóa danh mục.
7. Mọi quyết định/trạng thái chỉ được thay đổi qua Workflow Service; không cho CRUD trực tiếp bỏ qua quy tắc nghiệp vụ.
8. Audit Log tập trung ghi sự kiện; bảng History chuyên biệt vẫn là nguồn chi tiết của từng bước nghiệp vụ.
9. Mọi thời gian lưu DB theo UTC; giao diện hiển thị Asia/Ho_Chi_Minh.
10. Tài liệu này không tự quyết định các câu hỏi nghiệp vụ còn CHỜ XÁC NHẬN trong WORKFLOW_SPECIFICATION.md.

## 2. Nhóm thực thể tổng thể

### 2.1. Dữ liệu nền tảng và phân quyền

| Bảng | Mục đích | Khóa/quan hệ chính |
|---|---|---|
| `users` | Tài khoản đăng nhập Admin | PK `id`; unique `email` |
| `roles` | Ba vai trò Super Admin, UOA, MSTAR | PK `id`; unique `code` |
| `permissions` | Quyền chức năng chi tiết | PK `id`; unique `code` |
| `user_roles` | Gán nhiều vai trò cho tài khoản | PK ghép `user_id, role_id`; FK users, roles |
| `role_permissions` | Ma trận quyền theo vai trò | PK ghép `role_id, permission_id`; FK roles, permissions |
| `server_rooms` | Danh mục phòng Server dùng chung PL1–PL5 | PK `id`; unique `room_code` |
| `room_contacts` | Đầu mối theo phòng: Approver, MSTAR, Manager... | FK `room_id`; user_id nullable; lưu tên/email snapshot |
| `system_settings` | Cấu hình kỹ thuật và cấu hình hệ thống | unique `setting_key`; secret tách kho bảo mật |
| `business_settings` | Cấu hình nghiệp vụ động theo PL/phòng/nhóm | key + scope; kiểu dữ liệu, đơn vị, phạm vi, hiệu lực |
| `setting_change_history` | Lịch sử trước/sau của cấu hình | FK setting; actor; before/after; thời điểm |

**Lưu ý:** Tài liệu nghiệp vụ yêu cầu chỉ có 3 vai trò Admin chính: SUPER_ADMIN, UOA, MSTAR. Requester/Executor/Assignee/Inspector là actor nghiệp vụ, không tạo thêm role Admin.

### 2.2. PL1 – Access Authorization

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl1_access_requests` | Yêu cầu NEW / CHANGE / REVOKE | FK room; approver snapshot; requester snapshot |
| `pl1_approval_decisions` | Lịch sử quyết định duyệt/từ chối | FK access_request; approver actor; channel |
| `pl1_access_records` | Access Register, lưu quyền đã cấp | FK room; tham chiếu request tạo/cập nhật gần nhất |
| `pl1_access_record_history` | Lịch sử thay đổi quyền | FK access_record; request; before/after; actor |
| `pl1_action_tokens` | Token hành động phê duyệt có hash | liên kết đúng request/action; expiry/revoked/used |

Quy tắc quan hệ:
- Một Room có nhiều Access Request và Access Record.
- Một Access Request có nhiều Approval Decision/history, nhưng chỉ một quyết định cuối cùng hợp lệ theo workflow.
- Một Access Record có nhiều dòng history.
- Access Record được nhận diện nghiệp vụ theo Email người được cấp quyền + Room; không dùng email đơn lẻ làm khóa.
- NEW có thể tạo Access Record mới hoặc tái sử dụng dòng REVOKED theo quy tắc đã chốt; CHANGE/REVOKE chỉ tác động bản ghi ACTIVE đúng phòng.
- Trạng thái quyền gồm tối thiểu ACTIVE, REVOKED, EXPIRED; không xóa dòng khi hết hạn/thu hồi.

### 2.3. PL2 – Entry / Check-in / Check-out

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl2_visits` | Hồ sơ đăng ký một lượt vào phòng | FK room; requester snapshot; approver snapshot |
| `pl2_approval_history` | Lịch sử duyệt, từ chối, reminder, expiry | FK visit; actor/channel |
| `pl2_action_tokens` | Token riêng cho APPROVAL, CHECKIN, CHECKOUT | FK visit; lưu hash, action, expiry, used/revoked |
| `pl2_otp_challenges` | Mỗi lần yêu cầu OTP là một challenge | FK visit; hash; sent_at; expires_at; status |
| `pl2_otp_attempts` | Lịch sử từng lần nhập OTP | FK otp_challenge; result; không lưu OTP rõ |
| `pl2_visit_events` | Lịch sử check-in/out và các bước đặc thù | FK visit; actor; event data |
| `evidence_files` | Ảnh/file minh chứng dùng chung | tham chiếu entity qua bảng liên kết |

Quy tắc:
- Một Room có nhiều Visit; một Visit có nhiều Approval History, Action Token, OTP Challenge, OTP Attempt và Visit Event.
- OTP mới vô hiệu hóa challenge/token OTP cũ còn chờ; thời hạn 10 phút chỉ bắt đầu sau khi email gửi thành công.
- Tối đa 5 lần nhập sai cho challenge theo quy tắc đã chốt; không lưu OTP dạng rõ.
- CHECK-IN ghi người thực tế vào phòng và Entry Time; CHECK-OUT chỉ cho Visit đang OPEN, ghi Work Result, Exit Time và minh chứng nếu có.
- Token CHECK-IN/CHECK-OUT phải giới hạn đúng Visit và đúng action; không dùng chung token giữa các bước.

### 2.4. PL3 – Inspection

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl3_inspections` | Phiếu kiểm tra theo phòng/ngày | FK room; inspector snapshot |
| `pl3_checklist_items` | Danh mục I01–I12 | unique item_code |
| `pl3_inspection_results` | Kết quả từng item của phiếu | FK inspection + checklist item |
| `pl3_acknowledgements` | UOA xác nhận đã tiếp nhận hồ sơ | FK inspection; actor/channel/time |
| `pl3_escalation_runs` | Theo dõi chu kỳ cảnh báo thiếu inspection | room + work date; config snapshot |
| `pl3_escalation_events` | Từng cấp cảnh báo SENT/SKIPPED/STOPPED | FK escalation_run; recipient snapshot |
| `calendar_holidays` | Ngày nghỉ áp dụng lịch kiểm tra | ngày; tên; active |
| `pl3_integration_outbox` | Gửi FAIL sang PL4 có retry/idempotency | FK inspection result; event key; trạng thái |

Quy tắc:
- Một Inspection có đúng 12 kết quả checklist theo danh mục đang áp dụng; mỗi item chỉ có một kết quả trong cùng Inspection.
- FAIL bắt buộc có remarks/action.
- Một Inspection có thể có nhiều acknowledgement/escalation events.
- Một Room/ngày làm việc có một chu kỳ kiểm tra/cảnh báo phù hợp với quy tắc idempotency; khóa chính xác cần chốt cùng lịch/tần suất.
- Cấp 1 gửi MSTAR tại DAILY_DEADLINE; cấp 2 Approver, cấp 3 Manager theo cấu hình động; khi phiếu hợp lệ đến thì dừng cảnh báo còn lại.
- PL3 → PL4 dùng duy nhất API ID + token; outbox giữ retry và không tạo ISSUE trùng.

### 2.5. PL4 – Issue Management

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl4_issues` | Hồ sơ sự cố/vi phạm | FK room; source inspection result nullable |
| `pl4_assignments` | Lịch sử phân công/nhận việc | FK issue; assignee snapshot; actor |
| `pl4_work_reports` | Mỗi lần Assignee báo cáo COMPLETED/NOT_COMPLETED | FK issue; actor; result; notes |
| `pl4_uoa_confirmations` | Mỗi lần UOA xác nhận/không xác nhận | FK issue + work_report; UOA actor; reason |
| `pl4_issue_status_history` | Lịch sử chuyển trạng thái | FK issue; from/to; actor; reason |
| `pl4_manual_submissions` | Idempotency/source metadata cho Form thủ công nếu cần | form response ID unique |

Quy tắc:
- ISSUE nguồn PL3 tham chiếu đúng Inspection Result/Item; một FAIL item chỉ sinh tối đa một ISSUE.
- ISSUE MANUAL không có nguồn PL3.
- Một ISSUE có thể có nhiều Assignment, Work Report, UOA Confirmation và Status History.
- COMPLETED và NOT_COMPLETED đều chuyển PENDING_UOA_CONFIRMATION.
- UOA xác nhận → CLOSED; UOA không xác nhận (bắt buộc lý do) → IN_PROGRESS.
- Assignee không có quyền tự đóng ISSUE; mọi lần báo cáo và xác nhận phải lưu riêng, không ghi đè.

### 2.6. PL5 – Work & Maintenance

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl5_work_catalog_categories` | Nhóm danh mục công việc | danh mục |
| `pl5_work_catalog_items` | Catalog công việc, hướng dẫn/risk | FK category |
| `pl5_maintenance_plans` | Kế hoạch bảo trì định kỳ | FK room; catalog item nullable/required theo quy tắc |
| `pl5_work_requests` | Công việc phát sinh/can thiệp hoặc phiếu bảo trì | FK room; requester snapshot; approver snapshot; catalog |
| `pl5_work_approvals` | Lịch sử UOA approve/reject/cancel | FK work request; actor/channel/note |
| `pl5_work_assignments` | Lịch sử giao Executor | FK work request; executor snapshot |
| `pl5_work_execution_events` | START / HOLD / RESUME / SUBMIT_RESULT | FK work request; actor; time; reason/result |
| `pl5_work_verifications` | Lịch sử UOA xác minh PASS/FAIL | FK work request; verifier; result/note |
| `pl5_work_attachments` | Liên kết file minh chứng | FK work request + evidence file |
| `pl5_resources` | Nguồn lực/nhân sự/đơn vị thực hiện nếu cần quản lý danh mục | danh mục, chưa ép thành user Admin |

Quy tắc:
- Work Catalog chỉ Super Admin quản lý.
- UOA và MSTAR xem PL5 Dashboard read-only toàn bộ phòng; quyền xem này không kéo theo quyền thao tác.
- Người yêu cầu được hủy khi PENDING_APPROVAL, bắt buộc lý do.
- Approver và Verifier là UOA theo Room, không phải Requester.
- Workflow: PENDING_APPROVAL → APPROVED/REJECTED/CANCELLED; APPROVED → IN_PROGRESS; HOLD → ON_HOLD; RESUME → IN_PROGRESS; SUBMIT_RESULT → PENDING_VERIFICATION; VERIFY PASS → CLOSED; VERIFY FAIL → IN_PROGRESS + REWORK_REQUIRED.
- Work Approval, Execution, Verification phải lưu lịch sử riêng.

### 2.7. Dịch vụ dùng chung

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `email_queue` | Hàng đợi email nghiệp vụ | module + entity reference; event_key unique |
| `email_queue_attempts` | Lịch sử từng lần gửi | FK email_queue |
| `system_audit_logs` | Audit Log tập trung PL1–PL5 | actor, module, entity, event, channel, correlation |
| `evidence_files` | Metadata file và checksum | owner/uploader; storage key |
| `entity_evidence_links` | Liên kết file với nhiều loại hồ sơ | polymorphic entity reference có kiểm soát |
| `integration_events` | Theo dõi request/response tích hợp ngoài | module, event key, correlation; không lưu secret |
| `idempotency_keys` | Chống xử lý trùng từ Form/API/action callback | scope + key unique; result reference |
| `scheduled_job_runs` | Theo dõi từng lần chạy scheduler/worker | job code, started/finished, outcome |
| `data_exports` | Nhật ký tạo Evidence Package/report export | actor, scope, filters, file/checksum |

## 3. Quan hệ tổng quát (cardinality)

- `server_rooms 1 — N pl1_access_requests`
- `server_rooms 1 — N pl1_access_records`
- `pl1_access_requests 1 — N pl1_approval_decisions`
- `pl1_access_records 1 — N pl1_access_record_history`
- `server_rooms 1 — N pl2_visits`
- `pl2_visits 1 — N pl2_approval_history / pl2_action_tokens / pl2_otp_challenges / pl2_visit_events`
- `pl3_inspections 1 — N pl3_inspection_results`
- `pl3_checklist_items 1 — N pl3_inspection_results`
- `pl3_inspection_results 1 — 0..1 pl4_issues` (nguồn PL3; unique source reference)
- `pl4_issues 1 — N pl4_assignments / pl4_work_reports / pl4_uoa_confirmations / pl4_issue_status_history`
- `server_rooms 1 — N pl5_work_requests / pl5_maintenance_plans`
- `pl5_work_requests 1 — N approvals / assignments / execution_events / verifications / attachments`
- `email_queue 1 — N email_queue_attempts`
- `mọi entity nghiệp vụ 1 — N system_audit_logs` theo entity_type + entity_id và correlation ID
- `evidence_files 1 — N entity_evidence_links`

## 4. Các điểm phải sửa so với schema.sql khởi tạo

Schema hiện tại trong `database/schema.sql` chỉ là skeleton khởi tạo, chưa đủ điều kiện dùng production. Khi thiết kế migration chính thức cần xử lý ít nhất:

1. Đổi `audit_logs` thành thiết kế `system_audit_logs` theo Section 17; bổ sung event_uuid, event_code, result SUCCESS/FAILED, workflow_step, actor_type, action_channel, correlation_id, causation_id, request_id, occurred_at/recorded_at và metadata an toàn.
2. Không dùng một `visits` table chứa duy nhất một OTP hash và một token cho mọi giai đoạn; tách Action Token, OTP Challenge và OTP Attempt để bảo toàn lịch sử và chống replay.
3. Tách `authorized_personnel` thành Access Register có lịch sử thay đổi; một request không nhất thiết tương ứng vĩnh viễn 1:1 với một Access Record vì CHANGE/REVOKE cập nhật quyền hiện có.
4. `email_queue` phải hỗ trợ trạng thái theo nguồn PL1 gồm PENDING/SENDING/RETRY/SENT/FAILED; PL2/PL5 có thể có CANCELLED theo nghiệp vụ. Chuẩn hóa trạng thái dùng chung, không để PROCESSING/SENDING lệch nhau.
5. Tách PL4 Work Report và UOA Confirmation thành nhiều bản ghi; không lưu duy nhất Action_Taken/Resolution_Notes/Approved_By trong bảng incidents.
6. Bổ sung đầy đủ PL5 Approval, Assignment, Execution, Verification, Maintenance Plan, Catalog, Attachments; bảng `work_requests` hiện tại mới chỉ là bản phác thảo.
7. Bổ sung PL3 escalation run/event và integration outbox; không chỉ lưu một trạng thái xác nhận trong inspections.
8. Bổ sung snapshot actor/approver và action channel để hỗ trợ Email/Admin/Public Form; không bắt buộc mọi actor phải là users.
9. Bổ sung idempotency cho Form Response ID, API callback và action link.
10. Tách business settings động khỏi secrets kỹ thuật; ghi setting change history và áp dụng RBAC.
11. Bổ sung Evidence metadata/checksum và bảng liên kết file; không chỉ lưu URL/path không có kiểm soát.
12. Bổ sung PL1 Access Record History và các lịch sử approval/action.
13. Không dùng soft-delete cho hồ sơ nghiệp vụ đã phát sinh; danh mục có thể inactive, dữ liệu lịch sử phải còn truy vết.

## 5. Quy tắc khóa và dữ liệu

- PK nội bộ: `BIGINT UNSIGNED` hoặc UUID/ULID; mã nghiệp vụ hiển thị riêng như ACCESS-..., VISIT-..., INSP-..., ISSUE-..., WORK-....
- Email chuẩn hóa lowercase + trim trước khi so sánh; giữ giá trị gốc đã nhận nếu cần bằng chứng.
- Unique Form Response ID theo từng PL/source; unique PL3 source key cho PL4; unique event_key cho email queue.
- FK dùng InnoDB; xóa phòng/danh mục đã được sử dụng phải bị chặn hoặc chỉ chuyển INACTIVE.
- Các trạng thái nên dùng VARCHAR + CHECK/lookup tùy phiên bản MySQL và chiến lược migration; workflow service vẫn phải xác thực state transition.
- Lưu snapshot tên/email/phòng/approver tại thời điểm phát sinh để lịch sử không đổi khi danh mục được cập nhật.
- Token lưu hash, có token purpose, entity binding, expiry, used_at, revoked_at; không log raw token.
- File lưu ngoài webroot/object storage; DB chỉ lưu storage key, MIME, size, checksum SHA-256, uploader, created_at.
- Không lưu OTP, password, API secret dạng rõ trong DB/log.

## 6. Những quyết định ERD chưa được tự ý chốt

Các mục dưới đây phải đối chiếu câu hỏi còn mở trong WORKFLOW_SPECIFICATION.md và xác nhận trước khi tạo migration:
- PL1 Valid Until tính hết ngày hay hết thời điểm; thời điểm/cadence scheduler chuyển EXPIRED.
- PL1 approval token expiry thực tế/cấu hình có cần giữ chính xác như Apps Script hay thay đổi.
- PL2 thời hạn CHECK-IN sau khi APPROVED và cơ chế xử lý không check-in.
- PL3 một chu kỳ inspection/escalation được khóa theo Room + ngày hay Room + tần suất + kỳ; xử lý Weekly/Monthly.
- PL3 xác minh chỉ một trigger API PL3 → PL4 đang được dùng.
- PL4 email queue/retry hiện tại và yêu cầu tương thích.
- Các trường live Form chưa xác minh trực tiếp; phải đối chiếu workbook + Apps Script trước khi migration.
- Retention, privacy, IP/User-Agent, WORM/signature và partitioning cho Audit/Evidence.
- Cấu trúc actor identity: có cần danh mục external contacts riêng hay chỉ snapshot trên từng hồ sơ.
- Phạm vi liên kết file và thời hạn lưu Evidence theo từng PL.

## 7. Trạng thái

**ERD logic v0.1 – DRAFT, chưa phê duyệt triển khai.**  
Đây là bản đồ thực thể và quan hệ để rà soát. Chưa thay thế `database/schema.sql`, chưa tạo migration, chưa tạo PHP nghiệp vụ. Sau khi rà soát các quan hệ và chốt các điểm chặn, mới chuyển thành ERD vật lý, Data Dictionary, rồi Migration theo từng nhóm.
