# SRM – Column-level Schema, Batch 1 (bản nháp)

> Phạm vi: 7 bảng nền tảng đầu tiên. Đây là bản chuẩn hóa kiểu cột và ràng buộc ở mức thiết kế; chưa phải Laravel migration đã được duyệt. Các bảng hiện hữu dùng làm FK không bị sửa trong tài liệu này.

## 1. Quy ước áp dụng cho batch

- Engine: InnoDB; charset/collation theo chuẩn database hiện hữu, dự kiến utf8mb4.
- ID: BIGINT UNSIGNED AUTO_INCREMENT.
- FK tới `service_customers.id`, `customer_branches.id`, `users.id` phải khớp chính xác kiểu cột hiện hữu.
- Boolean: BOOLEAN (MySQL lưu dưới dạng TINYINT(1)); ứng dụng chỉ ghi 0/1.
- Các cột status/type dùng VARCHAR có giới hạn thay vì MySQL ENUM ở giai đoạn đầu để dễ mở rộng; giá trị hợp lệ được kiểm soát bằng validation/constants.
- Timestamp nghiệp vụ dùng DATETIME; created_at/updated_at/deleted_at theo Laravel timestamps/soft deletes.
- Các bảng có dữ liệu lịch sử hoặc được tham chiếu bởi hồ sơ đã phát sinh không hard delete.
- customer_id là tenant key. Với quan hệ nội bộ SRM, ưu tiên composite FK hoặc service-layer tenant check có test âm tính.

## 2. Bảng 1 – server_room_locations

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | FK service_customers.id |
| branch_id | BIGINT UNSIGNED | YES | NULL | FK customer_branches.id; cùng customer |
| code | VARCHAR(50) | NO | — | Mã location |
| name | VARCHAR(150) | NO | — | Tên location |
| location_type | VARCHAR(30) | NO | — | branch/head_office/data_center/independent |
| address | VARCHAR(255) | YES | NULL | Địa chỉ |
| provider_name | VARCHAR(150) | YES | NULL | Nhà cung cấp DC/địa điểm |
| contact_name | VARCHAR(150) | YES | NULL | Người liên hệ |
| contact_phone | VARCHAR(50) | YES | NULL | Điện thoại |
| contact_email | VARCHAR(190) | YES | NULL | Email |
| description | TEXT | YES | NULL | Mô tả |
| status | VARCHAR(20) | NO | active | active/inactive/archived đề xuất |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |
| deleted_at | TIMESTAMP | YES | NULL | Soft delete |

**Khóa và index**
- PK: id.
- UNIQUE: (customer_id, code) – chưa cho tái sử dụng code sau soft delete cho đến khi có quyết định chính sách.
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, location_type, status), (branch_id, customer_id).

**Quy tắc**
- location_type=branch yêu cầu branch_id.
- Nếu branch_id khác NULL, customer_branches.customer_id phải bằng customer_id.
- Data Center là thông tin nhà cung cấp/địa điểm, không phải tenant.
- Không xóa location còn server room hoặc hồ sơ lịch sử.

## 3. Bảng 2 – server_rooms

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| location_id | BIGINT UNSIGNED | NO | — | FK location cùng tenant |
| code | VARCHAR(50) | NO | — | Mã phòng |
| name | VARCHAR(150) | NO | — | Tên phòng |
| floor | VARCHAR(50) | YES | NULL | Tầng/khu vực |
| area_sqm | DECIMAL(10,2) | YES | NULL | Diện tích; không âm |
| description | TEXT | YES | NULL | Mô tả |
| status | VARCHAR(20) | NO | active | active/inactive/maintenance/archived đề xuất |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users.id |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |
| deleted_at | TIMESTAMP | YES | NULL | Soft delete |

**Khóa và index**
- PK: id.
- UNIQUE: (customer_id, code).
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, location_id, status), (created_by).

**Quy tắc**
- (location_id, customer_id) tham chiếu (server_room_locations.id, customer_id).
- Không chuyển room sang location thuộc tenant khác.
- Không xóa room còn asset, access log, inspection, incident, maintenance hoặc movement history.

