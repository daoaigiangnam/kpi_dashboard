# SRM – Migration Blueprint (bản nháp)

> Trạng thái: DRAFT – bản chuẩn bị migration. Tài liệu này chuẩn hóa danh mục bảng và thứ tự phụ thuộc; chưa phải lệnh triển khai. Chỉ viết migration sau khi rà soát đầy đủ cột, FK, index, unique, delete rule và các quyết định còn mở.

## 1. Mục tiêu

- Chuyển thiết kế logic SRM thành một kế hoạch migration có thứ tự rõ ràng.
- Bảo đảm đủ 23 bảng mới và không tạo trùng các bảng dùng chung hiện hữu.
- Tạo cấu trúc theo hướng multi-tenant với `service_customers` là tenant root.
- Tách rõ FK cứng, composite FK và kiểm tra bắt buộc ở service layer.
- Không thay đổi bảng hiện hữu như `service_customers`, `customer_branches`, `users`, `permissions`, `group_permissions` nếu chưa có migration riêng được đánh giá tác động.

## 2. Master Table Register – 23 bảng

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
| 17 | `server_room_approval_delegations` | Core | Ủy quyền phê duyệt theo thời hạn/phạm vi | `service_customers`, `users`, `server_rooms` |
| 18 | `server_room_attachments` | Core | File đính kèm polymorphic | `service_customers`, `users` |
| 19 | `server_room_audit_logs` | Core | Nhật ký thay đổi nghiệp vụ | `service_customers`, `users` |
| 20 | `server_room_incident_assets` | Pivot | Sự cố ↔ thiết bị | `server_room_incidents`, `server_room_assets` |
| 21 | `server_room_maintenance_assets` | Pivot | Bảo trì ↔ thiết bị | `server_room_maintenance_requests`, `server_room_assets` |
| 22 | `customer_user_srm_permissions` | Access control | Quyền chức năng SRM theo customer user | `service_customers`, `users` |
| 23 | `customer_user_server_rooms` | Access control | Phạm vi phòng được gán cho customer user | `service_customers`, `users`, `server_rooms` |

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
| 16 | `server_room_approval_delegations` | Delegation phải tồn tại trước approval FK |
| 17 | `server_room_approvals` | Workflow phê duyệt, tham chiếu delegation |
| 18 | `server_room_attachments` | File nghiệp vụ |
| 19 | `server_room_audit_logs` | Audit |
| 20 | `server_room_incident_assets` | Pivot incident–asset |
| 21 | `server_room_maintenance_assets` | Pivot maintenance–asset |
| 22 | `customer_user_srm_permissions` | Cấp quyền chức năng cho customer user |
| 23 | `customer_user_server_rooms` | Gán customer user vào room |

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
- [x] Column-level schema batch 1: 7 bảng nền tảng (các quyết định Batch 1 đã được ghi nhận trong Decision Register).
- [x] Column-level schema batch 2: 7 bảng nghiệp vụ (B01–B16 đã APPROVED; schema Movement đã đồng bộ).
- [x] Column-level schema batch 3: 8 bảng còn lại (đang chờ chủ dự án duyệt các quyết định nghiệp vụ).
- [ ] Chốt toàn bộ FK/unique/index/delete rule.
- [ ] Phê duyệt các quyết định nghiệp vụ còn mở.
- [ ] Viết Laravel migrations.


## Bổ sung tiến độ Batch 3

- Tài liệu chi tiết: [09 – Column-level Schema Batch 3](./09-column-schema-batch-3-draft.md).
- Đã có column-level draft cho toàn bộ 22 bảng; chưa phê duyệt schema cuối và chưa tạo Laravel migrations.


## Schema Review Progress

- [x] Review Batch 1: 7 bảng nền tảng; đã ghi nhận các ràng buộc tenant, Rack/U-position, soft delete, user ownership và checklist versioning.
- [ ] Hoàn tất đối chiếu các quyết định và điểm còn mở trong tài liệu 10 với Decision Register.
- [x] Review Batch 2: 7 bảng nghiệp vụ (đã review; B01–B16 đã APPROVED).
- [ ] Review Batch 3: 8 bảng còn lại.
- Tài liệu review: [10 – Schema Review Batch 1](./10-schema-review-batch-1-draft.md).


