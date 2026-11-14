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

### 2.9 `server_room_inspections`

Mục đích: phiếu kiểm tra một phòng Server theo một phiên bản Checklist đã được phê duyệt.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng, cùng tenant |
| template_id | BIGINT UNSIGNED | NO | FK template |
| template_version | INT UNSIGNED | NO | Snapshot phiên bản |
| inspection_code | VARCHAR(60) | NO | Mã phiếu |
| inspection_date | DATE | NO | Ngày kiểm tra |
| started_at | DATETIME | YES | Bắt đầu |
| completed_at | DATETIME | YES | Hoàn tất |
| frequency | VARCHAR(30) | YES | Tần suất áp dụng tại lần kiểm tra |
| inspector_id | BIGINT UNSIGNED | YES | FK users |
| inspector_name | VARCHAR(150) | NO | Snapshot người kiểm tra |
| conclusion | VARCHAR(40) | YES | pass/corrective_action_required/emergency |
| status | VARCHAR(30) | NO | draft/submitted/reviewed/closed/cancelled |
| general_remarks | TEXT | YES | Nhận xét chung |
| submitted_at | DATETIME | YES | Thời điểm gửi |
| reviewed_by | BIGINT UNSIGNED | YES | FK users |
| reviewed_at | DATETIME | YES | Thời điểm rà soát |
| closed_by | BIGINT UNSIGNED | YES | FK users |
| closed_at | DATETIME | YES | Thời điểm đóng |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique dự kiến: `(customer_id, inspection_code)`. Index: `(customer_id, server_room_id, inspection_date)`, `(customer_id, status)`, `(template_id, template_version)`. Phiếu phải tham chiếu đúng template thuộc cùng khách hàng. Không cho sửa template/version của phiếu đã submit; việc chỉnh sửa sau đó phải theo quy trình trả về/reopen có audit.

### 2.10 `server_room_inspection_results`

Mục đích: kết quả từng hạng mục, lưu snapshot để lịch sử không đổi khi Checklist được sửa.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| inspection_id | BIGINT UNSIGNED | NO | FK phiếu kiểm tra |
| checklist_item_id | BIGINT UNSIGNED | YES | FK item gốc; nullable để giữ lịch sử khi item được archive |
| item_code_snapshot | VARCHAR(50) | NO | Mã tại thời điểm kiểm tra |
| item_name_snapshot | VARCHAR(200) | NO | Tên tại thời điểm kiểm tra |
| description_snapshot | TEXT | YES | Hướng dẫn tại thời điểm kiểm tra |
| response_type_snapshot | VARCHAR(30) | NO | Kiểu câu trả lời |
| result | VARCHAR(30) | YES | pass/fail/na hoặc yes/no tùy response_type |
| numeric_value | DECIMAL(18,4) | YES | Kết quả dạng số |
| text_value | TEXT | YES | Kết quả dạng văn bản |
| date_value | DATE | YES | Kết quả dạng ngày |
| boolean_value | BOOLEAN | YES | Kết quả dạng đúng/sai |
| remarks | TEXT | YES | Ghi chú |
| corrective_action | TEXT | YES | Hành động khắc phục |
| sort_order | INT | NO | Thứ tự snapshot |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index: `(customer_id, inspection_id)`, `checklist_item_id`. Unique dự kiến: `(inspection_id, item_code_snapshot)` nếu mỗi item chỉ xuất hiện một lần trong một phiếu. Quy tắc validation phải bảo đảm chỉ ghi đúng cột giá trị tương ứng với response_type; không nhận đồng thời các kiểu giá trị xung đột. Ảnh/chứng cứ liên kết qua attachments. Cần xác nhận có cần câu trả lời nhiều lựa chọn hay không trước khi chốt kiểu dữ liệu.

### 2.11 `server_room_incidents`

Mục đích: ghi nhận sự cố xảy ra tại phòng Server.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| incident_code | VARCHAR(60) | NO | Mã sự cố |
| incident_type | VARCHAR(50) | NO | Nhóm sự cố |
| severity | VARCHAR(20) | NO | low/medium/high/critical |
| title | VARCHAR(200) | NO | Tiêu đề |
| detected_at | DATETIME | NO | Thời điểm phát hiện |
| reported_at | DATETIME | YES | Thời điểm báo cáo |
| reported_by | BIGINT UNSIGNED | YES | FK users |
| reporter_name | VARCHAR(150) | YES | Snapshot người báo nếu ngoài hệ thống |
| description | TEXT | NO | Mô tả |
| initial_impact | TEXT | YES | Ảnh hưởng ban đầu |
| immediate_action | TEXT | YES | Xử lý tức thời |
| root_cause | TEXT | YES | Nguyên nhân gốc |
| status | VARCHAR(30) | NO | open/investigating/in_progress/resolved/verified/closed |
| resolved_at | DATETIME | YES | Thời điểm xử lý |
| verified_by | BIGINT UNSIGNED | YES | FK users |
| verified_at | DATETIME | YES | Thời điểm xác minh |
| closed_by | BIGINT UNSIGNED | YES | FK users |
| closed_at | DATETIME | YES | Thời điểm đóng |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique: `(customer_id, incident_code)`. Index: `(customer_id, server_room_id, detected_at)`, `(customer_id, severity, status)`. Thiết bị liên quan nên quản lý qua bảng liên kết riêng nếu một sự cố có thể ảnh hưởng nhiều tài sản; không nhồi một `asset_id` đơn vào bảng này.

