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
| customer_id | BIGINT UNSIGNED | NO | Tenant; phải bằng customer_id của template |
| template_id | BIGINT UNSIGNED | NO | FK template, cùng tenant |
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

Unique dự kiến: `(template_id, item_code)`. Tạo composite FK `(template_id, customer_id)` → `srm_checklist_templates(id, customer_id)`; không suy tenant chỉ bằng kiểm tra giao diện.

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

Mục đích: ghi nhận đưa thiết bị vào/ra phòng hoặc chuyển vị trí; giữ lịch sử Movement và Asset, không xóa hồ sơ khi check-out.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| asset_id | BIGINT UNSIGNED | YES | FK `server_room_assets.id`; NULL với thiết bị ngoài danh mục |
| from_server_room_id | BIGINT UNSIGNED | YES | FK phòng nguồn; NULL với check_in |
| to_server_room_id | BIGINT UNSIGNED | YES | FK phòng đích; NULL với check_out |
| movement_code | VARCHAR(60) | NO | Mã phiếu |
| movement_type | VARCHAR(30) | NO | check_in/check_out/relocate |
| asset_tag_snapshot | VARCHAR(100) | YES | Snapshot mã thiết bị |
| equipment_name | VARCHAR(150) | NO | Snapshot tên thiết bị |
| serial_number | VARCHAR(150) | YES | Snapshot serial |
| source_location | VARCHAR(200) | YES | Ghi chú vị trí nguồn; không thay thế Rack/U có cấu trúc |
| destination_location | VARCHAR(200) | YES | Ghi chú vị trí đích; không thay thế Rack/U có cấu trúc |
| from_rack_asset_id | BIGINT UNSIGNED | YES | Rack nguồn; phải thuộc from_server_room_id |
| from_rack_u_start | SMALLINT UNSIGNED | YES | U bắt đầu nguồn |
| to_rack_asset_id | BIGINT UNSIGNED | YES | Rack đích; phải thuộc to_server_room_id |
| to_rack_u_start | SMALLINT UNSIGNED | YES | U bắt đầu đích |
| requested_at | DATETIME | NO | Thời điểm tạo yêu cầu Movement |
| executed_at | DATETIME | YES | Thời điểm thực tế hoàn tất Movement |
| reason | TEXT | NO | Lý do |
| status | VARCHAR(30) | NO | draft/pending_approval/approved/rejected/completed/cancelled |
| performed_by | BIGINT UNSIGNED | YES | FK users; bắt buộc khi completed |
| approved_by | BIGINT UNSIGNED | YES | FK users; bắt buộc khi approved/completed |
| approved_at | DATETIME | YES | Bắt buộc khi approved/completed |
| notes | TEXT | YES | Ghi chú |
| created_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique: `(customer_id, movement_code)`. Index dự kiến: `(customer_id, from_server_room_id, requested_at)`, `(customer_id, to_server_room_id, requested_at)`, `(asset_id)`, `(customer_id, status, requested_at)`. Quy tắc theo movement_type: check_in bắt buộc to và để from NULL; check_out bắt buộc from và để to NULL; relocate bắt buộc cả from/to. Hai phòng phải cùng tenant; relocate giữa phòng khác nhau, đổi Rack/U trong cùng phòng cho phép from=to. Asset (nếu có) phải cùng tenant; với relocate/check_out, Asset phải đang thuộc from room tại thời điểm execute.

B11: `requested_at` là thời điểm tạo yêu cầu; `executed_at` là thời điểm thực tế hoàn tất, không dùng một `movement_at` cho cả hai. B12: trạng thái Movement là `draft`, `pending_approval`, `approved`, `rejected`, `completed`, `cancelled`; khi approved cần `approved_by`/`approved_at`, khi completed cần `performed_by`/`executed_at`; không cho draft → completed. B13: `asset_id` được NULL cho thiết bị ngoài danh mục; bắt buộc snapshot tên, Serial Number và Asset Tag nếu có; không tự động tạo Asset Record khi check-in. B14: Rack/U nguồn/đích lưu bằng các cột có cấu trúc; Rack phải thuộc đúng Server Room; thiết bị không gắn Rack được phép để các trường Rack/U tương ứng NULL; text chỉ là ghi chú. B15: check-out không xóa Asset Record; cập nhật trạng thái `checked_out` hoặc trạng thái tương đương trong allowlist; check-in lại bằng Movement mới. B16: execute relocate/check_out kiểm tra Asset đúng from room, khóa Asset bằng row-level lock và cập nhật Asset/Movement trong cùng transaction; lỗi thì rollback toàn bộ.