## 7. Tiến độ Schema Review

- [x] Schema Review Batch 1: 7 bảng nền tảng – tài liệu [10](./10-schema-review-batch-1-draft.md).
- [x] Schema Review Batch 2: 7 bảng nghiệp vụ – tài liệu [11](./11-schema-review-batch-2-draft.md).
- [x] Schema Review Batch 3: 8 bảng cuối – tài liệu [12](./12-schema-review-batch-3-draft.md).
- [x] Hoàn tất review sơ bộ 22/22 bảng.
- [ ] Hợp nhất các quyết định còn mở của Batch 3 và baseline kỹ thuật; chốt final schema.
- [ ] Đối chiếu schema/migrations thực tế trước khi tạo SRM migrations.
- [ ] Tạo migrations theo thứ tự phụ thuộc và chạy test tenant/state/permission.

**Lưu ý:** Hoàn tất review không đồng nghĩa schema đã được phê duyệt. Batch 1 và Batch 2 có các quyết định đã ghi nhận trong Decision Register; Batch 3: C01–C06 đã APPROVED; C07–C18 và baseline kỹ thuật nhóm D vẫn còn mở. Phải hoàn tất các quyết định ảnh hưởng cấu trúc và kiểm tra database thực tế trước migration.


## 8. Final Schema Decision Gate

- [x] Tổng hợp quyết định còn mở từ Batch 1, 2, 3 vào [13 – Final Schema Decision Register](./13-final-schema-decision-register-draft.md).
- [ ] Chủ dự án xác nhận nhóm quyết định P0.
- [ ] Chủ dự án xác nhận các quyết định P1 còn mở hoặc chấp nhận ghi nhận là policy triển khai.
- [x] C01–C03 Environment Logs đã APPROVED; `recorded_at` lưu UTC, timezone customer được xử lý ở application/query layer, device/import phải xác thực nguồn + idempotency + chống replay, retention/archive theo policy.
- [x] C04–C06 Approval đã APPROVED; workflow tuần tự theo cấp, ALL approvers mỗi cấp, snapshot approver khi kích hoạt cấp, delegation/reassignment có kiểm soát và audit.
- [ ] Cập nhật đồng bộ Data Dictionary, ERD, Column Schema và State Machines.
- [ ] Kiểm tra schema MySQL hiện hữu, FK/index/unique và dữ liệu duplicate.
- [ ] Final Schema Approval.
- [ ] Laravel Migrations.

**Gate:** Không bắt đầu migration khi các quyết định P0 chưa được xác nhận và schema database thực tế chưa được kiểm tra.


## 10. Bổ sung Migration Blueprint – Approval Delegation (bảng 23)

Bổ sung bảng `server_room_approval_delegations` vào master register. Thứ tự migration phải tạo delegation trước `server_room_approvals` để approval rows có FK `delegation_id`:
1. Tạo `server_room_approval_delegations` sau các bảng nền tảng có tenant/users/rooms.
2. Tạo `server_room_approvals` với các cột `acting_approver_id`, `acted_by`, `delegation_id` và FK phù hợp.
3. Các bảng attachments, audit logs, pivots và access-control tiếp tục theo dependency order.

Master register cập nhật: 23 bảng mới = 18 core + 2 pivot + 2 access-control + 1 approval-delegation. Không tạo bảng `approval_events` riêng ở quyết định này; sự kiện delegation và reassignment được ghi vào audit log, còn trạng thái snapshot nằm trên approval row.

**Migration gate bổ sung:** kiểm tra user/customer ownership, room scope, overlap delegation, valid_from < valid_until, FK delegation_id và test snapshot tại thời điểm activate level trước khi chạy migration.


## 11. C07 – Migration và test gate

C07 không làm tăng số bảng hoặc yêu cầu thêm cột mới. Đây là quy tắc thực thi ở Approval Service/Policy:
- Khi activate level: resolve principal approvers, loại requester, ghi nhận lý do và kiểm tra danh sách còn hợp lệ.
- Nếu danh sách rỗng: rollback activation; không tạo level pending rỗng và không skip.
- Sau delegation resolution: bảo đảm acting approver không phải requester.
- Reassignment phải có authorization và audit; không làm giảm ALL.
- Bổ sung test SoD vào bộ kiểm thử trước migration và trước release.


