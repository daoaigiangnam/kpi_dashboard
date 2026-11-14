# SRM – Migration Blueprint (bản nháp)

> Trạng thái: DRAFT – bản chuẩn bị migration. Tài liệu này chuẩn hóa danh mục bảng và thứ tự phụ thuộc; chưa phải lệnh triển khai. Chỉ viết migration sau khi rà soát đầy đủ cột, FK, index, unique, delete rule và các quyết định còn mở.

## 1. Mục tiêu

- Chuyển thiết kế logic SRM thành một kế hoạch migration có thứ tự rõ ràng.
- Bảo đảm đủ 22 bảng mới và không tạo trùng các bảng dùng chung hiện hữu.
- Tạo cấu trúc theo hướng multi-tenant với `service_customers` là tenant root.
- Tách rõ FK cứng, composite FK và kiểm tra bắt buộc ở service layer.
- Không thay đổi bảng hiện hữu như `service_customers`, `customer_branches`, `users`, `permissions`, `group_permissions` nếu chưa có migration riêng được đánh giá tác động.

## 2. Master Table Register – 22 bảng

| STT | Bảng | Nhóm | Vai trò | Phụ thuộc chính |
|---:|---|---|---|---|
| 1 | `server_room_locations` | Core | Địa điểm thuộc khách hàng | `service_customers`, `customer_branches` |
| 2 | `server_rooms` | Core | Phòng Server tại location | `server_room_locations`, `service_customers` |
| 3 | `server_room_contacts` | Core | Đầu mối liên hệ phòng | `server_rooms`, `users` |
| 4 | `server_room_assets` | Core | Thiết bị hạ tầng và Rack | `server_rooms`, `service_customers` |
| 5 | `server_room_access_persons` | Core | Người được phép vào phòng | `server_rooms`, `users` |
| 6 | `srm_checklist_templates` | Core | Template checklist có version | `service_customers`, `users` |
| 7 | `srm_checklist_items` | Core | Các hạng mục checklist | `srm_checklist_templates` |
| 8 | `server_room_access_logs` | Core | Nhật ký ra/vào | `server_rooms`, `server_room_access_persons`, `users` |
| 9 | `server_room_inspections` | Core | Phiếu kiểm tra | `server_rooms`, `srm_checklist_templates`, `users` |
| 10 | `server_room_inspection_results` | Core | Kết quả snapshot từng item | `server_room_inspections`, `srm_checklist_items` |
| 11 | `server_room_incidents` | Core | Hồ sơ sự cố | `server_rooms`, `users` |
| 12 | `server_room_incident_actions` | Core | Hành động khắc phục/phòng ngừa | `server_room_incidents`, `users` |
| 13 | `server_room_maintenance_requests` | Core | Yêu cầu bảo trì | `server_rooms`, `users` |
| 14 | `server_room_equipment_movements` | Core | Phiếu nhập/xuất/di chuyển | `server_room_assets`, `server_rooms`, `users` |
| 15 | `server_room_environment_logs` | Core | Nhật ký môi trường | `server_rooms` |
| 16 | `server_room_approvals` | Core | Các bước phê duyệt polymorphic | `service_customers`, `users` |
| 17 | `server_room_attachments` | Core | File đính kèm polymorphic | `service_customers`, `users` |
| 18 | `server_room_audit_logs` | Core | Nhật ký thay đổi nghiệp vụ | `service_customers`, `users` |
| 19 | `server_room_incident_assets` | Pivot | Sự cố ↔ thiết bị | `server_room_incidents`, `server_room_assets` |
| 20 | `server_room_maintenance_assets` | Pivot | Bảo trì ↔ thiết bị | `server_room_maintenance_requests`, `server_room_assets` |
| 21 | `customer_user_srm_permissions` | Access control | Quyền chức năng SRM theo customer user | `service_customers`, `users` |
| 22 | `customer_user_server_rooms` | Access control | Phạm vi phòng được gán cho customer user | `service_customers`, `users`, `server_rooms` |

**Không tạo mới** các bảng `service_customers`, `customer_branches`, `users`, `permissions`, `group_permissions`, `user_groups`, `customer_user_branches`. Đây là các cấu trúc hiện hữu cần tái sử dụng.

## 3. Thứ tự migration đề xuất

Thứ tự dưới đây ưu tiên tạo bảng cha trước bảng con. FK tới bảng polymorphic không tạo FK đa hình; chỉ tạo FK tới tenant/user khi phù hợp và kiểm tra đối tượng nghiệp vụ ở service layer.