### 2.12 `server_room_incident_actions`

Mục đích: theo dõi hành động khắc phục và phòng ngừa thuộc một sự cố.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| incident_id | BIGINT UNSIGNED | NO | FK sự cố, cùng tenant |
| action_type | VARCHAR(30) | NO | corrective/preventive |
| description | TEXT | NO | Nội dung hành động |
| assigned_to | BIGINT UNSIGNED | YES | FK users |
| assigned_name | VARCHAR(150) | YES | Snapshot người/đơn vị ngoài |
| due_at | DATETIME | YES | Hạn hoàn thành |
| started_at | DATETIME | YES | Bắt đầu |
| completed_at | DATETIME | YES | Hoàn thành |
| status | VARCHAR(30) | NO | pending/in_progress/completed/verified/cancelled |
| completion_note | TEXT | YES | Kết quả |
| verification_note | TEXT | YES | Xác minh |
| verified_by | BIGINT UNSIGNED | YES | FK users |
| verified_at | DATETIME | YES | |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index: `(customer_id, incident_id)`, `(assigned_to, status, due_at)`. Hành động không được xóa vật lý khi sự cố đã đóng; dùng trạng thái hủy và audit.

### 2.13 `server_room_maintenance_requests`

Mục đích: quản lý yêu cầu và công việc bảo trì phòng Server.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| request_code | VARCHAR(60) | NO | Mã yêu cầu |
| title | VARCHAR(200) | NO | Tiêu đề |
| work_type | VARCHAR(40) | NO | preventive/corrective/upgrade/inspection/other |
| requested_by | BIGINT UNSIGNED | YES | FK users |
| requested_at | DATETIME | NO | Thời điểm yêu cầu |
| work_start_at | DATETIME | YES | Lịch bắt đầu |
| work_end_at | DATETIME | YES | Lịch kết thúc |
| actual_start_at | DATETIME | YES | Bắt đầu thực tế |
| actual_end_at | DATETIME | YES | Kết thúc thực tế |
| vendor_name | VARCHAR(150) | YES | Nhà thầu/đơn vị |
| personnel | JSON | YES | Danh sách nhân sự nếu cần lưu snapshot |
| equipment_description | TEXT | YES | Thiết bị/phạm vi liên quan |
| work_description | TEXT | NO | Mô tả công việc |
| expected_impact | TEXT | YES | Ảnh hưởng dự kiến |
| rollback_plan | TEXT | YES | Phương án khôi phục |
| post_work_result | TEXT | YES | Kết quả sau công việc |
| status | VARCHAR(30) | NO | draft/pending_approval/approved/rejected/in_progress/completed/verified/cancelled |
| verified_by | BIGINT UNSIGNED | YES | FK users |
| verified_at | DATETIME | YES | |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique: `(customer_id, request_code)`. Index: `(customer_id, server_room_id, status)`, `(work_start_at, work_end_at)`. Nhiều thiết bị liên quan cần bảng pivot riêng hoặc mô hình quan hệ rõ ràng; không dùng chuỗi ID trong JSON làm FK.

### 2.14 `server_room_equipment_movements`

Mục đích: ghi nhận đưa thiết bị vào/ra phòng hoặc chuyển vị trí.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng liên quan |
| asset_id | BIGINT UNSIGNED | YES | FK `server_room_assets.id`; có thể NULL với thiết bị ngoài danh mục |
| movement_code | VARCHAR(60) | NO | Mã phiếu |
| movement_type | VARCHAR(30) | NO | check_in/check_out/relocate |
| asset_tag_snapshot | VARCHAR(100) | YES | Snapshot mã thiết bị |
| equipment_name | VARCHAR(150) | NO | Tên thiết bị |
| serial_number | VARCHAR(150) | YES | Serial |
| source_location | VARCHAR(200) | YES | Vị trí đi |
| destination_location | VARCHAR(200) | YES | Vị trí đến |
| movement_at | DATETIME | NO | Thời điểm |
| reason | TEXT | NO | Lý do |
| performed_by | BIGINT UNSIGNED | YES | FK users |
| approved_by | BIGINT UNSIGNED | YES | FK users |
| approved_at | DATETIME | YES | |
| notes | TEXT | YES | |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique: `(customer_id, movement_code)`. Index: `(customer_id, server_room_id, movement_at)`, `asset_id`. Cần xác định movement relocate có thể chuyển giữa hai phòng Server; nếu có, nên thay `server_room_id` bằng `from_server_room_id` và `to_server_room_id` nullable/có quy tắc bắt buộc theo movement_type. Đây là điểm cần chốt trước migration.

