# DATA DICTIONARY – UOA SERVER ROOM MANAGEMENT
**Phiên bản:** 0.3 – Đã áp dụng ADR-001; dự thảo cấu trúc vật lý  
**CSDL mục tiêu:** MySQL 8.0+, InnoDB, utf8mb4  
**Múi giờ lưu trữ:** UTC; giao diện Asia/Ho_Chi_Minh  
**Nhánh:** `feature/ITSMWEB`  
**Trạng thái:** DRAFT – chưa phải Migration triển khai

> Tài liệu này cụ thể hóa ERD logic. Các cột có hậu tố `_snapshot` giữ lại thông tin tại thời điểm phát sinh, không tự cập nhật khi danh mục thay đổi. Mọi actor bên ngoài có thể không có tài khoản Admin; các cột `actor_user_id` vì vậy được phép NULL.

## 1. Quy ước dữ liệu

| Quy ước | Chuẩn |
|---|---|
| PK | `BIGINT UNSIGNED AUTO_INCREMENT` |
| Mã nghiệp vụ | `VARCHAR(40–60)`, UNIQUE, không dùng làm FK nội bộ |
| Email | `VARCHAR(190)`; chuẩn hóa lowercase + trim khi so sánh |
| Ngày | `DATE`; thời điểm | `DATETIME(3)` UTC |
| Tiền/số đo | `DECIMAL` theo độ chính xác nghiệp vụ; không dùng FLOAT |
| Trạng thái | `VARCHAR(30)` + CHECK/validation trong Workflow Service |
| JSON | Chỉ dùng cho metadata linh hoạt; dữ liệu quan hệ phải có cột riêng |
| File | Lưu ngoài webroot; DB lưu storage key, MIME, size, SHA-256 |
| Xóa | Không hard-delete hồ sơ nghiệp vụ; danh mục dùng `is_active` |
| Snapshot | Tên/email/phòng/người duyệt tại thời điểm gửi yêu cầu |

Các cột chuẩn áp dụng khi phù hợp:
- `created_at DATETIME(3) NOT NULL`
- `updated_at DATETIME(3) NOT NULL`
- `created_by_user_id BIGINT UNSIGNED NULL` chỉ cho thao tác do tài khoản Admin thực hiện.
- Không tự thêm `deleted_at` vào hồ sơ PL1–PL5.

## 2. Bảng nền tảng và phân quyền

### 2.1 `users`

| Cột | Kiểu | Null | Ràng buộc / ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| name | VARCHAR(160) | NO | Tên hiển thị |
| email | VARCHAR(190) | NO | UNIQUE; lowercase |
| password_hash | VARCHAR(255) | NO | Hash do password_hash() tạo |
| status | VARCHAR(20) | NO | ACTIVE / DISABLED / LOCKED |
| last_login_at | DATETIME(3) | YES | Lần đăng nhập gần nhất |
| password_changed_at | DATETIME(3) | YES | Theo dõi thay đổi mật khẩu |
| created_at, updated_at | DATETIME(3) | NO | Thời gian UTC |

### 2.2 `roles`, `permissions`, `user_roles`, `role_permissions`

- `roles(id, code UNIQUE, name, description, created_at)`; seed ba mã `SUPER_ADMIN`, `UOA`, `MSTAR`.
- `permissions(id, code UNIQUE, name, description)`; quyền chi tiết theo module/action.
- `user_roles(user_id FK users, role_id FK roles, assigned_at, assigned_by_user_id NULL)`; PK ghép `(user_id, role_id)`.
- `role_permissions(role_id FK roles, permission_id FK permissions, granted_at)`; PK ghép `(role_id, permission_id)`.
- Không tạo role Admin cho Requester, Executor, Assignee hoặc Inspector.

### 2.3 `server_rooms`

| Cột | Kiểu | Null | Ràng buộc / ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| room_code | VARCHAR(40) | NO | UNIQUE, mã phòng ổn định |
| room_name | VARCHAR(160) | NO | Tên phòng |
| location | VARCHAR(255) | YES | Vị trí |
| status | VARCHAR(20) | NO | ACTIVE / INACTIVE |
| notes | TEXT | YES | Ghi chú |
| created_at, updated_at | DATETIME(3) | NO | UTC |

Không xóa phòng đã có dữ liệu liên quan; chỉ chuyển INACTIVE.

### 2.5 `business_entities` – Định danh chung hồ sơ nghiệp vụ (Supertype)