| Batch | Bảng | Lý do thứ tự |
|---:|---|---|
| 1 | `server_room_locations` | Gốc địa điểm |
| 2 | `server_rooms` | Phụ thuộc location |
| 3 | `server_room_assets` | Phụ thuộc room; self-FK Rack cần xử lý sau khi tạo bảng |
| 4 | `server_room_contacts` | Phụ thuộc room |
| 5 | `server_room_access_persons` | Phụ thuộc room |
| 6 | `srm_checklist_templates` | Gốc checklist theo tenant |
| 7 | `srm_checklist_items` | Phụ thuộc template |
| 8 | `server_room_access_logs` | Phụ thuộc room và access person |
| 9 | `server_room_inspections` | Phụ thuộc room và template |
| 10 | `server_room_inspection_results` | Phụ thuộc inspection và checklist item |
| 11 | `server_room_incidents` | Phụ thuộc room |
| 12 | `server_room_incident_actions` | Phụ thuộc incident |
| 13 | `server_room_maintenance_requests` | Phụ thuộc room |
| 14 | `server_room_equipment_movements` | Phụ thuộc asset và room nguồn/đích |
| 15 | `server_room_environment_logs` | Phụ thuộc room |
| 16 | `server_room_approvals` | Workflow phê duyệt |
| 17 | `server_room_attachments` | File nghiệp vụ |
| 18 | `server_room_audit_logs` | Audit |
| 19 | `server_room_incident_assets` | Pivot incident–asset |
| 20 | `server_room_maintenance_assets` | Pivot maintenance–asset |
| 21 | `customer_user_srm_permissions` | Cấp quyền chức năng cho customer user |
| 22 | `customer_user_server_rooms` | Gán customer user vào room |

Ghi chú triển khai:
- Có thể tạo `server_room_assets` trước rồi bổ sung self-FK `rack_asset_id` bằng bước FK riêng sau khi bảng tồn tại.
- FK hai chiều/quan hệ tự tham chiếu không được tạo trước bảng đích.
- Nếu FK tới `users` dùng `ON DELETE SET NULL`, cột actor phải nullable.
- Thứ tự rollback phải đảo ngược thứ tự tạo bảng; không drop bảng hiện hữu dùng chung.

## 4. Quy tắc tenant và khóa ghép

### 4.1 Parent keys

Các bảng cha có tenant nên có khóa candidate phục vụ composite FK:
- `server_room_locations`: UNIQUE (`id`, `customer_id`).
- `server_rooms`: UNIQUE (`id`, `customer_id`).
- `server_room_assets`: UNIQUE (`id`, `customer_id`, `server_room_id`) nếu cần ràng buộc Rack cùng phòng.
- `srm_checklist_templates`: UNIQUE (`id`, `customer_id`).
- `server_room_inspections`: UNIQUE (`id`, `customer_id`).
- `server_room_incidents`: UNIQUE (`id`, `customer_id`).
- `server_room_maintenance_requests`: UNIQUE (`id`, `customer_id`).

Đây là đề xuất khóa phục vụ ràng buộc. Cần kiểm tra khả năng tương thích với các unique/index hiện hữu và MySQL 8.0 trước khi migration.

### 4.2 Child relationships

Các quan hệ sau phải kiểm tra tenant cùng lúc với FK:
- Location → Branch.
- Room → Location.
- Mọi room-owned records → Room.
- Checklist Item → Template.
- Inspection → Room + Template.
- Inspection Result → Inspection + Checklist Item (nếu item_id khác NULL).
- Incident Action → Incident.
- Incident Asset pivot → Incident + Asset.
- Maintenance Asset pivot → Maintenance Request + Asset.
- Movement → From Room + To Room + Asset.
- Customer permission/room assignment → User cùng tenant; room assignment → Room cùng tenant.

Không chỉ kiểm tra `customer_id` từ request. Phải lấy tenant từ bản ghi đã truy vấn và xác minh toàn bộ quan hệ trong transaction.

## 5. Polymorphic tables

`server_room_approvals`, `server_room_attachments`, `server_room_audit_logs` dùng cặp loại đối tượng + ID (hoặc cấu trúc tương đương) nên không thể FK trực tiếp tới nhiều bảng nghiệp vụ.

