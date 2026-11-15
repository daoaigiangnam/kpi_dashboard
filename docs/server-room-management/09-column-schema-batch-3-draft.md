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
| recorded_at | DATETIME | NO | — | Thời điểm đo; lưu UTC, không lưu giờ địa phương customer |
| temperature_c | DECIMAL(5,2) | YES | NULL | °C |
| humidity_percent | DECIMAL(5,2) | YES | NULL | 0–100 |
| air_conditioning_status | VARCHAR(30) | YES | NULL | Trạng thái điều hòa |
| ups_status | VARCHAR(30) | YES | NULL | Trạng thái UPS |
| power_status | VARCHAR(30) | YES | NULL | Trạng thái nguồn |
| source_type | VARCHAR(20) | NO | manual | manual/device/import |
| source_reference | VARCHAR(150) | YES | NULL | ID sensor/nguồn ngoài; với device/import bắt buộc ở service layer để định danh nguồn |
| idempotency_key | VARCHAR(100) | YES | NULL | Khóa sự kiện do nguồn cấp; device/import bắt buộc, manual để NULL |
| remarks | TEXT | YES | NULL | Ghi chú |
| recorded_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, server_room_id, recorded_at), (source_type, source_reference), (customer_id, source_type, recorded_at); UNIQUE (customer_id, source_type, source_reference, idempotency_key) để chống ghi trùng cho device/import (các trường khóa này bắt buộc với device/import ở service layer). Composite FK (server_room_id, customer_id) → server_rooms(id, customer_id). Không unique source_reference/recorded_at vì có thể có nhiều phép đo cùng thời điểm làm tròn.

**Validation:** Ít nhất một trường đo/trạng thái có dữ liệu; humidity_percent trong [0,100]. Ngưỡng nhiệt độ cảnh báo cấu hình theo policy, không hard-code. source_type allowlist manual/device/import, default manual. Với device/import: xác thực nguồn, yêu cầu source_reference và idempotency_key, kiểm tra timestamp/chống replay; source_reference không phải credential. recorded_at phải được chuẩn hóa UTC trước khi lưu. Retention cấu hình theo loại dữ liệu/tần suất; archive khi cần, không tự xóa trước khi policy được duyệt.

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
| scan_status | VARCHAR(20) | NO | pending | `pending`, `clean`, `infected`, `error`; chỉ `clean` được phép preview/download |
| scanned_at | TIMESTAMP | YES | NULL | Thời điểm scanner hoàn tất; UTC |
| uploaded_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index:** PK id; INDEX (customer_id, attachable_type, attachable_id), (uploaded_by), (customer_id, created_at). FK uploaded_by → users.id, nullable. Polymorphic parent không có FK trực tiếp; kiểm tra allowlist, parent và tenant trước upload/download/delete. file_path không public URL; download qua service authorization.

**Security (C08) và Retention (C09):** Binary lưu Private Storage; `file_path` chỉ là đường dẫn nội bộ, không public. Xác minh MIME theo nội dung thực tế, không tin extension/client header; chỉ nhận MIME allowlist và giới hạn dung lượng cấu hình. File mới upload ở trạng thái quarantine `pending`; scanner xác nhận `clean` mới cho preview/download. `infected` hoặc `error` bị chặn truy cập và xử lý theo quy trình bảo mật. Mọi thao tác phải kiểm tra authorization + tenant + parent record. Không tự động xóa file khi parent Archived/Soft Deleted. Xóa vật lý chỉ sau khi hết retention, không còn Legal Hold, kiểm tra tham chiếu và ghi Audit actor/thời điểm/kết quả. Không lưu binary trong MySQL.

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


## 12. Bổ sung schema – Approval Delegation (C06, phương án B + áp dụng tại thời điểm kích hoạt cấp)