| Cột | Kiểu | Null | Ràng buộc / ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK; định danh chung |
| module_code | VARCHAR(10) | NO | PL1 / PL2 / PL3 / PL4 / PL5 |
| entity_type | VARCHAR(60) | NO | ACCESS_REQUEST / ACCESS_RECORD / VISIT / INSPECTION / ISSUE / WORK_REQUEST |
| business_code | VARCHAR(60) | NO | Mã nghiệp vụ của hồ sơ tương ứng |
| created_at | DATETIME(3) | NO | UTC; thời điểm đăng ký định danh |

UNIQUE `(module_code, entity_type, business_code)`; INDEX `(module_code, entity_type, id)`. Bảng này là supertype định danh. Mỗi bảng hồ sơ gốc trong phạm vi có thêm `business_entity_id BIGINT UNSIGNED NOT NULL UNIQUE FK business_entities(id)`. PK `id` hiện có của bảng PL được giữ lại; không đổi PK sang `business_entity_id` trong ADR này.

**Phạm vi subtype:** `pl1_access_requests`, `pl1_access_records`, `pl2_visits`, `pl3_inspections`, `pl4_issues`, `pl5_work_requests`. Các bảng con/lịch sử không có registry riêng; Audit/Evidence của chúng liên kết về hồ sơ gốc tương ứng. Mở rộng phạm vi phải được ghi nhận trong ERD trước Migration.

**Toàn vẹn dữ liệu:** tạo supertype và subtype trong cùng transaction; rollback cả hai nếu một bước thất bại. UNIQUE FK ngăn một business_entity gắn nhiều dòng trong cùng bảng subtype. Service phải kiểm tra entity_type/module_code khớp subtype; FK không tự bảo đảm subtype tồn tại cho mọi dòng cha hoặc loại subtype khớp. Không xóa cứng định danh đã phát sinh Audit/Evidence.

### 2.4 `room_contacts`

| Cột | Kiểu | Null | Ràng buộc / ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| room_id | BIGINT UNSIGNED | NO | FK server_rooms |
| contact_type | VARCHAR(30) | NO | APPROVER / MSTAR / MANAGER |
| user_id | BIGINT UNSIGNED | YES | FK users nếu có tài khoản |
| contact_name | VARCHAR(160) | NO | Snapshot tên |
| contact_email | VARCHAR(190) | NO | Email nhận thông báo |
| is_primary | TINYINT(1) | NO | Mặc định 0 |
| is_active | TINYINT(1) | NO | Mặc định 1 |
| valid_from, valid_until | DATETIME(3) | YES | Khoảng hiệu lực nếu cần |
| created_at, updated_at | DATETIME(3) | NO | UTC |

INDEX `(room_id, contact_type, is_active)`. Cần bảo đảm chỉ có một primary contact hiệu lực cho từng loại/phòng bằng transaction/service; không chỉ dựa vào UNIQUE đơn giản nếu có lịch sử thời gian.

## 3. PL1 – Access Authorization

### 3.1 `pl1_access_requests`

| Cột | Kiểu | Null | Ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| business_entity_id | BIGINT UNSIGNED | NO | UNIQUE FK business_entities(id); định danh supertype của hồ sơ gốc |
| request_code | VARCHAR(50) | NO | UNIQUE |
| request_type | VARCHAR(20) | NO | NEW / CHANGE / REVOKE |
| room_id | BIGINT UNSIGNED | NO | FK server_rooms |
| person_name | VARCHAR(160) | NO | Người được cấp quyền |
| person_email | VARCHAR(190) | NO | Email chuẩn hóa |
| organization | VARCHAR(160) | YES | Đơn vị |
| role_reason | VARCHAR(255) | NO | Vai trò/lý do |
| access_level | VARCHAR(30) | NO | Loại/phạm vi quyền |
| valid_from, valid_until | DATE | YES | Thời hạn quyền |
| requester_name | VARCHAR(160) | NO | Người đề xuất |
| requester_email | VARCHAR(190) | NO | Email người đề xuất |
| approver_name_snapshot | VARCHAR(160) | NO | Người duyệt tại thời điểm gửi |
| approver_email_snapshot | VARCHAR(190) | NO | Email người duyệt tại thời điểm gửi |
| approval_status | VARCHAR(30) | NO | PENDING / APPROVED / REJECTED / EXPIRED / CANCELLED nếu policy cho phép |
| source_response_id | VARCHAR(190) | YES | UNIQUE theo nguồn Form; nullable |
| submitted_at | DATETIME(3) | NO | Thời điểm gửi |
| decided_at | DATETIME(3) | YES | Thời điểm quyết định |
| created_at, updated_at | DATETIME(3) | NO | UTC |