## 12. C08 – Attachment Security migration/test gate

- Bảng `server_room_attachments` bổ sung `scan_status` (default `pending`, allowlist `pending/clean/infected/error`) và `scanned_at` nullable (UTC); không thêm bảng mới.
- `file_path` là đường dẫn private nội bộ, không tạo public URL hoặc endpoint trả trực tiếp đường dẫn.
- Storage phải hỗ trợ private quarantine; file chỉ được preview/download khi scan status là `clean`.
- Service/API phải xác minh MIME từ nội dung, allowlist, dung lượng cấu hình, tenant, parent record và authorization.
- Test bắt buộc: pending không tải được; infected/error không tải được; clean tải được khi có quyền; user khác tenant bị từ chối; giả mạo MIME/extension bị từ chối; không lộ file_path; scanner timeout/error là fail-closed.


## 13. C09 – Attachment Retention migration/test gate (APPROVED – 2026-11-15)

- Không tạo cascade delete từ parent record sang metadata/file storage; Archived hoặc Soft Deleted không đồng nghĩa được purge.
- Retention period phải đọc từ policy cấu hình theo loại hồ sơ/tài liệu, không hard-code số năm.
- Legal Hold được quản lý ở parent record; purge job phải xác định parent của từng attachment và chặn purge nếu parent đang Legal Hold.
- Purge job chỉ xóa khi retention attachment đã hết, parent không còn Legal Hold, không còn tham chiếu hợp lệ; sau đó ghi Audit actor/thời điểm/kết quả.
- Test bắt buộc: parent archived/soft-deleted vẫn giữ file; retention chưa hết thì giữ; parent Legal Hold bảo vệ tất cả attachment dù retention hết; gỡ hold thì attachment chỉ đủ điều kiện purge khi các điều kiện còn lại thỏa; có reference thì không purge; purge đủ điều kiện phải audit; lỗi purge không được xóa metadata sai lệch.
- Chi tiết schema lưu Legal Hold tại parent và truy vấn attachment vẫn là gate của Final Schema Review; chưa tạo migration.


## 14. C09-B – Legal Hold event history migration/test gate (APPROVED – 2026-11-15)

- Mỗi lần đặt/gỡ hold phải tạo event lịch sử mới, không chỉ update cờ trạng thái trên parent.
- Event cần parent identity, action, actor, UTC timestamp, reason; release phải tham chiếu placement tương ứng.
- Test: nhiều chu kỳ place/release vẫn truy vết đầy đủ; không sửa/xóa lịch sử; actor không đủ quyền bị từ chối; release không có placement hợp lệ bị từ chối; timezone hiển thị đúng từ UTC.
- Chọn cấu trúc lưu trữ sau khi rà soát polymorphic parent và schema users/audit; chưa tạo migration.


## 15. C10 – Audit Log Immutability migration/security gate (APPROVED – 2026-11-15)

- `server_room_audit_logs` là append-only ở tầng ứng dụng; không xây dựng CRUD update/delete cho audit rows.
- Tài khoản DB runtime phải bị thu hồi quyền `UPDATE` và `DELETE` trên bảng Audit Log. Quyền migration/DBA dùng tài khoản riêng, không dùng chung runtime credential.
- Nếu compliance yêu cầu, archive sang vùng lưu trữ riêng với access control hạn chế sửa/xóa; cấu hình cụ thể theo policy và năng lực hạ tầng, không mặc định tuyên bố WORM/object-lock khi chưa xác minh.
- Audit event tối thiểu gồm actor, UTC timestamp, action, target object, result và trace/correlation ID khi có.
- Gate trước release: kiểm tra grants thực tế; thử UPDATE/DELETE bằng runtime account phải bị từ chối; kiểm tra quyền đọc log, retention/archive và audit các thao tác đặc quyền.
- Lưu ý: các kiểm soát này không chống được tuyệt đối DBA/quản trị hạ tầng có đặc quyền; không mô tả là immutable tuyệt đối.

## C11 – Migration/schema naming gate (APPROVED – 2026-11-15)

- Migration cho pivot Incident–Asset phải giữ cột `impact_note` trên `server_room_incident_assets`.
- Migration cho pivot Maintenance–Asset phải giữ cột `work_note` trên `server_room_maintenance_assets`.
- Không dùng `relationship_note`; kiểm tra tên cột nhất quán giữa migration, model, validation, API và UI.


