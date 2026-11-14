# SRM – Data Dictionary (bản nháp)

> Trạng thái: DRAFT. Tài liệu này là khung thiết kế để tiếp tục rà soát. Không dùng làm căn cứ chạy migration cho đến khi chốt đầy đủ các cột, FK, index, unique, enum/status, delete rule và tenant integrity.

## 1. Quy ước chung dự kiến

- MySQL 8.0, InnoDB, utf8mb4; cần đối chiếu collation thực tế của schema đích.
- PK dự kiến: `BIGINT UNSIGNED AUTO_INCREMENT`.
- FK phải cùng kiểu và dấu với PK bảng được tham chiếu.
- Timestamp Laravel: `created_at`, `updated_at`; dùng `deleted_at` có chọn lọc.
- Mọi bảng nghiệp vụ cần được scope theo tenant. Bảng con nên có `customer_id` để truy vấn và kiểm soát tenant rõ ràng, trừ khi có thiết kế thay thế được chứng minh an toàn.
- Các trạng thái đang nêu là giá trị dự kiến, chưa chốt enum cuối cùng.

## 2. Danh sách bảng và cấu trúc sơ bộ

### 2.1 `server_room_locations`

Mục đích: địa điểm đặt phòng Server thuộc khách hàng.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | FK `service_customers.id` |
| branch_id | BIGINT UNSIGNED | YES | FK `customer_branches.id`; phải cùng tenant |
| code | VARCHAR(50) | NO | Mã địa điểm |
| name | VARCHAR(150) | NO | Tên địa điểm |
| location_type | VARCHAR(30) | NO | branch/head_office/data_center/independent |
| address | VARCHAR(255) | YES | Địa chỉ |
| provider_name | VARCHAR(150) | YES | Nhà cung cấp Data Center/địa điểm |
| contact_name | VARCHAR(150) | YES | Người liên hệ |
| contact_phone | VARCHAR(50) | YES | Điện thoại |
| contact_email | VARCHAR(190) | YES | Email |
| description | TEXT | YES | Mô tả |
| status | VARCHAR(20) | NO | Trạng thái |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |
| deleted_at | TIMESTAMP | YES | Soft delete dự kiến |

Unique dự kiến: `(customer_id, code)`. Index dự kiến: `(customer_id, location_type)`, `branch_id`. Cần ràng buộc/validation: location_type=branch phải có branch_id; các loại còn lại không bắt buộc branch_id.

### 2.2 `server_rooms`

Mục đích: phòng Server cụ thể tại một địa điểm.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| location_id | BIGINT UNSIGNED | NO | FK `server_room_locations`, cùng tenant |
| code | VARCHAR(50) | NO | Mã phòng |
| name | VARCHAR(150) | NO | Tên phòng |
| floor | VARCHAR(50) | YES | Tầng |
| area_sqm | DECIMAL(10,2) | YES | Diện tích m² |
| description | TEXT | YES | Mô tả |
| status | VARCHAR(20) | NO | Trạng thái |
| created_by | BIGINT UNSIGNED | YES | FK `users.id` |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |
| deleted_at | TIMESTAMP | YES | |

Unique dự kiến: `(customer_id, code)`. Index dự kiến: `(customer_id, location_id)`, `status`.

### 2.3 `server_room_contacts`

Mục đích: đầu mối liên hệ của phòng.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng, cùng tenant |
| user_id | BIGINT UNSIGNED | YES | FK `users.id` nếu có tài khoản |
| full_name | VARCHAR(150) | NO | Họ tên |
| email | VARCHAR(190) | YES | Email |
| phone | VARCHAR(50) | YES | Điện thoại |
| role | VARCHAR(50) | NO | Vai trò liên hệ |
| is_primary | BOOLEAN | NO | Đầu mối chính |
| is_active | BOOLEAN | NO | Hiệu lực |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index dự kiến: `(customer_id, server_room_id)`, `user_id`. Quy tắc một primary contact/phòng cần chốt.

### 2.4 `server_room_assets`

Mục đích: danh mục thiết bị hạ tầng SRM, độc lập với ITAM hiện tại (đang chỉ quản lý PC/Laptop User).

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng, cùng tenant |
| asset_tag | VARCHAR(100) | NO | Mã tài sản SRM |
| asset_name | VARCHAR(150) | NO | Tên thiết bị |
| category | VARCHAR(50) | NO | Nhóm thiết bị |
| manufacturer | VARCHAR(100) | YES | Hãng |
| model | VARCHAR(100) | YES | Model |
| serial_number | VARCHAR(150) | YES | Serial |
| rack_name | VARCHAR(100) | YES | Tên/mã Rack |
| rack_position | VARCHAR(50) | YES | Vị trí U |
| management_ip | VARCHAR(45) | YES | IPv4/IPv6 |
| installed_at | DATE | YES | Ngày lắp |
| warranty_expiry | DATE | YES | Hết bảo hành |
| status | VARCHAR(30) | NO | Trạng thái |
| notes | TEXT | YES | Ghi chú |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |
| deleted_at | TIMESTAMP | YES | |