INDEX `(room_id, person_email, approval_status)`, `(approval_status, submitted_at)`. Không đặt UNIQUE trực tiếp trên `source_response_id` nếu cần nhiều nguồn; dùng unique theo source_system + response_id trong idempotency_keys.

### 3.2 `pl1_approval_decisions`

- `id BIGINT PK`, `access_request_id FK`, `decision VARCHAR(20)` (APPROVED/REJECTED), `approver_user_id NULL FK users`, `approver_name_snapshot`, `approver_email_snapshot`, `channel VARCHAR(30)` (EMAIL_APPROVAL/ADMIN), `note TEXT NULL`, `decided_at DATETIME(3)`, `created_at`.
- INDEX `(access_request_id, decided_at)`. Mỗi request chỉ có một quyết định cuối cùng thành công; mọi lần thử bị từ chối ghi Audit Log.

### 3.3 `pl1_access_records`

| Cột | Kiểu | Null | Ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| business_entity_id | BIGINT UNSIGNED | NO | UNIQUE FK business_entities(id); định danh supertype của hồ sơ gốc |
| room_id | BIGINT UNSIGNED | NO | FK server_rooms |
| person_name | VARCHAR(160) | NO | Người có quyền |
| person_email | VARCHAR(190) | NO | Email |
| organization | VARCHAR(160) | YES | Đơn vị |
| role_reason | VARCHAR(255) | YES | Vai trò/lý do |
| access_level | VARCHAR(30) | NO | Quyền |
| valid_from, valid_until | DATE | YES | Thời hạn |
| record_status | VARCHAR(20) | NO | ACTIVE / REVOKED / EXPIRED |
| created_from_request_id | BIGINT UNSIGNED | NO | FK request cấp quyền gần nhất |
| last_changed_by_request_id | BIGINT UNSIGNED | YES | FK request thay đổi gần nhất |
| approved_by_name_snapshot | VARCHAR(160) | YES | Người duyệt |
| approved_at | DATETIME(3) | YES | Thời điểm duyệt |
| revoked_at | DATETIME(3) | YES | Thời điểm thu hồi |
| expired_at | DATETIME(3) | YES | Thời điểm hết hạn được ghi nhận |
| created_at, updated_at | DATETIME(3) | NO | UTC |

INDEX `(person_email, room_id, record_status)`. Không UNIQUE cứng trên email + room vì phải bảo toàn lịch sử/tái sử dụng REVOKED; service phải khóa bản ghi phù hợp trong transaction và xác định bản ghi quyền hiện hành.

### 3.4 `pl1_access_record_history`

`id PK, access_record_id FK, access_request_id FK, change_type, old_values JSON, new_values JSON, actor_type, actor_user_id NULL, actor_name_snapshot, action_channel, changed_at DATETIME(3), created_at`. Append-only; INDEX `(access_record_id, changed_at)`.

### 3.5 `pl1_action_tokens`

`id PK, access_request_id FK, purpose, token_hash CHAR(64) UNIQUE, expires_at, used_at NULL, revoked_at NULL, created_at`. Chỉ lưu hash; không đưa token rõ vào Audit Log.

## 4. PL2 – Visit / Check-in / Check-out

### 4.1 `pl2_visits`

- Định danh: `id PK`, `business_entity_id BIGINT UNSIGNED NOT NULL UNIQUE FK business_entities(id)`, `visit_code VARCHAR(60) UNIQUE`, `room_id FK`.
- Người đăng ký: `requester_name`, `requester_email`, `organization`, `visitor_count SMALLINT UNSIGNED`.
- Công việc: `purpose TEXT`, `supervisor_name`, `planned_entry_at DATETIME(3)`.
- Snapshot duyệt: `approver_name_snapshot`, `approver_email_snapshot`.
- Trạng thái: `approval_status` (PENDING/APPROVED/REJECTED/EXPIRED), `visit_status` (NOT_CHECKED_IN/OPEN/CLOSED).
- Check-in: `checked_in_at`, `checked_in_person_name`, `checked_in_actor_name`.
- Check-out: `checked_out_at`, `checked_out_actor_name`, `work_result TEXT`.
- Hạn/decision: `approval_deadline_at`, `approved_at`, `approval_note TEXT NULL`.
- Chuẩn: `created_at, updated_at`.