### 2.15 `server_room_environment_logs`

Mục đích: nhật ký điều kiện môi trường và trạng thái hạ tầng phụ trợ.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | FK phòng |
| recorded_at | DATETIME | NO | Thời điểm đo; lưu UTC, giao diện và truy vấn theo timezone customer |
| temperature_c | DECIMAL(5,2) | YES | Nhiệt độ °C |
| humidity_percent | DECIMAL(5,2) | YES | Độ ẩm % |
| air_conditioning_status | VARCHAR(30) | YES | Trạng thái điều hòa |
| ups_status | VARCHAR(30) | YES | Trạng thái UPS |
| power_status | VARCHAR(30) | YES | Trạng thái nguồn |
| source_type | VARCHAR(20) | NO | manual/device/import |
| source_reference | VARCHAR(150) | YES | Mã cảm biến/nguồn; device/import bắt buộc ở service layer |
| idempotency_key | VARCHAR(100) | YES | Khóa sự kiện nguồn; device/import bắt buộc để chống ghi trùng |
| remarks | TEXT | YES | Ghi chú |
| recorded_by | BIGINT UNSIGNED | YES | FK users |
| created_at | TIMESTAMP | YES | |

Index: `(customer_id, server_room_id, recorded_at)`, `(source_type, source_reference)`, UNIQUE `(customer_id, source_type, source_reference, idempotency_key)` cho sự kiện device/import. `recorded_at` lưu UTC; người dùng nhập điều kiện thời gian theo timezone customer, backend quy đổi sang UTC trước truy vấn và hiển thị kết quả theo timezone customer. Device/import phải xác thực nguồn, cung cấp source_reference + idempotency_key và được kiểm tra chống replay; source_reference không phải credential. Retention cấu hình theo loại dữ liệu/tần suất; hỗ trợ archive, không tự xóa trước khi policy được duyệt. Kiểm tra humidity 0–100; ngưỡng cảnh báo cấu hình riêng.

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


## 5. Bổ sung quan hệ ERD sau rà soát

Tham chiếu tài liệu [ERD và quan hệ dữ liệu](./03-erd-and-relationships-draft.md).

Đề xuất bổ sung hai bảng liên kết nhiều-nhiều: server_room_incident_assets và server_room_maintenance_assets; tổng cộng 20 bảng dự kiến (18 bảng cốt lõi + 2 bảng liên kết). Rack được biểu diễn bằng tài sản có category=rack, thiết bị tham chiếu Rack qua rack_asset_id; chưa tạo bảng Rack riêng. Bảng movement cần hỗ trợ from_server_room_id và to_server_room_id thay cho một server_room_id đơn.

### 5.1 Các cột bổ sung/chỉnh sửa đề xuất

- server_room_assets: thêm rack_asset_id, rack_u_start, rack_u_height, rack_total_u; Rack là bản ghi có category=rack.
- server_room_equipment_movements: dùng from_server_room_id, to_server_room_id, asset_id; lưu snapshot thiết bị và vị trí nguồn/đích.
- Tạo server_room_incident_assets để một sự cố liên kết nhiều thiết bị.
- Tạo server_room_maintenance_assets để một yêu cầu bảo trì liên kết nhiều thiết bị.

### 5.2 Các vấn đề còn cần xác nhận