## C12 – Pivot Asset Snapshot migration/test gate (APPROVED – 2026-11-15)

- Bổ sung/duy trì hai trường snapshot `asset_tag` và `asset_name` trên cả hai pivot Incident–Asset và Maintenance–Asset.
- Khi tạo pivot row, Service phải đọc Asset hợp lệ trong đúng tenant và ghi snapshot trong cùng transaction với thao tác liên kết.
- Không cập nhật snapshot khi Asset gốc đổi tên/mã; mọi báo cáo lịch sử phải dùng snapshot.
- Test: tạo liên kết lưu đúng mã/tên tại thời điểm đó; đổi tên/mã Asset không làm thay đổi snapshot; tenant mismatch không tạo được pivot row.
- Kiểu dữ liệu, độ dài và nullability được chốt ở Final Schema Review; chưa tạo migration.


## C13 – Pivot Room Scope migration/test gate (APPROVED – 2026-11-15)

- Enforce tenant consistency cho cả hai đầu của từng pivot.
- Incident–Asset phải cho phép nhiều Room trong cùng tenant; không thêm ràng buộc buộc cùng Room.
- Maintenance–Asset mặc định phải cùng Room; nếu hỗ trợ đa phòng, request phải khai báo scope rõ ràng và service xác thực từng Asset thuộc một Room trong scope.
- Kiểm tra scope khi tạo/cập nhật pivot và kiểm tra lại vị trí Asset khi execute nghiệp vụ liên quan.
- Test bắt buộc: khác tenant bị từ chối; Incident đa phòng cùng tenant hợp lệ; Maintenance cùng phòng hợp lệ; Maintenance khác phòng nhưng chưa khai báo scope bị từ chối; Maintenance đa phòng có scope hợp lệ được chấp nhận.


## 16. C14 – Customer Permission Revoke migration/test gate (APPROVED – 2026-11-15)

- Bảo toàn grant history: revoke cập nhật `revoked_at`/`revoked_by`; không hard-delete; re-grant tạo row mới.
- Enforce tối đa một grant active cho `(customer_id, user_id, permission_code)`.
- Trước khi chọn generated column/unique index, phải xác minh phiên bản MySQL và khả năng hỗ trợ biểu thức/index cần dùng; đồng thời rà soát duplicate active rows và index hiện hữu.
- Nếu DB không hỗ trợ phương án unique phù hợp, thực thi grant/revoke trong transaction với locking phù hợp và kiểm thử cạnh tranh; không chỉ dựa vào kiểm tra ở UI.
- Test: revoke giữ lịch sử; re-grant tạo row mới; không thể tạo hai active grant kể cả hai request đồng thời; user khác tenant/actor thiếu quyền không thể cấp hoặc thu hồi.


## 17. C15 – Customer Room Assignment migration/test gate (APPROVED – 2026-11-15)

- Giữ lịch sử assignment: revoke cập nhật `revoked_at`/`revoked_by`; không hard-delete; cấp lại tạo row mới.
- Enforce tối đa một assignment active cho `(customer_id, user_id, server_room_id)`.
- Trước khi chọn generated column/unique index, xác minh MySQL version, hỗ trợ generated columns/indexes, dữ liệu duplicate và index hiện hữu.
- Nếu DB không hỗ trợ phương án unique phù hợp, grant/revoke phải chạy trong transaction với locking phù hợp; không chỉ kiểm tra ở UI.
- Test: revoke giữ lịch sử; cấp lại tạo row mới; không thể tạo hai assignment active kể cả request đồng thời; cross-tenant user/room bị từ chối; user không có assignment active không truy cập được Room.


## 18. C16 – Customer User Tenant Integrity migration/test gate (APPROVED – 2026-11-15)

