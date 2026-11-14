# SRM – Column-level Schema, Batch 2 (bản nháp)

> Phạm vi: 7 bảng nghiệp vụ phát sinh và vận hành. Nội dung được chuẩn hóa từ Data Dictionary và State Machine hiện tại; các cột bổ sung để lưu trạng thái/chủ thể/thời điểm chuyển trạng thái được ghi rõ là đề xuất cần duyệt. Chưa tạo migration.

## 1. Quy ước

- PK: BIGINT UNSIGNED AUTO_INCREMENT.
- customer_id bắt buộc trên các bảng nghiệp vụ trong batch.
- Các quan hệ room/inspection/template/incident/asset phải enforce cùng tenant bằng composite FK hoặc service-layer check trong transaction kèm test.
- Status/type dùng VARCHAR có allowlist ở backend; không dùng MySQL ENUM.
- DATETIME lưu thời điểm nghiệp vụ; timestamps Laravel dùng created_at/updated_at.
- Hồ sơ đã submit hoặc hoàn tất không hard delete.

## 2. Bảng 8 – server_room_access_logs

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room |
| access_person_id | BIGINT UNSIGNED | YES | NULL | FK access person; nullable cho visitor/ngoại lệ |
| visitor_name | VARCHAR(150) | NO | — | Snapshot tên thực tế |
| organization | VARCHAR(150) | YES | NULL | Đơn vị |
| purpose | TEXT | NO | — | Mục đích |
| entry_at | DATETIME | NO | — | Thời gian vào dự kiến/thực tế theo status |
| exit_at | DATETIME | YES | NULL | Thời gian ra |
| approver_id | BIGINT UNSIGNED | YES | NULL | FK users |
| supervisor_id | BIGINT UNSIGNED | YES | NULL | FK users |
| work_performed | TEXT | YES | NULL | Công việc |
| completion_note | TEXT | YES | NULL | Ghi chú hoàn tất |
| status | VARCHAR(30) | NO | registered | registered/checked_in/completed/cancelled |
| exception_reason | TEXT | YES | NULL | Đề xuất bổ sung: bắt buộc khi exception_close hoặc emergency override |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE chưa đề xuất trên visitor_name/entry_at vì không phải định danh ổn định.
- INDEX: (customer_id, server_room_id, entry_at), (customer_id, status, entry_at), (access_person_id), (supervisor_id, status).
- Composite FK (server_room_id, customer_id) → server_rooms(id, customer_id).
- Nếu access_person_id khác NULL, phải cùng customer_id và server_room_id.

**State rules**
- registered → checked_in: người được phép hoặc ngoại lệ đã được duyệt.
- checked_in → completed: ghi exit_at.
- registered → cancelled: chưa check-in.
- checked_in → completed bằng exception_close: bắt buộc exception_reason và actor có quyền.
- exit_at >= entry_at; khi status=checked_in thì exit_at phải NULL; khi completed thì exit_at bắt buộc.
- entry_at nên được hiểu là thời điểm check-in thực tế; nếu cần lưu lịch đăng ký trước, bổ sung scheduled_entry_at riêng ở batch quyết định nghiệp vụ.

## 3. Bảng 9 – server_room_inspections

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| template_id | BIGINT UNSIGNED | NO | — | FK template cùng tenant |
| template_version | INT UNSIGNED | NO | — | Snapshot version |
| inspection_code | VARCHAR(60) | NO | — | Mã phiếu |
| inspection_date | DATE | NO | — | Ngày kiểm tra |
| started_at | DATETIME | YES | NULL | Bắt đầu |
| completed_at | DATETIME | YES | NULL | Hoàn tất kiểm tra |
| frequency | VARCHAR(30) | YES | NULL | Snapshot tần suất |
| inspector_id | BIGINT UNSIGNED | YES | NULL | FK users |
| inspector_name | VARCHAR(150) | NO | — | Snapshot người kiểm tra |
| conclusion | VARCHAR(40) | YES | NULL | pass/corrective_action_required/emergency |
| status | VARCHAR(30) | NO | draft | draft/submitted/reviewed/correction_required/closed/cancelled |
| general_remarks | TEXT | YES | NULL | Nhận xét |
| submitted_at | DATETIME | YES | NULL | Thời điểm gửi |
| reviewed_by | BIGINT UNSIGNED | YES | NULL | FK users |
| reviewed_at | DATETIME | YES | NULL | Thời điểm review |
| closed_by | BIGINT UNSIGNED | YES | NULL | FK users |
| closed_at | DATETIME | YES | NULL | Thời điểm đóng |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE: (customer_id, inspection_code).
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, server_room_id, inspection_date), (customer_id, status, inspection_date), (template_id, template_version).
- Composite FK room/template phải bảo đảm cùng customer_id.