- Tính hợp lệ của Rack và vị trí U phải được kiểm tra trong transaction; cần xác định có cho phép thiết bị chiếm nhiều U không.
- Movement giữa hai phòng chỉ được phép trong cùng customer_id.
- Các bảng pivot phải enforce tenant consistency cho cả hai đầu quan hệ.
- Approval/Attachment/Audit polymorphic không có FK cứng tới từng bảng; cần allowlist và authorization ở service layer.


---

## 6. Quyền SRM và phạm vi truy cập Customer User (đã chốt)

### 6.1 customer_user_srm_permissions

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| user_id | BIGINT UNSIGNED | NO | Customer account cùng tenant |
| permission_code | VARCHAR(120) | NO | Mã quyền SRM thuộc allowlist |
| granted_by | BIGINT UNSIGNED | YES | Người cấp |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Unique (customer_id, user_id, permission_code). Index (user_id, customer_id), (customer_id, permission_code). Ràng buộc: users.account_type=customer và users.service_customer_id=customer_id. Không cấp quyền internal/platform. Mọi thay đổi phải audit.

### 6.2 customer_user_server_rooms

Mục đích: gán phạm vi phòng Server cụ thể cho từng customer user. Không có assignment thì mặc định không truy cập phòng nào.

| Cột | Kiểu dự kiến | Null | Ghi chú |
|---|---|---:|---|
| id | BIGINT UNSIGNED | NO | PK |
| customer_id | BIGINT UNSIGNED | NO | Tenant |
| user_id | BIGINT UNSIGNED | NO | Customer account cùng tenant |
| server_room_id | BIGINT UNSIGNED | NO | Phòng Server cùng tenant |
| granted_by | BIGINT UNSIGNED | YES | Người cấp |
| revoked_by | BIGINT UNSIGNED | YES | Người thu hồi |
| revoked_at | DATETIME | YES | Thời điểm thu hồi |
| created_at | TIMESTAMP | YES | |
| updated_at | TIMESTAMP | YES | |

Không hard delete assignment đã cấp. Khi cần cấp lại, phải bảo toàn lịch sử cấp/thu hồi; cách đặt unique active assignment sẽ được chốt ở schema review.

### 6.3 Tổng số bảng

18 bảng SRM core + 2 bảng pivot + 2 bảng access-control = 22 bảng mới dự kiến.


## 7. Chuẩn hóa các điểm đã thống nhất cho thiết kế Migration

1. Tổng số bảng mới của SRM là 22: 18 bảng nghiệp vụ cốt lõi, 2 bảng pivot và 2 bảng phân quyền Customer User.
2. Equipment Movement dùng `from_server_room_id` và `to_server_room_id`; không còn dùng một `server_room_id` đơn để biểu diễn nguồn/đích.
3. Checklist Item có `customer_id` và phải tham chiếu template qua cặp `(template_id, customer_id)` để bảo đảm cùng tenant.
4. Các quan hệ con có `customer_id` phải được thiết kế FK ghép hoặc kiểm tra service-layer có test tenant isolation; không được chỉ dựa vào ID đơn.
5. Đây vẫn là Data Dictionary bản nháp. Các kiểu cột, FK cụ thể, index, unique, soft-delete và delete rule phải được rà soát ở bảng Migration Blueprint trước khi viết migration.


## Bổ sung Data Dictionary – Approval Delegation (C06)

Bổ sung bảng thứ 23 `server_room_approval_delegations` với các trường: id, customer_id, principal_approver_id, delegate_user_id, approvable_type (nullable), scope_server_room_id (nullable), valid_from, valid_until, status, reason, requested_at, approved_by, approved_at, revoked_by, revoked_at, created_at, updated_at. Chi tiết kiểu dữ liệu, FK và index theo mục 12 của tài liệu Column Schema Batch 3.

Bảng quản lý ủy quyền theo thời hạn và phạm vi; không tạo bản ghi delegation riêng cho từng request. Khi một approval level được kích hoạt, hệ thống mới tìm ủy quyền hợp lệ và snapshot kết quả vào `server_room_approvals`.