## 4. Bảng 3 – server_room_contacts

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| user_id | BIGINT UNSIGNED | YES | NULL | FK users.id nếu có account |
| full_name | VARCHAR(150) | NO | — | Họ tên |
| email | VARCHAR(190) | YES | NULL | Email |
| phone | VARCHAR(50) | YES | NULL | Điện thoại |
| role | VARCHAR(50) | NO | — | Vai trò liên hệ |
| is_primary | BOOLEAN | NO | 0 | Đầu mối chính |
| is_active | BOOLEAN | NO | 1 | Hiệu lực |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa và index**
- PK: id.
- INDEX: (customer_id, server_room_id, is_active), (user_id).
- Chưa tạo unique primary contact vì chính sách một hay nhiều đầu mối chính chưa được chủ dự án xác nhận.

**Quy tắc**
- Room và contact phải cùng customer_id.
- user_id chỉ là liên kết tài khoản tùy chọn; full_name/email/phone vẫn là dữ liệu liên hệ hiển thị.
- Ngừng hiệu lực bằng is_active=0; không cần xóa vật lý.

## 5. Bảng 4 – server_room_assets

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| asset_tag | VARCHAR(100) | NO | — | Mã tài sản SRM |
| asset_name | VARCHAR(150) | NO | — | Tên thiết bị |
| category | VARCHAR(50) | NO | — | server/firewall/switch/router/storage/nas/ups/pdu/rack/cooling/other |
| manufacturer | VARCHAR(100) | YES | NULL | Hãng |
| model | VARCHAR(100) | YES | NULL | Model |
| serial_number | VARCHAR(150) | YES | NULL | Serial |
| rack_asset_id | BIGINT UNSIGNED | YES | NULL | Self-FK tới Rack trong cùng room |
| rack_u_start | SMALLINT UNSIGNED | YES | NULL | U bắt đầu |
| rack_u_height | SMALLINT UNSIGNED | YES | NULL | Số U chiếm dụng |
| rack_total_u | SMALLINT UNSIGNED | YES | NULL | Chỉ dùng với category=rack |
| management_ip | VARCHAR(45) | YES | NULL | IPv4/IPv6 |
| installed_at | DATE | YES | NULL | Ngày lắp |
| warranty_expiry | DATE | YES | NULL | Hết bảo hành |
| status | VARCHAR(30) | NO | active | active/inactive/maintenance/retired đề xuất |
| notes | TEXT | YES | NULL | Ghi chú |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |
| deleted_at | TIMESTAMP | YES | NULL | Soft delete |

**Khóa và index**
- PK: id.
- UNIQUE: (customer_id, asset_tag).
- UNIQUE phục vụ composite FK: (id, customer_id, server_room_id).
- INDEX: (customer_id, server_room_id, category, status), (customer_id, serial_number), (rack_asset_id).
- Self-FK Rack nên tạo sau khi bảng đã được tạo.

**Quy tắc**
- Rack là một asset có category=rack; không tạo bảng Rack riêng.
- Rack và thiết bị gắn Rack phải cùng customer_id và server_room_id.
- rack_total_u chỉ có giá trị cho Rack; rack_asset_id/rack_u_start/rack_u_height chỉ dùng cho thiết bị đặt trong Rack.
- U-position collision phải được kiểm tra trong transaction và có cơ chế khóa chống ghi đồng thời.
- Không tạo unique serial_number vì thiết bị có thể không có serial hoặc dữ liệu serial từ nhà sản xuất không đảm bảo duy nhất toàn hệ thống.

## 6. Bảng 5 – server_room_access_persons

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| user_id | BIGINT UNSIGNED | YES | NULL | FK users.id nếu có account |
| full_name | VARCHAR(150) | NO | — | Họ tên |
| organization | VARCHAR(150) | YES | NULL | Đơn vị |
| job_title | VARCHAR(100) | YES | NULL | Chức danh |
| access_reason | TEXT | YES | NULL | Lý do cấp |
| access_level | VARCHAR(30) | NO | supervised | permanent/supervised đề xuất |
| valid_from | DATETIME | YES | NULL | Thời điểm bắt đầu |
| valid_until | DATETIME | YES | NULL | Thời điểm hết hạn |
| approval_status | VARCHAR(30) | NO | draft | draft/pending_approval/approved/rejected/revoked/expired |
| approved_by | BIGINT UNSIGNED | YES | NULL | FK users.id |
| approved_at | DATETIME | YES | NULL | Thời điểm duyệt |
| revoked_at | DATETIME | YES | NULL | Thời điểm thu hồi |
| notes | TEXT | YES | NULL | Ghi chú |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users.id |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa và index**
- PK: id.
- INDEX: (customer_id, server_room_id, approval_status), (user_id), (valid_until).
- Không hard delete bản ghi đã được duyệt hoặc đã phát sinh access log.