**State rules**
- draft → submitted: đủ item bắt buộc và kết quả hợp lệ.
- submitted → reviewed: reviewer có quyền.
- submitted → correction_required: trả về có lý do; nếu quyết định sau cùng dùng draft thay vì correction_required thì cần bỏ trạng thái này đồng bộ toàn hệ thống.
- reviewed → closed: kết luận và chứng cứ đầy đủ.
- draft/submitted → cancelled theo quyền và lý do.
- Sau submitted không sửa trực tiếp snapshot kết quả; sửa chữa phải có revision/audit.

## 4. Bảng 10 – server_room_inspection_results

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| inspection_id | BIGINT UNSIGNED | NO | — | FK inspection |
| checklist_item_id | BIGINT UNSIGNED | YES | NULL | FK item gốc; có thể NULL để giữ snapshot |
| item_code_snapshot | VARCHAR(50) | NO | — | Mã snapshot |
| item_name_snapshot | VARCHAR(200) | NO | — | Tên snapshot |
| description_snapshot | TEXT | YES | NULL | Hướng dẫn snapshot |
| response_type_snapshot | VARCHAR(30) | NO | — | pass_fail_na/yes_no/number/text/date |
| result | VARCHAR(30) | YES | NULL | pass/fail/na hoặc yes/no theo response type |
| numeric_value | DECIMAL(18,4) | YES | NULL | Giá trị số |
| text_value | TEXT | YES | NULL | Giá trị text |
| date_value | DATE | YES | NULL | Giá trị ngày |
| boolean_value | BOOLEAN | YES | NULL | Giá trị boolean |
| remarks | TEXT | YES | NULL | Ghi chú |
| corrective_action | TEXT | YES | NULL | Hành động khắc phục |
| sort_order | INT | NO | 0 | Thứ tự |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE: (inspection_id, item_code_snapshot) – giả định mỗi item xuất hiện một lần/phiếu.
- INDEX: (customer_id, inspection_id), (checklist_item_id).
- Composite FK (inspection_id, customer_id) → inspections(id, customer_id).
- checklist_item_id nullable; khi có giá trị phải cùng customer_id. Có thể dùng composite FK (checklist_item_id, customer_id) nếu bảng item có unique tương ứng.

**Validation**
- response_type_snapshot quyết định cột nào được phép có giá trị.
- pass_fail_na/yes_no dùng result; number dùng numeric_value; text dùng text_value; date dùng date_value. boolean_value chỉ giữ nếu thực sự cần kiểu yes/no riêng, nếu không có thể loại bỏ để tránh hai cách biểu diễn boolean.
- Không cho nhiều trường value xung đột cùng có dữ liệu.
- Ảnh/chứng cứ liên kết qua server_room_attachments.

## 5. Bảng 11 – server_room_incidents

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| incident_code | VARCHAR(60) | NO | — | Mã sự cố |
| incident_type | VARCHAR(50) | NO | — | Nhóm sự cố |
| severity | VARCHAR(20) | NO | medium | low/medium/high/critical |
| title | VARCHAR(200) | NO | — | Tiêu đề |
| detected_at | DATETIME | NO | — | Thời điểm phát hiện |
| reported_at | DATETIME | YES | NULL | Thời điểm báo cáo |
| reported_by | BIGINT UNSIGNED | YES | NULL | FK users |
| reporter_name | VARCHAR(150) | YES | NULL | Snapshot người báo |
| description | TEXT | NO | — | Mô tả |
| initial_impact | TEXT | YES | NULL | Ảnh hưởng ban đầu |
| immediate_action | TEXT | YES | NULL | Xử lý tức thời |
| root_cause | TEXT | YES | NULL | Nguyên nhân gốc |
| status | VARCHAR(30) | NO | open | open/investigating/in_progress/resolved/closed/cancelled |
| resolved_at | DATETIME | YES | NULL | Thời điểm xử lý |
| verified_by | BIGINT UNSIGNED | YES | NULL | FK users |
| verified_at | DATETIME | YES | NULL | Xác minh |
| closed_by | BIGINT UNSIGNED | YES | NULL | FK users |
| closed_at | DATETIME | YES | NULL | Đóng |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE: (customer_id, incident_code).
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, server_room_id, detected_at), (customer_id, severity, status), (customer_id, status, detected_at).
- Composite FK room + tenant.

**State rules**
- open → in_progress khi phân công/tiếp nhận.
- in_progress → resolved khi có root cause/action/result phù hợp.
- resolved → closed sau verify.
- resolved → in_progress khi reopen có lý do.
- open/in_progress → cancelled có lý do và đúng quyền.
- Data Dictionary đang liệt kê investigating và verified nhưng State Machine chưa mô tả transition riêng. Không chốt hai trạng thái này trong DB cho đến khi nghiệp vụ xác nhận; có thể giữ allowlist tương thích nếu UI/flow hiện tại cần.