Nhóm category dự kiến: server, firewall, switch, router, storage, nas, ups, pdu, rack, cooling, other. Unique dự kiến: `(customer_id, asset_tag)`. Index dự kiến: `(server_room_id, category)`, `(customer_id, serial_number)`. Cần rà soát có tách Rack thành thực thể riêng hay chỉ lưu thuộc tính.

### 2.5 `server_room_access_persons`

Mục đích: danh sách cá nhân được phép tiếp cận phòng Server.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| user_id | BIGINT UNSIGNED | YES | FK users nếu là user hệ thống |
| full_name | VARCHAR(150) | NO | Họ tên |
| organization | VARCHAR(150) | YES | Đơn vị |
| job_title | VARCHAR(100) | YES | Chức danh |
| access_reason | TEXT | YES | Lý do cấp quyền |
| access_level | VARCHAR(30) | NO | permanent/supervised dự kiến |
| valid_from | DATETIME | YES | Hiệu lực từ |
| valid_until | DATETIME | YES | Hiệu lực đến |
| approval_status | VARCHAR(30) | NO | Trạng thái phê duyệt |
| approved_by | BIGINT UNSIGNED | YES | FK users |
| approved_at | DATETIME | YES | |
| revoked_at | DATETIME | YES | |
| notes | TEXT | YES | |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index dự kiến: `(customer_id, server_room_id, approval_status)`, `user_id`.

### 2.6 `srm_checklist_templates`

Mục đích: mẫu Checklist riêng của từng khách hàng, có versioning.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| code | VARCHAR(50) | NO | Mã template |
| name | VARCHAR(150) | NO | Tên |
| description | TEXT | YES | Mô tả |
| frequency | VARCHAR(30) | NO | daily/weekly/monthly/after_maintenance/custom dự kiến |
| version | INT UNSIGNED | NO | Phiên bản |
| status | VARCHAR(30) | NO | draft/pending_approval/approved/inactive |
| effective_from | DATE | YES | Hiệu lực từ |
| effective_until | DATE | YES | Hiệu lực đến |
| created_by | BIGINT UNSIGNED | YES | FK users |
| approved_by | BIGINT UNSIGNED | YES | FK users |
| approved_at | DATETIME | YES | |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique dự kiến: `(customer_id, code, version)`.

### 2.7 `srm_checklist_items`

Mục đích: các câu hỏi/hạng mục thuộc template.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| template_id | BIGINT UNSIGNED | NO | FK template |
| item_code | VARCHAR(50) | NO | Mã hạng mục |
| item_name | VARCHAR(200) | NO | Tên |
| description | TEXT | YES | Hướng dẫn |
| response_type | VARCHAR(30) | NO | pass_fail_na/yes_no/number/text/date dự kiến |
| is_required | BOOLEAN | NO | Bắt buộc |
| require_evidence | BOOLEAN | NO | Cần bằng chứng |
| sort_order | INT | NO | Thứ tự |
| is_active | BOOLEAN | NO | Hiệu lực |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique dự kiến: `(template_id, item_code)`. Có thể cần `customer_id` để thuận tiện tenant enforcement hoặc FK ghép qua template; quyết định ở bản final.

### 2.8 `server_room_access_logs`

Mục đích: nhật ký đăng ký/ghi nhận người ra vào phòng.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| access_person_id | BIGINT UNSIGNED | YES | FK access persons |
| visitor_name | VARCHAR(150) | NO | Họ tên thực tế |
| organization | VARCHAR(150) | YES | Đơn vị |
| purpose | TEXT | NO | Mục đích |
| entry_at | DATETIME | NO | Giờ vào |
| exit_at | DATETIME | YES | Giờ ra |
| approver_id | BIGINT UNSIGNED | YES | FK users |
| supervisor_id | BIGINT UNSIGNED | YES | FK users |
| work_performed | TEXT | YES | Công việc |
| completion_note | TEXT | YES | Ghi chú hoàn tất |
| status | VARCHAR(30) | NO | registered/checked_in/completed/cancelled dự kiến |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index dự kiến: `(server_room_id, entry_at)`, `(customer_id, status)`. Cần hỗ trợ trường hợp khách/nhà thầu không có user account.