### 2.15 `server_room_environment_logs`

Mục đích: nhật ký điều kiện môi trường và trạng thái hạ tầng phụ trợ.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| recorded_at | DATETIME | NO | Thời điểm đo |
| temperature_c | DECIMAL(5,2) | YES | Nhiệt độ °C |
| humidity_percent | DECIMAL(5,2) | YES | Độ ẩm % |
| air_conditioning_status | VARCHAR(30) | YES | Trạng thái điều hòa |
| ups_status | VARCHAR(30) | YES | Trạng thái UPS |
| power_status | VARCHAR(30) | YES | Trạng thái nguồn |
| source_type | VARCHAR(20) | NO | manual/device/import |
| source_reference | VARCHAR(150) | YES | Mã cảm biến/nguồn |
| remarks | TEXT | YES | Ghi chú |
| recorded_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |

Index: `(customer_id, server_room_id, recorded_at)`, `(source_type, source_reference)`. Cần kiểm tra miền giá trị (humidity 0–100), đơn vị và timezone. Ngưỡng cảnh báo nên cấu hình riêng, không hard-code vào log.

### 2.16 `server_room_approvals`

Mục đích: ghi nhận từng bước phê duyệt cho các yêu cầu SRM.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| approvable_type | VARCHAR(100) | NO | Loại đối tượng |
| approvable_id | BIGINT UNSIGNED | NO | ID đối tượng |
| approval_step | SMALLINT UNSIGNED | NO | Thứ tự bước |
| approver_id | BIGINT UNSIGNED | YES | FK users |
| approver_group | VARCHAR(100) | YES | Nhóm duyệt nếu hỗ trợ |
| status | VARCHAR(20) | NO | pending/approved/rejected/skipped |
| comments | TEXT | YES | Ý kiến |
| assigned_at | DATETIME | YES | Giao duyệt |
| decided_at | DATETIME | YES | Quyết định |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Index: `(customer_id, approvable_type, approvable_id)`, `(approver_id, status)`. Đây là quan hệ polymorphic nên DB không thể tạo FK trực tiếp đến mọi bảng nghiệp vụ. Bắt buộc kiểm tra tenant và đối tượng tồn tại ở service layer; cần quyết định có bảng approval workflow/requests riêng hay không. Cấm người tạo tự duyệt nếu quy trình nghiệp vụ yêu cầu phân tách nhiệm vụ.

### 2.17 `server_room_attachments`

Mục đích: metadata của file đính kèm; file lưu tại disk/object storage, không lưu binary trong DB.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| attachable_type | VARCHAR(100) | NO | Loại đối tượng |
| attachable_id | BIGINT UNSIGNED | NO | ID đối tượng |
| original_name | VARCHAR(255) | NO | Tên file gốc |
| storage_disk | VARCHAR(50) | NO | Disk cấu hình |
| file_path | VARCHAR(500) | NO | Đường dẫn lưu |
| mime_type | VARCHAR(150) | YES | MIME |
| file_size | BIGINT UNSIGNED | NO | Kích thước byte |
| checksum_sha256 | CHAR(64) | YES | Kiểm tra toàn vẹn |
| uploaded_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |

Index: `(customer_id, attachable_type, attachable_id)`, `(uploaded_by)`. Download phải qua authorization kiểm tra tenant/object, không public trực tiếp. Cần chính sách giới hạn MIME, dung lượng, quét malware, retention và xóa file khi hồ sơ được lưu trữ/xóa theo quy định. Polymorphic relation không có FK trực tiếp đến mọi bảng.

### 2.18 `server_room_audit_logs`

Mục đích: lịch sử thao tác nghiệp vụ, ưu tiên append-only.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| auditable_type | VARCHAR(100) | NO | Loại đối tượng |
| auditable_id | BIGINT UNSIGNED | NO | ID đối tượng |
| action | VARCHAR(50) | NO | created/updated/deleted/approved/status_changed/... |
| old_values | JSON | YES | Giá trị trước |
| new_values | JSON | YES | Giá trị sau |
| performed_by | BIGINT UNSIGNED | YES | FK users |
| ip_address | VARCHAR(45) | YES | IPv4/IPv6 |
| user_agent | VARCHAR(500) | YES | Client |
| request_id | VARCHAR(100) | YES | Correlation/request ID |
| created_at | TIMESTAMP | NO | Thời điểm |

Index: `(customer_id, auditable_type, auditable_id, created_at)`, `(performed_by, created_at)`, `(action, created_at)`. Không cho sửa/xóa audit log qua giao diện thông thường. Cần chính sách retention và quyền truy cập log riêng.

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

Các kiểu dữ liệu, cột và index trong tài liệu là đề xuất thiết kế. Các điểm ghi rõ "cần chốt" phải được giải quyết trước khi migration; không được xem bản nháp là schema đã phê duyệt.