## 6. Bảng 12 – server_room_incident_actions

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| incident_id | BIGINT UNSIGNED | NO | — | FK incident cùng tenant |
| action_type | VARCHAR(30) | NO | corrective | corrective/preventive |
| description | TEXT | NO | — | Nội dung |
| assigned_to | BIGINT UNSIGNED | YES | NULL | FK users |
| assigned_name | VARCHAR(150) | YES | NULL | Snapshot người/đơn vị ngoài |
| due_at | DATETIME | YES | NULL | Hạn |
| started_at | DATETIME | YES | NULL | Bắt đầu |
| completed_at | DATETIME | YES | NULL | Hoàn thành |
| status | VARCHAR(30) | NO | pending | pending/in_progress/completed/verified/cancelled |
| completion_note | TEXT | YES | NULL | Kết quả |
| verification_note | TEXT | YES | NULL | Xác minh |
| verified_by | BIGINT UNSIGNED | YES | NULL | FK users |
| verified_at | DATETIME | YES | NULL | Thời điểm xác minh |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- INDEX: (customer_id, incident_id), (assigned_to, status, due_at).
- Composite FK (incident_id, customer_id) → incidents(id, customer_id).
- UNIQUE không cần thiết ở action description; nhiều action có thể giống nội dung.

**State rules**
- pending → in_progress → completed → verified.
- pending/in_progress → cancelled theo quyền, có lý do/audit.
- Không hard delete action khi incident đã đóng.

## 7. Bảng 13 – server_room_maintenance_requests

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| server_room_id | BIGINT UNSIGNED | NO | — | FK room cùng tenant |
| request_code | VARCHAR(60) | NO | — | Mã yêu cầu |
| title | VARCHAR(200) | NO | — | Tiêu đề |
| work_type | VARCHAR(40) | NO | — | preventive/corrective/upgrade/inspection/other |
| requested_by | BIGINT UNSIGNED | YES | NULL | FK users |
| requested_at | DATETIME | NO | — | Thời điểm yêu cầu |
| work_start_at | DATETIME | YES | NULL | Lịch bắt đầu |
| work_end_at | DATETIME | YES | NULL | Lịch kết thúc |
| actual_start_at | DATETIME | YES | NULL | Bắt đầu thực tế |
| actual_end_at | DATETIME | YES | NULL | Kết thúc thực tế |
| vendor_name | VARCHAR(150) | YES | NULL | Nhà thầu |
| personnel | JSON | YES | NULL | Snapshot nhân sự ngoài; không dùng làm quan hệ FK |
| equipment_description | TEXT | YES | NULL | Mô tả thiết bị/phạm vi |
| work_description | TEXT | NO | — | Nội dung công việc |
| expected_impact | TEXT | YES | NULL | Ảnh hưởng |
| rollback_plan | TEXT | YES | NULL | Phương án khôi phục |
| post_work_result | TEXT | YES | NULL | Kết quả |
| status | VARCHAR(30) | NO | draft | draft/pending_approval/approved/rejected/in_progress/completed/verified/cancelled |
| verified_by | BIGINT UNSIGNED | YES | NULL | FK users |
| verified_at | DATETIME | YES | NULL | Thời điểm xác minh |
| emergency_reason | TEXT | YES | NULL | Đề xuất bổ sung nếu bật emergency maintenance |
| emergency_started_by | BIGINT UNSIGNED | YES | NULL | Đề xuất bổ sung, FK users |
| emergency_started_at | DATETIME | YES | NULL | Đề xuất bổ sung |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE: (customer_id, request_code).
- UNIQUE phục vụ composite FK: (id, customer_id).
- INDEX: (customer_id, server_room_id, status), (customer_id, work_start_at, work_end_at), (requested_by, status).
- Composite FK room + tenant.

**State rules**
- draft → pending_approval.
- pending_approval → approved/rejected.
- approved → in_progress.
- in_progress → completed.
- completed → verified.
- pending_approval/approved → cancelled.
- Emergency maintenance hiện là quyết định chưa chốt; chỉ giữ các cột emergency_* như phương án tùy chọn, không đưa vào migration nếu chủ dự án chưa duyệt.
- Validate work_end_at >= work_start_at và actual_end_at >= actual_start_at.

## 8. Bảng 14 – server_room_equipment_movements