INDEX `(approval_status, created_at)`, `(room_id, planned_entry_at)`, `(visit_status, checked_in_at)`.

### 4.2 `pl2_approval_history`

`id PK, visit_id FK, action_code, result, actor_type, actor_user_id NULL, actor_name_snapshot, actor_email_snapshot, channel, note, occurred_at, created_at`. Ghi cả reminder/expiry và quyết định.

### 4.3 `pl2_action_tokens`

`id PK, visit_id FK, purpose (APPROVAL/CHECKIN/CHECKOUT), token_hash CHAR(64) UNIQUE, expires_at, used_at NULL, revoked_at NULL, created_at`. Mỗi token chỉ đúng một purpose và một Visit.

### 4.4 `pl2_otp_challenges`

`id PK, visit_id FK, otp_hash CHAR(64), status (PENDING/SENT/VERIFIED/EXPIRED/LOCKED/INVALIDATED/FAILED), requested_at, sent_at NULL, expires_at NULL, failed_attempts TINYINT UNSIGNED DEFAULT 0, verified_at NULL, invalidated_at NULL, created_at`. Hạn OTP chỉ được gán sau khi email gửi thành công.

### 4.5 `pl2_otp_attempts`

`id PK, otp_challenge_id FK, attempted_at, result (SUCCESS/FAILED/LOCKED), failure_code NULL, ip_address NULL, user_agent NULL`. Không lưu OTP đã nhập.

### 4.6 `pl2_visit_events`

`id PK, visit_id FK, event_code, actor_type, actor_user_id NULL, actor_name_snapshot, action_channel, event_data JSON NULL, occurred_at, created_at`. Dữ liệu chính như Entry/Exit vẫn được giữ ở pl2_visits để đọc trạng thái hiện tại.

## 5. PL3 – Inspection

### 5.1 `pl3_checklist_items`

`id PK, item_code VARCHAR(20) UNIQUE, title_vi, description_vi, description_en NULL, sort_order SMALLINT, is_active, effective_from DATE NULL, effective_until DATE NULL`. Không xóa item đã được dùng trong phiếu.

### 5.2 `pl3_inspections`

`id PK, business_entity_id BIGINT UNSIGNED NOT NULL UNIQUE FK business_entities(id), inspection_code UNIQUE, room_id FK, frequency (DAILY/WEEKLY/MONTHLY), inspection_date DATE, inspection_time TIME, inspector_name, inspector_email NULL, temperature_c DECIMAL(5,2) NULL, humidity_percent DECIMAL(5,2) NULL, conclusion, form_response_id NULL, uoa_acknowledgement_status, submitted_at, created_at, updated_at`.

UNIQUE idempotency không đặt tùy tiện trên `room_id + inspection_date` vì cần xác nhận quy tắc nhiều lần kiểm tra/tần suất. Dùng `idempotency_keys` theo Form Response ID; kiểm soát phiếu hợp lệ theo kỳ bằng service.

### 5.3 `pl3_inspection_results`

`id PK, inspection_id FK, checklist_item_id FK, result (PASS/FAIL/NA), remarks_action TEXT NULL, created_at`. UNIQUE `(inspection_id, checklist_item_id)`. CHECK nghiệp vụ: FAIL bắt buộc remarks_action.

### 5.4 `pl3_acknowledgements`

`id PK, inspection_id FK, status, actor_user_id NULL, actor_name_snapshot, actor_email_snapshot, channel, note NULL, acknowledged_at, created_at`. Là xác nhận tiếp nhận, không phải xác nhận khắc phục.

### 5.5 `pl3_escalation_runs` và `pl3_escalation_events` – theo ADR-002 đã phê duyệt

