# SRM – Column-level Schema, Batch 3 (bản nháp)

> Phạm vi: 8 bảng còn lại để hoàn tất register 22 bảng: Environment Logs, Approvals, Attachments, Audit Logs, 2 bảng liên kết nhiều-nhiều và 2 bảng access-control. Đây là thiết kế cột/khóa đề xuất; chưa tạo migration.

## 1. Quy ước
- Tất cả bảng nghiệp vụ/access-control có customer_id BIGINT UNSIGNED NOT NULL.
- PK BIGINT UNSIGNED AUTO_INCREMENT, trừ bảng liên kết dùng composite primary key.
- Tenant integrity dùng composite FK khi khả thi và service-layer authorization trong transaction.
- Polymorphic type phải qua allowlist cố định; không nhận class/model tùy ý từ request.
- Audit/approval/attachment là dữ liệu lịch sử; không cascade xóa theo hồ sơ đã phát sinh.
- Actor FK tới users.id; nếu ON DELETE SET NULL thì cột phải nullable.

## 2. Bảng 15 – server_room_environment_logs

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| recorded_at | DATETIME | NO | — | Thời điểm đo |
| temperature_c | DECIMAL(5,2) | YES | NULL | °C |
| humidity_percent | DECIMAL(5,2) | YES | NULL | 0–100 |
| air_conditioning_status | VARCHAR(30) | YES | NULL | Trạng thái điều hòa |
| ups_status | VARCHAR(30) | YES | NULL | Trạng thái UPS |
| power_status | VARCHAR(30) | YES | NULL | Trạng thái nguồn |
| source_type | VARCHAR(20) | NO | manual | manual/device/import |
| source_reference | VARCHAR(150) | YES | NULL | ID sensor/nguồn ngoài |
| remarks | TEXT | YES | NULL | Ghi chú |
| recorded_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, server_room_id, recorded_at), (source_type, source_reference), (customer_id, source_type, recorded_at). Composite FK (server_room_id, customer_id) → server_rooms(id, customer_id). Không unique source_reference/recorded_at vì có thể có nhiều phép đo cùng thời điểm làm tròn.

**Validation:** Ít nhất một trường đo/trạng thái có dữ liệu; humidity_percent trong [0,100]. Ngưỡng nhiệt độ cảnh báo cấu hình theo policy, không hard-code. source_type allowlist manual/device/import. Timezone và retention cần chốt; ngưỡng cảnh báo cấu hình riêng, không lưu ngưỡng vào từng log.

## 3. Bảng 16 – server_room_approvals

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| approvable_type | VARCHAR(100) | NO | — | Alias polymorphic allowlist |
| approvable_id | BIGINT UNSIGNED | NO | — | ID hồ sơ |
| approval_step | SMALLINT UNSIGNED | NO | 1 | Thứ tự bước |
| approver_id | BIGINT UNSIGNED | YES | NULL | FK users nếu resolve cá nhân |
| approver_group | VARCHAR(100) | YES | NULL | Snapshot mã nhóm/role |
| status | VARCHAR(20) | NO | pending | pending/approved/rejected/skipped |
| comments | TEXT | YES | NULL | Ý kiến/lý do |
| assigned_at | DATETIME | YES | NULL | Thời điểm giao |
| decided_at | DATETIME | YES | NULL | Thời điểm quyết định |
| assigned_by | BIGINT UNSIGNED | YES | NULL | Đề xuất bổ sung: người giao/reassign |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, approvable_type, approvable_id), (approver_id, status), (customer_id, status, assigned_at). UNIQUE theo approval_step/approver chưa chốt vì phụ thuộc multi-approver. FK approver_id/assigned_by → users.id, nullable. Không thể tạo FK trực tiếp cho approvable_id đến nhiều bảng; service phải xác thực allowlist, tồn tại hồ sơ và cùng tenant.

**Workflow:** pending → approved/rejected/skipped; reassign giữ pending nhưng phải audit. Không ghi đè quyết định đã chốt. Khi quyết định phải kiểm tra user active, permission, tenant và đúng step. Mặc định cấm self-approval. ANY/ALL và cách resolve group vẫn là quyết định mở. Nếu cần lưu lịch sử từng lần giao lại/decision đầy đủ, cân nhắc approval_events riêng ở giai đoạn sau; không tự ý thêm bảng ngoài register 22.