Bổ sung vào `server_room_approvals`: `acting_approver_id` (người được giao xử lý tại thời điểm kích hoạt), `acted_by` (người thực tế bấm quyết định), `delegation_id` (ủy quyền được áp dụng, nullable). `approver_id` luôn là principal approver gốc. Không có delegation thì acting_approver_id trùng approver_id; acted_by được ghi khi có quyết định.



## 8. C09 – Attachment Retention (APPROVED – 2026-11-15)

- Không xóa tự động file khi parent Archived/Soft Deleted.
- Retention cấu hình theo loại hồ sơ/tài liệu và policy nghiệp vụ; chưa hard-code thời hạn.
- Legal Hold được quản lý ở parent record; tất cả attachment của parent tự động được bảo vệ, không có Legal Hold độc lập từng file.
- Parent đang Legal Hold chặn purge tất cả attachment liên quan dù retention đã hết.
- Khi gỡ Legal Hold, file chỉ đủ điều kiện purge nếu retention đã hết, không còn reference hợp lệ và có Audit actor/thời điểm/kết quả.
- Cách lưu Legal Hold ở parent và cơ chế resolve attachment sẽ được chốt trong Final Schema Review; không tự thêm bảng/cột ở bước này.


## 9. C09-B – Legal Hold event history (APPROVED – 2026-11-15)

- Lưu từng lần đặt/gỡ Legal Hold thành event riêng, không chỉ ghi đè trạng thái hiện tại.
- Event gồm parent identity, action, actor, thời điểm UTC, lý do; release phải tham chiếu lần placement tương ứng.
- Không hard delete lịch sử; thao tác đặt/gỡ phải có authorization và Audit.
- Cấu trúc lưu trữ sẽ được chốt trong Final Schema Review.


## C10 – Data Dictionary note: `server_room_audit_logs` (APPROVED – 2026-11-15)

- Bảng Audit Log vận hành theo append-only; ứng dụng không cập nhật/xóa audit rows trong luồng thông thường.
- DB runtime account không có quyền `UPDATE`/`DELETE` trên bảng này; quyền đặc biệt được tách biệt và kiểm soát.
- Archive riêng với quyền sửa/xóa hạn chế là phương án hỗ trợ khi compliance yêu cầu; không mặc định một công nghệ archive cụ thể.
- Event ghi actor, UTC timestamp, action, target object, result và trace/correlation ID khi có.
- Không tuyên bố bất biến tuyệt đối trước DBA/quản trị hạ tầng đặc quyền. Các quyền DB và archive là implementation/security controls, không phải cột dữ liệu mới.

## C11 – Xác nhận tên cột Pivot (APPROVED – 2026-11-15)

- `server_room_incident_assets.impact_note`: ghi nhận ảnh hưởng của Asset trong Incident.
- `server_room_maintenance_assets.work_note`: ghi chú công việc bảo trì liên quan đến Asset.
- Giữ nguyên tên cột riêng theo ngữ nghĩa nghiệp vụ; không đổi thành `relationship_note`.


## C12 – Data Dictionary: Pivot Asset Snapshot (APPROVED – 2026-11-15)

Cả `server_room_incident_assets` và `server_room_maintenance_assets` lưu hai trường snapshot `asset_tag` và `asset_name` tại thời điểm liên kết Asset. Snapshot bảo toàn tên/mã lịch sử và không tự thay đổi khi bản ghi Asset gốc được cập nhật. Chi tiết kiểu dữ liệu/độ dài/nullability được chốt trong Final Schema Review.


## C13 – Data Dictionary: Pivot Room Scope (APPROVED – 2026-11-15)

- `server_room_incident_assets`: Asset liên kết có thể nằm ở nhiều Room khác nhau trong cùng `customer_id`.
- `server_room_maintenance_assets`: Asset mặc định phải thuộc Room của Maintenance Request; trường hợp đa phòng yêu cầu khai báo scope rõ ràng.
- Tenant và Room scope được Service xác thực trước khi ghi liên kết; không chỉ dựa vào FK ID đơn.