- Run: `id PK, room_id FK, work_date DATE, schedule_type VARCHAR(20) CHECK/ENUM DAILY, deadline_at DATETIME, status (PENDING/IN_PROGRESS/STOPPED_INSPECTION_RECEIVED/COMPLETED/ERROR), config_snapshot JSON, started_at DATETIME, stopped_at DATETIME NULL, stop_reason VARCHAR/TEXT NULL, created_at, updated_at`.
- UNIQUE: `(room_id, work_date, schedule_type)`. Phạm vi hiện tại chỉ tạo `schedule_type = DAILY`; không tạo WEEKLY/MONTHLY khi chưa có quyết định riêng.
- Event: `id PK, escalation_run_id FK, level TINYINT, recipient_type, recipient_name_snapshot, recipient_email_snapshot, status (PENDING/SENDING/SENT/SKIPPED/RETRY/FAILED/STOPPED), scheduled_at DATETIME, attempted_at DATETIME NULL, sent_at DATETIME NULL, email_queue_id BIGINT UNSIGNED NULL FK email_queue(id), attempt_count INT DEFAULT 0, reason TEXT NULL, error_code VARCHAR(100) NULL, created_at, updated_at`.
- UNIQUE `(escalation_run_id, level)` để mỗi cấp chỉ có một Event logic; retry được ghi nhận qua Email Queue/Email Queue Attempts và `attempt_count`, không tạo Event mới cho từng lần thử.
- Timing: Level 1 = `DAILY_DEADLINE + ALERT_LEVEL1_DELAY_MINUTES`; Level 2 = timestamp kết quả Level 1 + `ALERT_LEVEL2_DELAY_MINUTES`; Level 3 = timestamp kết quả Level 2 + `ALERT_LEVEL3_DELAY_MINUTES`. Timestamp `SKIPPED` cũng là mốc tính cấp kế tiếp.
- Khi Inspection hợp lệ đúng `room_id + work_date` được commit, Run chuyển `STOPPED_INSPECTION_RECEIVED`; Event chưa gửi chuyển `STOPPED`; Event đã gửi/SKIPPED giữ nguyên lịch sử.
- Email được enqueue qua `email_queue`; Event tham chiếu queue bằng `email_queue_id`. Không gửi SMTP trực tiếp trong transaction Scheduler.

### 5.6 `pl3_integration_outbox`

`id PK, event_key VARCHAR(190) UNIQUE, inspection_id FK, inspection_result_id FK, target_system, payload JSON, status (PENDING/PROCESSING/RETRY/SENT/FAILED), attempts, next_attempt_at, last_error, created_at, updated_at`. Không lưu API secret; payload chỉ chứa dữ liệu nghiệp vụ cần thiết.

## 6. PL4 – Issue Management

### 6.1 `pl4_issues`

`id PK, business_entity_id BIGINT UNSIGNED NOT NULL UNIQUE FK business_entities(id), issue_code UNIQUE, source_type (PL3/MANUAL), source_inspection_id NULL FK, source_inspection_result_id NULL FK UNIQUE, room_id FK, source_item_code_snapshot NULL, source_fail_remarks_snapshot NULL, title, description, priority, location_equipment NULL, reporter_name, reporter_email NULL, status (OPEN/IN_PROGRESS/PENDING_UOA_CONFIRMATION/CLOSED), created_at, updated_at`.

Nếu MySQL cho phép nhiều NULL trong UNIQUE, unique `source_inspection_result_id` bảo đảm một FAIL item chỉ sinh tối đa một issue nguồn PL3. Cần thêm kiểm tra source_type và source FK nhất quán ở service/constraint phù hợp.

### 6.2 `pl4_assignments`

`id PK, issue_id FK, assignee_name_snapshot, assignee_email_snapshot, assigned_by_user_id NULL, assigned_at, claimed_at NULL, released_at NULL, status, note NULL, created_at`. Một Issue có thể có nhiều lần phân công theo thời gian.

### 6.3 `pl4_work_reports`

`id PK, issue_id FK, assignment_id FK NULL, report_result (COMPLETED/NOT_COMPLETED), action_taken TEXT, resolution_notes TEXT, evidence_summary TEXT NULL, submitted_by_name, submitted_by_email, submitted_at, created_at`. Append-only.

### 6.4 `pl4_uoa_confirmations`

`id PK, issue_id FK, work_report_id FK, decision (CONFIRMED/NOT_CONFIRMED), uoa_user_id NULL FK users, uoa_name_snapshot, uoa_email_snapshot, note TEXT NULL, reason TEXT NULL, decided_at, created_at`. NOT_CONFIRMED bắt buộc reason; một report có thể được xem xét theo policy, nhưng không được ghi đè quyết định cũ.

### 6.5 `pl4_issue_status_history`

`id PK, issue_id FK, from_status, to_status, actor_type, actor_user_id NULL, actor_name_snapshot, channel, reason NULL, changed_at`. Append-only.