Chủ dự án đã chọn ủy quyền theo thời hạn/phạm vi và xác nhận chỉ áp dụng khi cấp phê duyệt được kích hoạt. Bổ sung bảng thứ 23: `server_room_approval_delegations`.

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| principal_approver_id | BIGINT UNSIGNED | NO | — | Người ủy quyền gốc |
| delegate_user_id | BIGINT UNSIGNED | NO | — | Người nhận ủy quyền |
| approvable_type | VARCHAR(100) | YES | NULL | Alias allowlist; NULL = mọi loại hồ sơ SRM trong phạm vi được cấp |
| scope_server_room_id | BIGINT UNSIGNED | YES | NULL | NULL = mọi phòng trong tenant; có giá trị = giới hạn phòng này |
| valid_from | DATETIME | NO | — | Bắt đầu hiệu lực, UTC |
| valid_until | DATETIME | NO | — | Kết thúc hiệu lực, UTC; phải lớn hơn valid_from |
| status | VARCHAR(20) | NO | pending | pending/approved/rejected/revoked/expired |
| reason | TEXT | NO | — | Lý do ủy quyền |
| requested_at | DATETIME | NO | — | Thời điểm đề nghị, UTC |
| approved_by | BIGINT UNSIGNED | YES | NULL | Người duyệt ủy quyền |
| approved_at | DATETIME | YES | NULL | Thời điểm duyệt, UTC |
| revoked_by | BIGINT UNSIGNED | YES | NULL | Người thu hồi |
| revoked_at | DATETIME | YES | NULL | Thời điểm thu hồi, UTC |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index/FK:** PK id; INDEX (customer_id, principal_approver_id, status, valid_from, valid_until), (customer_id, delegate_user_id, status), (customer_id, scope_server_room_id, status); FK customer_id → service_customers.id; principal_approver_id, delegate_user_id, approved_by, revoked_by → users.id; composite FK (scope_server_room_id, customer_id) → server_rooms(id, customer_id) khi scope_server_room_id NOT NULL. Service layer xác minh cả hai user thuộc đúng tenant/phạm vi và active; không cho tự ủy quyền cho chính mình. Không hard-delete; thu hồi bằng status/revoked_at/revoked_by.

**Điều kiện hiệu lực:** status=approved; thời điểm kích hoạt cấp nằm trong [valid_from, valid_until]; đúng customer, approver gốc, loại hồ sơ và room scope; delegate active và đủ quyền. Nếu nhiều bản ghi cùng khớp tại thời điểm kích hoạt, không tự chọn: chặn kích hoạt và yêu cầu xử lý xung đột.

**Điều chỉnh bảng 16 – server_room_approvals:**
- `approver_id`: principal approver đã resolve/snapshot từ group/role.
- Bổ sung `acting_approver_id BIGINT UNSIGNED NULL`: người được giao thực hiện tại thời điểm cấp được kích hoạt; nếu không ủy quyền thì bằng approver_id.
- Bổ sung `acted_by BIGINT UNSIGNED NULL`: user thực tế thực hiện approve/reject; chỉ ghi khi có quyết định.
- Bổ sung `delegation_id BIGINT UNSIGNED NULL`: FK tới server_room_approval_delegations.id nếu cấp này sử dụng ủy quyền.
- `approver_group` giữ mã nhóm/role nguồn; danh sách user cụ thể phải snapshot thành từng approval row.
- Khi B thao tác thay A: approver_id=A, acting_approver_id=B, acted_by=B, delegation_id=ID ủy quyền. Không ủy quyền: acting_approver_id=approver_id, delegation_id=NULL.
- Chỉ kiểm tra delegation khi cấp được kích hoạt. Thu hồi/hết hạn sau đó không hồi tố thay đổi cấp đã kích hoạt; cấp tiếp theo kiểm tra lại khi đến lượt.

**Thứ tự tạo bảng:** tạo `server_room_approval_delegations` trước `server_room_approvals` để tạo FK delegation_id. Tổng register: 23 bảng (18 core + 2 pivot + 2 access-control + 1 delegation).


## C09 – Retention và Legal Hold (APPROVED – 2026-11-15)

- Parent chuyển `archived` hoặc Soft Delete không tự động xóa file vật lý.
- Retention được cấu hình theo loại hồ sơ/tài liệu và policy nghiệp vụ; chưa hard-code thời hạn.
- Legal Hold được đặt ở parent record; mọi attachment của parent tự động được bảo vệ trong thời gian hold, không có hold độc lập ở từng file.
- Điều kiện purge: retention của attachment đã hết + parent không còn Legal Hold + kiểm tra không còn tham chiếu hợp lệ + Audit actor/thời điểm/kết quả.
- Cách lưu trạng thái/metadata Legal Hold ở parent và cách scheduler resolve các attachment liên quan sẽ được chốt trong Final Schema Review; hiện chưa thêm cột/bảng hoặc migration.


### C09-B – Legal Hold event history (APPROVED – 2026-11-15)