## C14 – Data Dictionary: Customer Permission Revoke (APPROVED – 2026-11-15)

Bảng `customer_user_srm_permissions` giữ lịch sử grant/revoke. Thu hồi cập nhật `revoked_at`, `revoked_by`; cấp lại tạo row mới. Mỗi `(customer_id, user_id, permission_code)` chỉ có một grant active. Cơ chế unique/index cụ thể chờ xác minh MySQL version và schema hiện hữu.


## C15 – Data Dictionary: Customer Room Assignment (APPROVED – 2026-11-15)

Bảng `customer_user_server_rooms` giữ lịch sử assignment. Thu hồi cập nhật `revoked_at`, `revoked_by`; cấp lại tạo row mới. Mỗi `(customer_id, user_id, server_room_id)` chỉ có một assignment active. Chiến lược unique/index cụ thể chờ xác minh MySQL version và schema hiện hữu.


## C16 – Data Dictionary: Customer User Tenant Integrity (APPROVED – 2026-11-15)

Các record SRM được truy cập bởi Customer User phải xác minh `users.account_type='customer'` và `users.service_customer_id` bằng `customer_id` của record. Cơ chế FK ghép chỉ áp dụng nếu schema `users` hiện hữu hỗ trợ; nếu không, Service/Policy tenant check và negative tests là bắt buộc.


## C17 – Customer IT Admin Scope (APPROVED – 2026-11-15)

1. Customer IT Admin chỉ được cấp hoặc thu hồi quyền SRM cho Customer User thuộc cùng `customer_id` với chính mình.
2. Customer IT Admin chỉ được gán hoặc thu hồi quyền truy cập các Server Room thuộc tenant của mình.
3. Chỉ được cấp các `permission_code` nằm trong allowlist quyền SRM dành cho Customer Admin; không thể tự cấp quyền Internal/Platform hoặc nâng quyền vượt phạm vi được phép.
4. Mọi thao tác grant/revoke permission và room assignment đều phải được authorization theo tenant, kiểm tra trạng thái user/room, và ghi Audit actor, target, action, timestamp UTC, result.
5. Server-side phải resolve tenant từ user đã xác thực; không tin `customer_id`, `user_id` hoặc `server_room_id` do client gửi nếu chưa xác minh ownership.
6. Cần negative tests: Customer IT Admin tenant A không thể grant/revoke user tenant B, gán/revoke room tenant B, hoặc cấp permission ngoài allowlist.


## C18 – Customer User Disable / Tenant Change (APPROVED – 2026-11-15)

1. Khi Customer User bị `disabled`/`inactive`, hệ thống lập tức từ chối mọi truy cập SRM; permission và room assignment hiện hữu được giữ lại làm lịch sử/trạng thái, nhưng không được sử dụng để cấp quyền truy cập khi user bị khóa.
2. Khi Customer User chuyển `customer_id` sang tenant khác, toàn bộ permission và room assignment của tenant cũ phải được revoke; không tự động chuyển quyền sang tenant mới.
3. Giữ nguyên lịch sử grant/revoke và assignment; không hard-delete dữ liệu lịch sử.
4. Quy trình cập nhật trạng thái user, thu hồi permission và room assignment phải được thực hiện nhất quán trong transaction ở mức ứng dụng/DB phù hợp; nếu thất bại phải rollback, không để trạng thái một phần.
5. Khi user được kích hoạt lại hoặc chuyển tenant hoàn tất, quyền truy cập chỉ được khôi phục/cấp mới theo quy trình cấp quyền của tenant tương ứng; không tự khôi phục quyền tenant cũ.
6. Ghi Audit cho disable/enable, tenant change, các thao tác revoke và các lần truy cập SRM bị từ chối; ghi actor, target, UTC timestamp, action, result và correlation ID nếu có.
7. Negative tests bắt buộc: disabled user không truy cập được; user sau tenant change không còn quyền/room assignment tenant cũ; user không được tự động nhận quyền tenant mới; lỗi giữa quy trình không để lại permission/assignment ở trạng thái dở dang.