## 7. PL5 – Work & Maintenance

### 7.1 `pl5_work_catalog_categories`

`id PK, code UNIQUE, name, description NULL, sort_order, is_active, created_at, updated_at`.

### 7.2 `pl5_work_catalog_items`

`id PK, category_id FK, code UNIQUE, title, description, risk_level NULL, safety_instructions TEXT NULL, required_rollback TINYINT(1), is_active, created_at, updated_at`. Chỉ Super Admin quản trị catalog.

### 7.3 `pl5_maintenance_plans`

`id PK, plan_code UNIQUE, room_id FK, catalog_item_id FK NULL, title, description, frequency_code, interval_value NULL, next_due_at, owner_name_snapshot NULL, is_active, created_at, updated_at`. Quy tắc lịch cụ thể cần cấu hình và xác nhận trước khi migration.

### 7.4 `pl5_work_requests`

- Định danh/phạm vi: `id PK, business_entity_id BIGINT UNSIGNED NOT NULL UNIQUE FK business_entities(id), work_code UNIQUE, room_id FK, catalog_item_id FK NULL, maintenance_plan_id FK NULL`.
- Requester: `requester_name, requester_email, requester_organization NULL`.
- Nội dung: `title, work_description, work_window_start, work_window_end, implementing_organization, implementing_personnel TEXT, related_equipment TEXT, expected_service_impact TEXT NULL, rollback_plan TEXT`.
- Snapshot duyệt: `approver_name_snapshot, approver_email_snapshot`.
- Trạng thái: `approval_status, work_status`.
- Thời điểm: `submitted_at, approved_at NULL, closed_at NULL, created_at, updated_at`.

### 7.5 `pl5_work_approvals`

`id PK, work_request_id FK, decision (APPROVED/REJECTED/CANCELLED), actor_type, actor_user_id NULL, actor_name_snapshot, channel, note/reason TEXT, decided_at, created_at`. Hủy PENDING_APPROVAL do Requester; approve/reject do UOA đúng phòng.

### 7.6 `pl5_work_assignments`

`id PK, work_request_id FK, executor_name_snapshot, executor_email_snapshot, assigned_by_user_id NULL, assigned_at, started_at NULL, ended_at NULL, status, created_at`.

### 7.7 `pl5_work_execution_events`

`id PK, work_request_id FK, assignment_id FK NULL, event_code (START/HOLD/RESUME/SUBMIT_RESULT), actor_type, actor_name_snapshot, actor_email_snapshot NULL, note/reason TEXT NULL, result TEXT NULL, occurred_at, created_at`. Append-only.

### 7.8 `pl5_work_verifications`

`id PK, work_request_id FK, decision (PASS/FAIL), verifier_user_id NULL FK users, verifier_name_snapshot, verifier_email_snapshot, note TEXT, verified_at, created_at`. FAIL đưa work về IN_PROGRESS và phát sinh REWORK_REQUIRED event.

## 8. Dịch vụ dùng chung

### 8.1 `email_queue`

| Cột | Kiểu | Null | Ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| event_key | VARCHAR(190) | NO | UNIQUE idempotency |
| module_code | VARCHAR(10) | NO | PL1–PL5 / SYSTEM |
| event_type | VARCHAR(80) | NO | Loại email |
| business_entity_id | BIGINT UNSIGNED | YES | FK business_entities; NULL chỉ với event hệ thống không gắn hồ sơ |

| recipient | VARCHAR(500) | NO | Người nhận |
| cc_json, bcc_json | JSON | YES | CC/BCC |
| subject | VARCHAR(255) | NO | Tiêu đề |
| html_body, plain_body | MEDIUMTEXT | YES | Nội dung gửi |
| status | VARCHAR(20) | NO | PENDING/SENDING/RETRY/SENT/FAILED/CANCELLED |
| attempts, max_attempts | SMALLINT UNSIGNED | NO | Bộ đếm |
| next_attempt_at | DATETIME(3) | NO | Lần thử tiếp |
| locked_at, locked_by | DATETIME(3)/VARCHAR(100) | YES | Worker lock |
| last_error | TEXT | YES | Lỗi đã làm sạch secret |
| message_id | VARCHAR(255) | YES | SMTP Message-ID |
| sent_at | DATETIME(3) | YES | Gửi thành công |
| created_at, updated_at | DATETIME(3) | NO | UTC |