- Lưu riêng từng sự kiện đặt/gỡ Legal Hold tại parent record; không chỉ cập nhật một cờ trạng thái hiện tại.
- Event tối thiểu: parent type + ID (hoặc định danh parent tương đương), action `placed`/`released`, actor, timestamp UTC, reason; sự kiện release phải tham chiếu lần placement tương ứng.
- Lịch sử không hard delete; mọi thao tác phải được Audit.
- Bảng/cấu trúc lưu event sẽ được chốt trong Final Schema Review; chưa tự thêm bảng/cột hoặc migration.


## C10 – Audit Log Immutability (APPROVED – 2026-11-15)

- `server_room_audit_logs` được thiết kế theo nguyên tắc append-only; không có chức năng cập nhật/xóa audit row trong các luồng ứng dụng thông thường.
- Tài khoản DB runtime của ứng dụng không được cấp quyền `UPDATE`/`DELETE` trên bảng Audit Log. Quyền quản trị/migration phải tách biệt và được kiểm soát.
- Hỗ trợ archive riêng với quyền sửa/xóa bị hạn chế khi compliance yêu cầu; mức bảo vệ cụ thể phụ thuộc policy và năng lực hạ tầng.
- Audit event ghi tối thiểu actor, timestamp UTC, action, target object, result và trace/correlation ID khi có.
- Không tuyên bố bất biến tuyệt đối trước DBA/quản trị hạ tầng chỉ bằng UI hoặc append-only ở tầng ứng dụng.
- Kiểm tra quyền DB, archive, retention, quyền xem log và test âm tính UPDATE/DELETE bằng tài khoản runtime ở giai đoạn triển khai; chưa tạo migration.

## C11 – Pivot Column Naming (APPROVED – 2026-11-15)

- `server_room_incident_assets.impact_note`: ghi nhận ảnh hưởng của Asset trong Incident.
- `server_room_maintenance_assets.work_note`: ghi chú công việc bảo trì liên quan đến Asset.
- Giữ nguyên hai tên cột theo ngữ nghĩa nghiệp vụ; không đổi thành `relationship_note`.


## C12 – Pivot Asset Snapshot (APPROVED – 2026-11-15)

- Hai bảng `server_room_incident_assets` và `server_room_maintenance_assets` phải lưu snapshot gồm `asset_tag` và `asset_name` tại thời điểm liên kết Asset.
- Snapshot phục vụ báo cáo lịch sử, không bị ghi đè khi Asset gốc thay đổi tên/mã.
- Khi cần, giao diện có thể hiển thị song song snapshot lịch sử và thông tin Asset hiện tại; không dùng dữ liệu hiện tại thay cho snapshot.
- Độ dài/kiểu dữ liệu và quy tắc nullability phải được xác nhận khi rà soát cột cuối cùng.


## C13 – Pivot Room Scope (APPROVED – 2026-11-15)

- Pivot Incident–Asset: Asset có thể thuộc Server Room khác nhau trong cùng tenant của Incident; không bắt buộc cùng một Room.
- Pivot Maintenance–Asset: mặc định Asset phải thuộc Room của Maintenance Request. Trường hợp nhiều Room phải có khai báo phạm vi đa phòng rõ ràng trên yêu cầu.
- Service kiểm tra `customer_id` ở cả hai đầu và điều kiện Room tương ứng trước khi ghi pivot; kiểm tra lại vị trí Asset tại thời điểm thực hiện nghiệp vụ.


## C14 – Customer Permission Revoke (APPROVED – 2026-11-15)

- Bảng `customer_user_srm_permissions` giữ lịch sử grant/revoke; thu hồi bằng `revoked_at` và `revoked_by`, không hard-delete.
- Cấp lại permission sau khi thu hồi phải tạo row mới.
- Chỉ một grant active cho mỗi bộ `(customer_id, user_id, permission_code)`.
- Cơ chế unique active được quyết định sau khi xác minh MySQL version, generated-column/index support và schema hiện hữu; nếu không khả thi thì dùng transaction/locking cùng kiểm thử concurrency.
- Mọi grant/revoke phải được authorization và Audit.


## C15 – Customer User Room Assignment Uniqueness (APPROVED – 2026-11-15)

- Bảng `customer_user_server_rooms` giữ lịch sử cấp/thu hồi assignment; revoke cập nhật `revoked_at` và `revoked_by`, không hard-delete.
- Re-assignment sau revoke tạo row mới.
- Chỉ một assignment active cho mỗi `(customer_id, user_id, server_room_id)`.
- Cơ chế unique active phải được xác minh theo MySQL version và schema/index thực tế; nếu không có unique strategy phù hợp, dùng transaction/locking và concurrency test.