- Trước migration, inspect `users.account_type`, `users.service_customer_id`, primary/unique keys, indexes, existing FKs và dữ liệu orphan/không nhất quán.
- Chỉ tạo composite FK/candidate key nếu tương thích schema hiện hữu và không ảnh hưởng các module khác.
- Nếu không khả thi, Service/Policy phải enforce `account_type='customer'` và `service_customer_id=customer_id` trên mọi thao tác SRM; kiểm thử cả read, create, update, delete, attachment/download và API endpoints.
- Negative tests bắt buộc: customer user tenant A không đọc/ghi record tenant B; giả mạo customer_id không vượt được kiểm tra; internal/platform account không bị nhận diện nhầm là customer; disabled user không được truy cập.
- Không tạo migration cho users trước khi hoàn tất schema inspection và kiểm tra dữ liệu thực tế.


## C17 – Customer IT Admin Scope (APPROVED – 2026-11-15)

1. Customer IT Admin chỉ được cấp hoặc thu hồi quyền SRM cho Customer User thuộc cùng `customer_id` với chính mình.
2. Customer IT Admin chỉ được gán hoặc thu hồi quyền truy cập các Server Room thuộc tenant của mình.
3. Chỉ được cấp các `permission_code` nằm trong allowlist quyền SRM dành cho Customer Admin; không thể tự cấp quyền Internal/Platform hoặc nâng quyền vượt phạm vi được phép.
4. Mọi thao tác grant/revoke permission và room assignment đều phải được authorization theo tenant, kiểm tra trạng thái user/room, và ghi Audit actor, target, action, timestamp UTC, result.
5. Server-side phải resolve tenant từ user đã xác thực; không tin `customer_id`, `user_id` hoặc `server_room_id` do client gửi nếu chưa xác minh ownership.
6. Cần negative tests: Customer IT Admin tenant A không thể grant/revoke user tenant B, gán/revoke room tenant B, hoặc cấp permission ngoài allowlist.

- Migration/seed permission phải tạo allowlist quyền SRM dành cho Customer Admin; xác minh mapping role-permission hiện hữu trước khi thay đổi.
- Kiểm thử negative cases cross-tenant và privilege escalation trước khi nghiệm thu.


## C18 – Customer User Disable / Tenant Change (APPROVED – 2026-11-15)

1. Khi Customer User bị `disabled`/`inactive`, hệ thống lập tức từ chối mọi truy cập SRM; permission và room assignment hiện hữu được giữ lại làm lịch sử/trạng thái, nhưng không được sử dụng để cấp quyền truy cập khi user bị khóa.
2. Khi Customer User chuyển `customer_id` sang tenant khác, toàn bộ permission và room assignment của tenant cũ phải được revoke; không tự động chuyển quyền sang tenant mới.
3. Giữ nguyên lịch sử grant/revoke và assignment; không hard-delete dữ liệu lịch sử.
4. Quy trình cập nhật trạng thái user, thu hồi permission và room assignment phải được thực hiện nhất quán trong transaction ở mức ứng dụng/DB phù hợp; nếu thất bại phải rollback, không để trạng thái một phần.
5. Khi user được kích hoạt lại hoặc chuyển tenant hoàn tất, quyền truy cập chỉ được khôi phục/cấp mới theo quy trình cấp quyền của tenant tương ứng; không tự khôi phục quyền tenant cũ.
6. Ghi Audit cho disable/enable, tenant change, các thao tác revoke và các lần truy cập SRM bị từ chối; ghi actor, target, UTC timestamp, action, result và correlation ID nếu có.
7. Negative tests bắt buộc: disabled user không truy cập được; user sau tenant change không còn quyền/room assignment tenant cũ; user không được tự động nhận quyền tenant mới; lỗi giữa quy trình không để lại permission/assignment ở trạng thái dở dang.

- Trước triển khai, xác định nguồn sự kiện disable/enable và tenant change trong module User hiện hữu; không giả định có event hook nếu chưa kiểm tra code.
- Kiểm thử rollback tại từng điểm lỗi giữa cập nhật user lifecycle và revoke permission/room assignment.


## D01 – MySQL Baseline (APPROVED – 2026-11-15)

MySQL 8.0 là baseline mục tiêu theo xác nhận của người dùng. Trước migration phải chạy kiểm tra phiên bản 8.x cụ thể, `version_comment`, `sql_mode`, charset/collation, storage engine và schema hiện hữu. Không mặc định minor/patch release hoặc cấu hình server.


### D01 – Runtime evidence (verified 2026-11-15)

Thông tin truy vấn qua kết nối Laravel đang hoạt động tới database `kpi_dashboard` trong container `kpi-dashboard-db-1`:

- `VERSION()`: `8.0.46`
- `version_comment`: `MySQL Community Server - GPL`
- `sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`
- `character_set_server`: `utf8mb4`
- `collation_server`: `utf8mb4_unicode_ci`
- `default_storage_engine`: `InnoDB`

Kết luận: runtime xác minh là MySQL Community Server 8.0.46, InnoDB, charset/collation server `utf8mb4`/`utf8mb4_unicode_ci`. Đây là bằng chứng môi trường tại thời điểm kiểm tra; trước production migration vẫn phải kiểm tra lại trên đúng môi trường triển khai.

- Baseline migration đã được xác minh trên container hiện tại; dùng InnoDB cho các bảng SRM cần transaction và foreign key. Kiểm tra lại `sql_mode` và server variables ở môi trường đích trước triển khai.


## D02 – Storage Engine and Transaction Policy (APPROVED – 2026-11-15)

1. Toàn bộ 23 bảng SRM sử dụng InnoDB.
2. Các thao tác nghiệp vụ cập nhật nhiều bảng có quan hệ phải được thực hiện trong một Database Transaction phù hợp.
3. Nếu một bước bắt buộc trong transaction thất bại, rollback toàn bộ thay đổi thuộc transaction; không để lại trạng thái nghiệp vụ dở dang.
4. Không sử dụng MyISAM cho các bảng SRM.
5. Với thao tác đồng thời có nguy cơ tranh chấp dữ liệu, Service phải đánh giá và sử dụng cơ chế khóa phù hợp; `SELECT ... FOR UPDATE` được dùng khi cần khóa bản ghi để bảo vệ invariant, kết hợp transaction và thứ tự khóa nhất quán để hạn chế deadlock.
6. Các transaction phải ngắn, không giữ row lock trong lúc gọi dịch vụ mạng hoặc thực hiện tác vụ chậm; có xử lý lỗi deadlock/lock timeout phù hợp.
7. Kiểm thử phải bao gồm rollback khi lỗi giữa chừng và các tình huống concurrent update cho các invariant quan trọng.

- Migration phải khai báo `ENGINE=InnoDB` rõ ràng cho từng bảng SRM; xác minh engine sau migration.
- Thiết kế transaction và lock theo invariant nghiệp vụ; tránh giữ lock trong lúc gọi network/API hoặc tác vụ dài.


## D03 – Lock Ordering, Deadlock and Lock Wait Handling (APPROVED – 2026-11-15)

1. Các transaction có thể khóa nhiều bản ghi phải lấy khóa theo thứ tự xác định, nhất quán giữa các luồng nghiệp vụ để giảm nguy cơ deadlock.
2. Khi gặp MySQL deadlock error `1213` hoặc lock wait timeout `1205`, ứng dụng phải xử lý lỗi có kiểm soát; transaction thất bại phải rollback đúng phạm vi.
3. Cho phép retry transaction với số lần giới hạn và backoff tăng dần; số lần, thời gian chờ và ngưỡng retry phải được cấu hình/kiểm chứng trong triển khai, không retry vô hạn.
4. Chỉ retry khi toàn bộ thao tác có thể chạy lại an toàn (idempotent hoặc được bảo vệ bằng idempotency key/unique constraint và không gây side effect lặp). Không retry mù các thao tác gửi thông báo, gọi API bên ngoài hoặc phát sinh side effect ngoài transaction.
5. Side effect ngoài database nên được phát hành sau khi transaction commit (ví dụ qua cơ chế after-commit/outbox nếu cần); không giữ row lock trong khi gọi network/API.
6. Ghi log có cấu trúc cho transaction lỗi/retry: operation/correlation ID, error code, số lần retry, thời gian và kết quả; không ghi dữ liệu bí mật.
7. Kiểm thử concurrency, deadlock, lock timeout, rollback và giới hạn retry đối với các nghiệp vụ có tranh chấp dữ liệu.

- Xác định thứ tự khóa nhất quán cho từng luồng có cập nhật nhiều tài nguyên; kiểm thử trên MySQL 8.0.46.
- Thiết lập retry/backoff có giới hạn theo cấu hình được kiểm chứng; không cố định số lần hoặc thời gian khi chưa có số liệu tải.