UNIQUE `event_key`; INDEX `(status, next_attempt_at, id)`. Có thể cần `email_queue_attempts` riêng lưu mỗi lần gửi. Không lưu mật khẩu SMTP hoặc API secret trong queue.

### 8.2 `email_queue_attempts`

`id PK, email_queue_id FK, attempt_no, attempted_at, outcome, error_code NULL, sanitized_error TEXT NULL, smtp_message_id NULL, duration_ms NULL`; UNIQUE `(email_queue_id, attempt_no)`.

### 8.3 `system_audit_logs`

| Cột | Kiểu | Null | Ý nghĩa |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| event_uuid | CHAR(36) | NO | UNIQUE |
| module | VARCHAR(10) | NO | PL1–PL5 / SYSTEM |
| entity_type | VARCHAR(60) | YES | Loại hồ sơ |
| entity_id | BIGINT UNSIGNED | YES | ID hồ sơ |
| parent_business_entity_id | BIGINT UNSIGNED | YES | FK business_entities nếu có quan hệ hồ sơ cha |
| workflow_step | VARCHAR(60) | YES | Bước workflow |
| event_code | VARCHAR(100) | NO | Mã sự kiện |
| event_name | VARCHAR(160) | NO | Tên hiển thị |
| event_category | VARCHAR(40) | NO | BUSINESS/SECURITY/EMAIL/INTEGRATION/SCHEDULER/CONFIG/EXPORT |
| result | VARCHAR(20) | NO | SUCCESS/FAILED/DENIED |
| old_status, new_status | VARCHAR(40) | YES | Trước/sau |
| actor_type | VARCHAR(40) | NO | USER/REQUESTER/UOA/MSTAR/ASSIGNEE/EXECUTOR/SYSTEM... |
| actor_user_id | BIGINT UNSIGNED | YES | FK users nếu có |
| actor_name_snapshot | VARCHAR(160) | YES | Snapshot |
| actor_email_snapshot | VARCHAR(190) | YES | Snapshot, hạn chế dữ liệu |
| action_channel | VARCHAR(40) | NO | EMAIL_APPROVAL/ADMIN/EMAIL_LINK_ACTION/PUBLIC_FORM/SCHEDULER/API |
| description | TEXT | YES | Mô tả |
| reason | TEXT | YES | Lý do |
| old_values, new_values | JSON | YES | Chỉ trường không nhạy cảm |
| metadata | JSON | YES | Metadata an toàn |
| correlation_id, causation_id, request_id | VARCHAR(100) | YES | Truy vết |
| ip_address | VARCHAR(45) | YES | Theo chính sách privacy |
| user_agent | VARCHAR(500) | YES | Theo chính sách privacy |
| occurred_at, recorded_at | DATETIME(3) | NO | Thời điểm sự kiện/ghi nhận |
| error_code | VARCHAR(100) | YES | Mã lỗi |
| error_message | TEXT | YES | Đã làm sạch bí mật |

INDEX đề xuất: `(business_entity_id, occurred_at)`, `(event_code, occurred_at)`, `(actor_user_id, occurred_at)`, `(correlation_id)`, `(result, occurred_at)`. Append-only; ứng dụng không có UPDATE/DELETE. FK actor_user_id có thể dùng ON DELETE RESTRICT; tài khoản bị disable chứ không xóa.

### 8.4 `evidence_files` và `entity_evidence_links`

- `evidence_files`: `id PK, file_uuid UNIQUE, original_name, storage_key UNIQUE, mime_type, file_size_bytes, sha256 CHAR(64), uploaded_by_type, uploaded_by_user_id NULL, uploader_name_snapshot, uploaded_at, scan_status, is_quarantined`.
- `entity_evidence_links`: `id PK, evidence_file_id FK, business_entity_id FK business_entities, purpose, linked_by_user_id NULL, linked_at`. UNIQUE phù hợp để tránh liên kết trùng theo `(evidence_file_id, business_entity_id, purpose)`.
- Chặn truy cập file trực tiếp; download qua controller kiểm tra quyền. Không cho phép đường dẫn tùy ý hoặc MIME không nằm trong allowlist.

### 8.5 `idempotency_keys`, `integration_events`, `scheduled_job_runs`, `data_exports`