**Quy tắc**
- Đây là danh sách quyền được phép vào phòng; không phải nhật ký lượt vào thực tế.
- Người ngoài hệ thống được lưu bằng snapshot full_name/organization/job_title; user_id nullable.
- Không cho check-in nếu quyền đã revoked/expired, trừ luồng emergency có audit.

## 7. Bảng 6 – srm_checklist_templates

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| code | VARCHAR(50) | NO | — | Mã template |
| name | VARCHAR(150) | NO | — | Tên template |
| description | TEXT | YES | NULL | Mô tả |
| frequency | VARCHAR(30) | NO | custom | daily/weekly/monthly/after_maintenance/custom |
| version | INT UNSIGNED | NO | 1 | Phiên bản bắt đầu từ 1 |
| status | VARCHAR(30) | NO | draft | draft/pending_approval/approved/rejected/inactive |
| effective_from | DATE | YES | NULL | Ngày hiệu lực |
| effective_until | DATE | YES | NULL | Ngày hết hiệu lực |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users.id |
| approved_by | BIGINT UNSIGNED | YES | NULL | FK users.id |
| approved_at | DATETIME | YES | NULL | Thời điểm duyệt |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa và index**
- PK: id.
- UNIQUE: (customer_id, code, version).
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, status, effective_from).

**Quy tắc**
- Template version đã approved không sửa nội dung; thay đổi tạo version mới.
- Checklist template thuộc duy nhất một customer.
- Chưa quyết định tái sử dụng code sau soft delete; hiện không thêm deleted_at để tránh vô tình làm mất ý nghĩa version/history.

## 8. Bảng 7 – srm_checklist_items

| Cột | Kiểu chốt đề xuất | Null | Default | Ràng buộc/Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant, phải bằng template |
| template_id | BIGINT UNSIGNED | NO | — | FK template cùng tenant |
| item_code | VARCHAR(50) | NO | — | Mã hạng mục |
| item_name | VARCHAR(200) | NO | — | Tên hạng mục |
| description | TEXT | YES | NULL | Hướng dẫn |
| response_type | VARCHAR(30) | NO | pass_fail_na | pass_fail_na/yes_no/number/text/date |
| is_required | BOOLEAN | NO | 1 | Bắt buộc |
| require_evidence | BOOLEAN | NO | 0 | Cần chứng cứ |
| sort_order | INT | NO | 0 | Thứ tự hiển thị |
| is_active | BOOLEAN | NO | 1 | Hiệu lực |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa và index**
- PK: id.
- UNIQUE: (template_id, item_code).
- Composite FK: (template_id, customer_id) → srm_checklist_templates(id, customer_id).
- INDEX: (customer_id, template_id, is_active, sort_order).

**Quy tắc**
- Item phải thuộc template cùng tenant.
- Không sửa item làm thay đổi nội dung của template version đã approved; tạo version mới.
- Chưa hỗ trợ multi-select trong batch này; cần quyết định nghiệp vụ trước khi bổ sung response schema.

## 9. Các quyết định cần chủ dự án xác nhận trước khi coi batch này là FINAL

1. Có cho phép tái sử dụng code location/room/asset sau soft delete không? Đề xuất: không tái sử dụng trong giai đoạn đầu.
2. Một phòng có được có nhiều primary contacts không? Tài liệu hiện giữ is_primary nhưng chưa ép unique.
3. Người ngoài hệ thống trong access persons chỉ lưu snapshot hay cần danh bạ vendor/contact dùng chung?
4. access_level có đúng hai giá trị permanent/supervised không?
5. Checklist có cần response_type multi_select không?
6. Template approved có được phép tạo version mới bằng thao tác clone không? Đề xuất: có, version mới là bản ghi mới.

## 10. Trạng thái batch

- [x] Chuẩn hóa 7 bảng nền tảng ở mức column-level.
- [x] Bổ sung Rack self-reference và quy tắc U-position.
- [x] Xác định quan hệ tenant cho location/room/asset/checklist.
- [ ] Chủ dự án xác nhận các quyết định ở mục 9.
- [ ] Chốt FK/index/delete rule ở migration review.