## 4. Bảng 17 – server_room_attachments

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| attachable_type | VARCHAR(100) | NO | — | Alias polymorphic allowlist |
| attachable_id | BIGINT UNSIGNED | NO | — | ID hồ sơ |
| original_name | VARCHAR(255) | NO | — | Tên file gốc |
| storage_disk | VARCHAR(50) | NO | — | Tên disk |
| file_path | VARCHAR(500) | NO | — | Đường dẫn nội bộ |
| mime_type | VARCHAR(150) | YES | NULL | MIME đã xác thực |
| file_size | BIGINT UNSIGNED | NO | — | Byte |
| checksum_sha256 | CHAR(64) | YES | NULL | SHA-256 |
| uploaded_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, attachable_type, attachable_id), (uploaded_by), (customer_id, created_at). FK uploaded_by → users.id, nullable. Polymorphic parent không có FK trực tiếp; kiểm tra allowlist, parent và tenant trước upload/download/delete. file_path không public URL; download qua service authorization.

**Security/retention:** Kiểm tra MIME theo nội dung, không tin extension/header; giới hạn dung lượng/loại file, malware scan và storage policy phải cấu hình. Xóa vật lý theo retention job sau khi kiểm tra tham chiếu; không cascade tự động. Không lưu binary trong MySQL.

## 5. Bảng 18 – server_room_audit_logs

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| auditable_type | VARCHAR(100) | NO | — | Alias polymorphic allowlist |
| auditable_id | BIGINT UNSIGNED | NO | — | ID hồ sơ |
| action | VARCHAR(50) | NO | — | created/updated/deleted/approved/status_changed/... |
| old_values | JSON | YES | NULL | Giá trị trước, đã lọc dữ liệu nhạy cảm |
| new_values | JSON | YES | NULL | Giá trị sau, đã lọc dữ liệu nhạy cảm |
| performed_by | BIGINT UNSIGNED | YES | NULL | FK users |
| ip_address | VARCHAR(45) | YES | NULL | IPv4/IPv6 |
| user_agent | VARCHAR(500) | YES | NULL | Client |
| request_id | VARCHAR(100) | YES | NULL | Correlation ID |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | Thời điểm |

**Khóa/index:** PK id; INDEX (customer_id, auditable_type, auditable_id, created_at), (performed_by, created_at), (action, created_at), (customer_id, created_at). FK performed_by → users.id, nullable. Target qua allowlist; audit event vẫn phải ghi được khi parent archived/soft-deleted. Append-only, quyền đọc riêng. Không ghi password, token, secret, session, private key hoặc dữ liệu nhạy cảm không cần thiết.

## 6. Bảng 19 – server_room_incident_assets

Bảng liên kết sự cố với nhiều thiết bị; một thiết bị có thể xuất hiện trong nhiều sự cố.

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| incident_id | BIGINT UNSIGNED | NO | — | FK incident |
| asset_id | BIGINT UNSIGNED | NO | — | FK SRM asset |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| relationship_note | VARCHAR(255) | YES | NULL | Vai trò/ảnh hưởng |

**Khóa/index:** Composite PK (incident_id, asset_id); INDEX (customer_id, asset_id), (customer_id, incident_id). Composite FK (incident_id, customer_id) → incidents(id, customer_id) và (asset_id, customer_id) → server_room_assets(id, customer_id). Không cascade xóa hồ sơ đã phát sinh; RESTRICT. Xác thực cùng tenant trong transaction.

## 7. Bảng 20 – server_room_maintenance_assets

Bảng liên kết yêu cầu bảo trì với nhiều thiết bị.

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| maintenance_request_id | BIGINT UNSIGNED | NO | — | FK maintenance |
| asset_id | BIGINT UNSIGNED | NO | — | FK SRM asset |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| relationship_note | VARCHAR(255) | YES | NULL | Phạm vi/hạng mục |