| Cột | Kiểu đề xuất | Null | Default | Ghi chú |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| customer_id | BIGINT UNSIGNED | NO | — | Tenant |
| asset_id | BIGINT UNSIGNED | YES | NULL | FK asset cùng tenant; NULL nếu thiết bị ngoài danh mục |
| from_server_room_id | BIGINT UNSIGNED | YES | NULL | FK room nguồn |
| to_server_room_id | BIGINT UNSIGNED | YES | NULL | FK room đích |
| movement_code | VARCHAR(60) | NO | — | Mã phiếu |
| movement_type | VARCHAR(30) | NO | — | check_in/check_out/relocate |
| asset_tag_snapshot | VARCHAR(100) | YES | NULL | Snapshot mã |
| equipment_name | VARCHAR(150) | NO | — | Snapshot tên |
| serial_number | VARCHAR(150) | YES | NULL | Snapshot serial |
| source_location | VARCHAR(200) | YES | NULL | Snapshot vị trí nguồn |
| destination_location | VARCHAR(200) | YES | NULL | Snapshot vị trí đích |
| movement_at | DATETIME | NO | — | Thời điểm yêu cầu/thực hiện; cần thống nhất semantics |
| reason | TEXT | NO | — | Lý do |
| status | VARCHAR(30) | NO | draft | Đề xuất bổ sung: draft/pending_approval/approved/rejected/completed/cancelled |
| performed_by | BIGINT UNSIGNED | YES | NULL | FK users |
| approved_by | BIGINT UNSIGNED | YES | NULL | FK users |
| approved_at | DATETIME | YES | NULL | Thời điểm duyệt |
| executed_at | DATETIME | YES | NULL | Đề xuất bổ sung: thời điểm thực thi |
| notes | TEXT | YES | NULL | Ghi chú |
| created_by | BIGINT UNSIGNED | YES | NULL | FK users |
| created_at | TIMESTAMP | YES | NULL | Laravel |
| updated_at | TIMESTAMP | YES | NULL | Laravel |

**Khóa/index**
- PK: id.
- UNIQUE: (customer_id, movement_code).
- INDEX: (customer_id, from_server_room_id, movement_at), (customer_id, to_server_room_id, movement_at), (asset_id), (customer_id, status, movement_at).
- Quan hệ composite tenant tới from room, to room và asset.
- Nếu cần composite FK asset cùng room, asset cần candidate unique (id, customer_id, server_room_id).

**Quy tắc movement_type**
- check_in: from=NULL, to bắt buộc.
- check_out: from bắt buộc, to=NULL.
- relocate: from và to bắt buộc.
- from/to cùng tenant; relocate giữa phòng thì from != to; đổi Rack/U trong cùng phòng cho phép from=to.
- Asset nếu có phải thuộc tenant; với movement relocate giữa phòng, vị trí hiện tại của asset phải khớp from room.
- Khi execute, cập nhật server_room_assets.server_room_id và rack/U trong cùng DB transaction.
- Movement completed bất biến; điều chỉnh bằng phiếu mới.

**State rules**
- draft → pending_approval → approved → completed.
- pending_approval → rejected.
- draft/pending_approval/approved → cancelled nếu chưa execute.
- Chỉ execute khi approved; cập nhật asset và movement trong cùng transaction.
- Data Dictionary ban đầu thiếu status/executed_at trong khi State Machine yêu cầu các bước này; đây là phần bổ sung schema đề xuất, cần xác nhận trước migration.

## 9. Các điểm cần chủ dự án xác nhận

1. Access Log: entry_at là giờ đăng ký hay giờ check-in thực tế? Nếu có đặt lịch, đề xuất thêm scheduled_entry_at.
2. Inspection: chốt review_return trả về draft hay dùng correction_required.
3. Inspection Result: có cần multi-select không? Có cần boolean_value riêng hay dùng result cho yes/no?
4. Incident: có cần trạng thái investigating và verified riêng hay chuẩn hóa theo State Machine open → in_progress → resolved → closed?
5. Maintenance: có bật emergency maintenance? Nếu có, vai trò nào được kích hoạt và hậu kiểm trong bao lâu?
6. Movement: chốt movement_at là thời điểm yêu cầu hay thực hiện; đề xuất tách requested_at và executed_at nếu cần báo cáo cả hai.
7. Movement: asset ngoài danh mục (asset_id NULL) có được phép check_in/check_out không, và sau đó có quy trình tạo asset record không?

## 10. Trạng thái batch

- [x] Chuẩn hóa 7 bảng nghiệp vụ.
- [x] Bổ sung status/actor/time fields cần thiết để biểu diễn state machine, ghi rõ phần còn chờ duyệt.
- [x] Định nghĩa index và tenant relationships ở mức thiết kế.
- [ ] Chủ dự án xác nhận các quyết định mục 9.
- [ ] Rà soát FK/index/delete rules trước migration.