### 2.9 `server_room_inspections` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: phiếu kiểm tra gắn với phòng Server và một phiên bản Checklist. Cần chốt mã phiếu, ngày/giờ, người kiểm tra, trạng thái, kết luận, người rà soát, snapshot phiên bản template, xử lý tái kiểm tra và lịch sử đóng phiếu.

### 2.10 `server_room_inspection_results` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: kết quả từng hạng mục trong phiếu. Bắt buộc thiết kế snapshot item_code, item_name, response_type và giá trị thực tế tại thời điểm kiểm tra. Cần chốt mô hình dữ liệu cho câu trả lời số/văn bản/pass-fail/yes-no/date; cân nhắc cột typed hoặc `value_json`, tránh thiết kế khiến lọc/báo cáo khó khăn.

### 2.11 `server_room_incidents` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: sự cố phòng Server. Cần chốt mã sự cố, loại, mức độ, thời điểm phát hiện, người báo cáo, mô tả, ảnh hưởng, hành động tức thời, nguyên nhân gốc, trạng thái, thời gian xử lý/đóng và liên kết tài sản nếu có.

### 2.12 `server_room_incident_actions` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: hành động khắc phục/phòng ngừa của sự cố; cần chốt người phụ trách, hạn xử lý, trạng thái, bằng chứng hoàn thành và xác minh.

### 2.13 `server_room_maintenance_requests` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: yêu cầu/công việc bảo trì. Cần chốt loại công việc, lịch dự kiến/thực tế, đơn vị/nhân sự thực hiện, thiết bị liên quan, phạm vi, ảnh hưởng, rollback, kết quả sau bảo trì, phê duyệt và xác minh.

### 2.14 `server_room_equipment_movements` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: ghi nhận thiết bị đưa vào/đưa ra/di chuyển giữa vị trí. Cần định nghĩa rõ movement_type, vị trí nguồn/đích, phê duyệt, người thực hiện, thời điểm và snapshot thông tin thiết bị khi cần.

### 2.15 `server_room_environment_logs` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: ghi nhận nhiệt độ, độ ẩm, điều hòa, UPS, nguồn điện và ghi chú. Cần chốt đơn vị đo, ngưỡng cảnh báo có cấu hình theo phòng hay không, nguồn dữ liệu thủ công/tự động và thời điểm ghi nhận.

### 2.16 `server_room_approvals` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: luồng phê duyệt cho các đối tượng SRM. Cần chốt đa cấp, thứ tự bước, người duyệt/nhóm duyệt, quyền ủy quyền, trạng thái, comment, thời điểm và chính sách không tự phê duyệt yêu cầu của chính mình. Quan hệ polymorphic cần xem xét kỹ tenant integrity và FK.

### 2.17 `server_room_attachments` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: file/hình ảnh gắn với các đối tượng SRM. Cần chốt disk, path, tên gốc, MIME, kích thước, checksum, người tải lên, quy tắc truy cập riêng theo tenant và vòng đời file. Không lưu binary trực tiếp trong bảng nếu không có lý do cụ thể.

### 2.18 `server_room_audit_logs` – CHƯA ĐẶC TẢ ĐỦ

Khung nghiệp vụ: log thao tác tạo/sửa/xóa/duyệt/chuyển trạng thái. Cần chốt actor, action, entity, old/new JSON, IP, user-agent, request/correlation ID, retention và quyền truy cập. Audit log nên append-only ở tầng ứng dụng.

## 3. Các quyết định phải hoàn tất trước khi tạo Migration

1. ERD cuối cùng với cardinality và mọi FK.
2. Tenant integrity: chọn FK đơn hay composite FK; bảo đảm cùng tenant.
3. Quy tắc delete cho từng quan hệ: RESTRICT / SET NULL / CASCADE.
4. Bộ trạng thái và transition hợp lệ cho access, inspection, incident, maintenance, approval.
5. Snapshot inspection: cấu trúc dữ liệu câu trả lời và phiên bản checklist.
6. Quan hệ Incident ↔ Asset; Maintenance ↔ Asset; Movement ↔ Asset.
7. Mô hình Rack/U: chỉ lưu thuộc tính hay có bảng rack/units riêng.
8. Quy tắc lưu file và kiểm soát truy cập.
9. Đối chiếu migrations/schema hiện hành, tên bảng, conventions và permission hiện có.

## 4. Lưu ý

Bản nháp trước đó từng mô tả một số cột/index cho nhóm bảng sau. Những nội dung đó chưa được xác minh đầy đủ với nghiệp vụ và schema thực tế; cần hoàn thiện, không xem là đặc tả đã chốt.