- `idempotency_keys(id PK, scope, idempotency_key, request_hash, entity_type NULL, entity_id NULL, status, response_snapshot JSON NULL, expires_at NULL, created_at)`; UNIQUE `(scope, idempotency_key)`.
- `integration_events(id PK, event_key UNIQUE, source_system, target_system, event_type, entity_type, entity_id, correlation_id, status, request_metadata JSON, response_metadata JSON, attempts, last_error, created_at, updated_at)`; không lưu secret/token.
- `scheduled_job_runs(id PK, job_code, run_uuid UNIQUE, started_at, finished_at NULL, status, processed_count, success_count, failed_count, summary JSON NULL, error_message NULL)`.
- `data_exports(id PK, export_code UNIQUE, requested_by_user_id FK users, export_type, module_scope, filters JSON, status, storage_key NULL, sha256 NULL, created_at, completed_at NULL)`.

## 9. Ma trận chỉ mục và ràng buộc trọng yếu

| Bảng | UNIQUE / INDEX cần có |
|---|---|
| users | UNIQUE email |
| roles / permissions | UNIQUE code |
| server_rooms | UNIQUE room_code; INDEX status |\n| business_entities | UNIQUE module_code + entity_type + business_code; INDEX module/type/id |
| room_contacts | INDEX room_id + contact_type + is_active |
| pl1_access_requests | UNIQUE request_code; INDEX room/person/status; INDEX status/submitted_at |
| pl1_access_records | INDEX person_email + room_id + record_status |
| pl1_access_record_history | INDEX access_record_id + changed_at |
| pl2_visits | UNIQUE visit_code; INDEX approval_status + created_at; INDEX room + planned_entry |
| pl2_action_tokens | UNIQUE token_hash; INDEX visit + purpose + expiry |
| pl2_otp_challenges | INDEX visit + status; INDEX expires_at |
| pl3_inspections | UNIQUE inspection_code; INDEX room + inspection_date |
| pl3_inspection_results | UNIQUE inspection + checklist item |
| pl3_integration_outbox | UNIQUE event_key; INDEX status + next_attempt_at |
| pl4_issues | UNIQUE issue_code; UNIQUE source_inspection_result_id nullable |
| pl4_work_reports | INDEX issue + submitted_at |
| pl4_uoa_confirmations | INDEX issue + decided_at |
| pl5_work_requests | UNIQUE work_code; INDEX room + work window; INDEX statuses |
| email_queue | UNIQUE event_key; INDEX status + next_attempt_at |
| system_audit_logs | UNIQUE event_uuid; INDEX business_entity_id + occurred_at, actor timeline, correlation |\n| entity_evidence_links | FK business_entity_id; UNIQUE evidence + entity + purpose phù hợp policy |
| evidence_files | UNIQUE file_uuid/storage_key; INDEX sha256 |
| idempotency_keys | UNIQUE scope + key |
 
## 10. Các quyết định cần khóa trước khi viết Migration

1. PL1 Valid Until có hiệu lực hết ngày hay hết thời điểm; scheduler chạy chu kỳ nào.
2. PL1 thời hạn approval token chính thức và cấu hình hiện hành.
3. PL2 hạn CHECK-IN sau APPROVED; xử lý Visit đã APPROVED nhưng không CHECK-IN.
4. PL3 khóa một inspection theo ngày/phòng hay theo frequency/period; cách xử lý Weekly/Monthly.
5. PL3 một hay nhiều chu kỳ escalation trong một ngày/phòng và cách dừng khi phiếu đến.
6. Xác minh API trigger PL3 → PL4 đang triển khai để không nhân đôi integration.
7. PL4 queue/retry thực tế; xác nhận có dùng queue chung.
8. Các trường Form live chưa đọc được; đối chiếu chính thức trước khi chốt NOT NULL/validation.
9. Retention Audit/Evidence, IP/User-Agent, WORM/chữ ký số và archive/partition.
10. Có cần external_contacts master hay snapshot actor trên từng hồ sơ là đủ.

## 11. Kết luận và trạng thái

**Data Dictionary v0.2 – DRAFT, đã áp dụng ADR-001; chưa phải Migration sẵn sàng chạy.** Đây là cấu trúc vật lý đề xuất để rà soát, không phải migration sẵn sàng chạy. Các cột/constraint có thể điều chỉnh sau khi giải quyết các điểm ở Mục 10. Sau khi chốt các điểm nghiệp vụ còn mở và rà soát đầy đủ subtype, bước kế tiếp mới là Migration theo thứ tự phụ thuộc.