Yêu cầu bắt buộc:
- Allowlist model type ở backend; không nhận class name tùy ý từ client.
- Xác minh đối tượng tồn tại và đúng tenant trước khi insert/read/update.
- Lưu `customer_id` trực tiếp để truy vấn tenant và audit.
- Attachment phải authorize trước khi cấp URL tải file.
- Audit log ưu tiên append-only; không cho sửa/xóa qua CRUD thông thường.

## 6. Unique, index và soft delete – nguyên tắc

1. Các mã nghiệp vụ dùng unique theo tenant: location code, room code, asset tag, checklist code + version, inspection code, incident code, maintenance request code, movement code.
2. Không mặc định unique (`customer_id`, `code`) tương thích với soft delete. Phải chốt có cho tái sử dụng mã sau soft delete hay không.
3. MySQL không có partial unique index thông dụng cho điều kiện `revoked_at IS NULL`; assignment cấp lại cần chọn generated column hoặc bảng current-state + history.
4. Index cần bám truy vấn thực tế: tenant + room + status/date; tránh tạo index trùng tiền tố mà không có lý do.
5. Index trên FK thường cần thiết; xác nhận MySQL đã tự tạo index phù hợp hay chưa để tránh trùng.

## 7. Các quyết định chưa đủ dữ liệu để chốt

Không tự suy đoán các nội dung sau trong migration:
- Cách unique active assignment khi thu hồi rồi cấp lại.
- Chính sách tái sử dụng mã sau soft delete.
- Approval ANY/ALL và cách snapshot group approver.
- Emergency maintenance và quyền kích hoạt/hậu kiểm.
- Inspection review_return về draft hay correction_required.
- Retention theo loại hồ sơ và file.
- Có cho phép user ngoài hệ thống đăng nhập/được gán quyền SRM hay chỉ lưu snapshot.
- Sensor integration và tần suất ghi environment logs.
- Quy tắc primary contact duy nhất theo phòng.
- Cấu trúc response đa lựa chọn của checklist.

## 8. Gate kiểm tra trước khi viết migration

- [ ] Đối chiếu lại đủ 22 bảng với Data Dictionary và ERD.
- [ ] Chốt toàn bộ cột, kiểu, nullable, default và timestamp.
- [ ] Chốt FK đơn/composite FK và thứ tự tạo FK.
- [ ] Chốt unique/index, đặc biệt soft delete và active assignment.
- [ ] Chốt delete rule và retention.
- [ ] Kiểm tra schema hiện hữu, kiểu PK/FK, collation, duplicate data.
- [ ] Chốt workflow còn mở có ảnh hưởng schema.
- [ ] Duyệt migration order và rollback order.
- [ ] Viết test tenant isolation, state transition, SoD và race condition.
- [ ] Chỉ sau khi các gate trên đạt mới bắt đầu tạo Laravel migration.

## 9. Tiến độ

- [x] Master register 22 bảng.
- [x] Thứ tự migration sơ bộ.
- [x] Nguyên tắc tenant integrity và composite FK.
- [x] Column-level schema batch 1: 7 bảng nền tảng (đang chờ chủ dự án xác nhận các điểm nghiệp vụ).
- [x] Column-level schema batch 2: 7 bảng nghiệp vụ (đang chờ chủ dự án xác nhận các điểm nghiệp vụ).
- [x] Column-level schema batch 3: 8 bảng còn lại (đang chờ chủ dự án duyệt các quyết định nghiệp vụ).
- [ ] Chốt toàn bộ FK/unique/index/delete rule.
- [ ] Phê duyệt các quyết định nghiệp vụ còn mở.
- [ ] Viết Laravel migrations.


## Bổ sung tiến độ Batch 3

- Tài liệu chi tiết: [09 – Column-level Schema Batch 3](./09-column-schema-batch-3-draft.md).
- Đã có column-level draft cho toàn bộ 22 bảng; chưa phê duyệt schema cuối và chưa tạo Laravel migrations.


## Schema Review Progress

- [x] Review Batch 1: 7 bảng nền tảng; đã ghi nhận các ràng buộc tenant, Rack/U-position, soft delete, user ownership và checklist versioning.
- [ ] Chủ dự án xác nhận các quyết định mở trong tài liệu 10.
- [ ] Review Batch 2: 7 bảng nghiệp vụ.
- [ ] Review Batch 3: 8 bảng còn lại.
- Tài liệu review: [10 – Schema Review Batch 1](./10-schema-review-batch-1-draft.md).