**Khóa/index:** Composite PK (maintenance_request_id, asset_id); INDEX (customer_id, asset_id), (customer_id, maintenance_request_id). Composite FK (maintenance_request_id, customer_id) → maintenance_requests(id, customer_id) và (asset_id, customer_id) → server_room_assets(id, customer_id). RESTRICT xóa đối với hồ sơ đã submit/approved/completed; sửa danh sách thiết bị phải audit.

## 8. Bảng 21 – customer_user_srm_permissions

Cấp quyền chức năng SRM cho customer account. RBAC nội bộ tiếp tục dùng permissions/group_permissions/user_groups hiện hữu.

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| user_id | BIGINT UNSIGNED | NO | — | FK users; account_type=customer |
| permission_code | VARCHAR(120) | NO | — | SRM permission allowlist |
| granted_by | BIGINT UNSIGNED | YES | NULL | Người cấp, FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; UNIQUE (customer_id, user_id, permission_code); INDEX (user_id, customer_id), (customer_id, permission_code). FK user_id → users.id; customer_id → service_customers.id; granted_by → users.id. Enforce users.account_type='customer' và users.service_customer_id=customer_id ở service layer; FK thường không biểu diễn được điều kiện account_type. permission_code phải thuộc allowlist SRM cho customer; cấm internal/platform/Super Admin.

**Điểm cần chốt:** Dictionary hiện tại không có revoked_at. Nếu DELETE row khi thu hồi thì lịch sử phải được bảo toàn bằng audit append-only. Đề xuất bổ sung revoked_at/revoked_by nếu cần lưu vòng đời grant ngay trên bảng; chưa xem đây là quyết định đã duyệt.

## 9. Bảng 22 – customer_user_server_rooms

Gán phạm vi phòng cụ thể cho customer user; không có assignment thì deny by default.

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| user_id | BIGINT UNSIGNED | NO | — | FK users; customer account cùng tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| granted_by | BIGINT UNSIGNED | YES | NULL | Người cấp |
| revoked_by | BIGINT UNSIGNED | YES | NULL | Người thu hồi |
| revoked_at | DATETIME | YES | NULL | Thời điểm thu hồi |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, user_id, revoked_at), (customer_id, server_room_id, revoked_at), (user_id, server_room_id). Composite FK (server_room_id, customer_id) → server_rooms(id, customer_id). FK user_id → users.id; enforce account_type=customer và service_customer_id=customer_id ở service layer. UNIQUE active assignment cần chiến lược riêng: MySQL 8 có thể dùng generated column hoặc transaction/locking; chưa chọn. Không hard delete assignment đã cấp; revoke giữ lịch sử, cấp lại tạo bản ghi mới sau khi chốt unique active strategy.

## 10. Các quyết định còn mở trước Migration

1. Environment Logs: device/import cần authentication/signature và idempotency key không?
2. Environment Logs: timezone UTC hay timezone tenant; retention và ngưỡng cảnh báo.
3. Approvals: multi-approver ANY hay ALL theo workflow; group resolve thành user snapshot hay lưu group/role.
4. Approvals: có cần approval event/history table hay audit log đủ dùng?
5. Attachments: MIME/dung lượng, malware scan, storage disk, retention và quy trình xóa.
6. Audit Logs: retention, quyền xem, trường cần mask; có cần chống sửa ở tầng storage?
7. Pivot: có cần snapshot tên/tag asset tại thời điểm liên kết hay chỉ FK asset?
8. Customer permissions: thêm revoked_at/revoked_by hay xóa row kèm audit log.
9. Room assignments: unique active assignment bằng generated column hay transaction/service locking.
10. Cấp quyền/gán phòng chỉ do Customer IT Admin trong cùng tenant; can_access_all_branches không thay room assignment.

## 11. Trạng thái batch
- [x] Column-level schema cho 8 bảng còn lại.
- [x] Tenant rules, polymorphic allowlist, index và delete principles ở mức thiết kế.
- [ ] Chủ dự án duyệt các quyết định mở tại mục 10.
- [ ] Rà soát hợp nhất 22 bảng và chuẩn hóa FK/unique/index.
- [ ] Chưa tạo Laravel migrations.
