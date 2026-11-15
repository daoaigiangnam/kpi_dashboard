# Server Room Management (SRM)

Tài liệu thiết kế module quản lý phòng máy chủ đa khách hàng trong KPI Dashboard.

## Trạng thái

- Kiến trúc nghiệp vụ: đã thống nhất ở mức định hướng.
- Data Dictionary: đã chuẩn hóa danh mục 22 bảng dự kiến; tiếp tục đối chiếu FK/index/delete rules trước migration.
- ERD logic: đã đồng bộ Movement theo B11–B16; FK/index/delete rules toàn hệ thống vẫn đang Final Schema Review.
- Phân quyền và workflow: đã có bản đề xuất; đã chốt phương án B; Customer User assigned-only; Customer IT Admin được cấp quyền và gán phòng trong tenant.
- Batch 1 và Batch 2: các quyết định nghiệp vụ đã được ghi nhận; Batch 2 có B01–B16 APPROVED.
- Batch 3: C01–C18 đã APPROVED; hoàn tất Batch 3. Còn 10 quyết định baseline kỹ thuật nhóm D.
- Laravel migrations và code ứng dụng: chưa triển khai.
- Không chạy migration hoặc thay đổi dữ liệu production trong giai đoạn thiết kế.

## Tiến độ cập nhật gần nhất

- Hoàn thành rà soát RBAC hiện hữu: user_groups, permissions, group_permissions, users.user_group_id, account_type và service_customer_id.
- Đề xuất tái sử dụng RBAC hiện hữu cho internal users; không tạo RBAC thứ hai.
- Đề xuất quyền SRM tách biệt cho customer accounts, giữ nguyên nguyên tắc customer không kế thừa quyền quản trị nội bộ.
- Đã soạn ma trận vai trò, permission codes, tenant scope, SoD và workflow phê duyệt.
- Đã chốt 2 bảng access-control: customer_user_srm_permissions và customer_user_server_rooms.
- Tổng thiết kế hiện tại: 23 bảng mới dự kiến (18 core + 2 pivots + 2 access-control + 1 approval-delegation).
- Đã lập tài liệu quy tắc tenant integrity, Rack/U, equipment movement và State Machine cho 7 nhóm hồ sơ.
- Đã đồng bộ Movement B11–B16 giữa Decision Register, Data Dictionary, ERD, Column Schema và State Machine.
- Đã cập nhật kết luận Schema Review Batch 2 và Migration Blueprint theo trạng thái phê duyệt mới nhất.
- C01–C03 Environment Logs đã APPROVED và đồng bộ vào Decision Register, Data Dictionary, Column Schema Batch 3, Schema Review Batch 3 và Migration Blueprint.
- C04–C07 Approval đã APPROVED: phê duyệt tuần tự theo cấp, ALL approvers mỗi cấp, snapshot approver khi cấp kích hoạt; hỗ trợ delegation/reassignment có kiểm soát và Audit.
- Chưa tạo permission seed, migration hoặc code authorization.

## Tài liệu

1. [Kiến trúc và các quyết định đã thống nhất](./01-architecture-and-decisions.md)
2. [Data Dictionary – bản nháp](./02-data-dictionary-draft.md)
3. [ERD và quan hệ dữ liệu – bản nháp](./03-erd-and-relationships-draft.md)
4. [Ma trận phân quyền và luồng phê duyệt – bản đề xuất](./04-permissions-and-approval-workflows-draft.md)
5. [Toàn vẹn dữ liệu và State Machine – bản nháp](./05-data-integrity-and-state-machines-draft.md)

## Phạm vi

SRM quản lý địa điểm, phòng Server, thiết bị hạ tầng, quyền truy cập, nhật ký ra vào, checklist/kiểm tra, sự cố, bảo trì, di chuyển thiết bị, môi trường vận hành, phê duyệt, tệp đính kèm và lịch sử thao tác.

## Nguyên tắc

- Tôn trọng schema hiện hữu của KPI Dashboard; tái sử dụng service_customers, customer_branches, users và RBAC hiện có.
- Cô lập dữ liệu theo khách hàng ở cả tầng truy vấn, policy và ràng buộc dữ liệu phù hợp.
- Chỉ bắt đầu migrations sau khi hoàn tất Data Dictionary, ERD, quyền và rà soát schema thực tế.


## 06. Migration Blueprint

- [06 – Migration Blueprint (bản nháp)](./06-migration-blueprint-draft.md): danh mục 22 bảng, thứ tự migration, tenant integrity, composite FK và các gate trước khi viết Laravel migrations.


## 07. Column-level Schema – Batch 1

- [07 – Column-level Schema, Batch 1](./07-column-schema-batch-1-draft.md): chuẩn hóa 7 bảng nền tảng đầu tiên ở mức cột, kiểu dữ liệu, nullable, default, unique/index và quy tắc tenant.


## 08. Column-level Schema – Batch 2

- [08 – Column-level Schema, Batch 2](./08-column-schema-batch-2-draft.md): chuẩn hóa 7 bảng Access Logs, Inspections, Inspection Results, Incidents, Incident Actions, Maintenance Requests và Equipment Movements.


## 09. Column-level Schema – Batch 3

- [09 – Column-level Schema, Batch 3](./09-column-schema-batch-3-draft.md): chuẩn hóa 8 bảng còn lại, hoàn tất thiết kế cột sơ bộ cho register 22 bảng.


## 10. Schema Review – Batch 1

- [10 – Schema Review Batch 1](./10-schema-review-batch-1-draft.md): rà soát 7 bảng nền tảng, tenant integrity, Rack/U-position, soft delete và checklist versioning.


## 11. Schema Review – Batch 2

- [11 – Schema Review Batch 2](./11-schema-review-batch-2-draft.md): rà soát 7 bảng Access Logs, Inspections, Incidents, Maintenance và Equipment Movements.


## 12. Schema Review – Batch 3

- [12 – Schema Review Batch 3](./12-schema-review-batch-3-draft.md): rà soát 8 bảng cuối, gồm Environment Logs, Approval, Attachment, Audit, hai pivot và hai bảng phân quyền Customer User.


## 13. Final Schema Decision Register

- [13 – Final Schema Decision Register](./13-final-schema-decision-register-draft.md): tổng hợp các quyết định còn mở từ ba đợt Schema Review, kèm đề xuất và mức ưu tiên P0/P1.


## Cập nhật Approval Delegation – C06

- Chủ dự án chọn ủy quyền theo thời hạn và phạm vi, quản lý bằng bảng `server_room_approval_delegations`.
- Đã chốt chỉ áp dụng ủy quyền khi cấp phê duyệt được kích hoạt; principal approver được snapshot theo C05 trước, sau đó mới resolve acting approver.
- Bổ sung `acting_approver_id`, `acted_by`, `delegation_id` cho `server_room_approvals`.
- Tổng thiết kế điều chỉnh từ 22 lên 23 bảng; chưa tạo Laravel migrations hoặc thay đổi database.
- C07 đã APPROVED: requester không tự phê duyệt; requester bị loại khỏi approver snapshot, cấp rỗng bị chặn, delegation không được bypass SoD; không phát sinh bảng/cột mới.

- C08 đã APPROVED: Private Storage, MIME validation theo nội dung, allowlist/giới hạn cấu hình, quarantine + malware scan; chỉ file `clean` mới được preview/download. Bổ sung `scan_status` và `scanned_at` vào attachment metadata; chưa tạo migrations.

- C09 đã APPROVED: không cascade/purge khi parent Archived/Soft Deleted; retention theo policy; Legal Hold đặt ở parent và bảo vệ toàn bộ attachment của parent; purge cần hết hạn + parent hết hold + kiểm tra reference + Audit. Chi tiết schema Legal Hold ở parent tiếp tục là gate của Final Schema Review.

- C09-A đã APPROVED: Legal Hold ở cấp parent record; toàn bộ attachment của parent tự động được bảo vệ; không đặt hold riêng từng file. Schema cụ thể tại parent vẫn chờ Final Schema Review.

- C09-B đã APPROVED: mỗi lần đặt/gỡ Legal Hold lưu thành event riêng với parent identity, action, actor, UTC timestamp, reason; release liên kết placement tương ứng; không hard delete. Cấu trúc lưu trữ chờ Final Schema Review.


- C10 đã APPROVED: Audit Log append-only; DB runtime account không có UPDATE/DELETE; hỗ trợ archive riêng với quyền sửa/xóa hạn chế khi compliance yêu cầu; audit event có actor, UTC timestamp, action, target, result và trace cần thiết. Không tuyên bố bất biến tuyệt đối trước DBA/quản trị hạ tầng đặc quyền. Chưa tạo migration.

- C11 đã APPROVED: giữ `server_room_incident_assets.impact_note` và `server_room_maintenance_assets.work_note`; không đổi thành tên chung `relationship_note`. Không phát sinh bảng/cột mới.


- C12 đã APPROVED: cả hai pivot Incident–Asset và Maintenance–Asset lưu snapshot `asset_tag` và `asset_name` tại thời điểm liên kết; snapshot không thay đổi khi Asset gốc đổi tên/mã. Kiểu dữ liệu chi tiết chờ Final Schema Review.


- C13 đã APPROVED: Incident được liên kết Asset đa phòng trong cùng tenant; Maintenance mặc định cùng Room, đa phòng phải khai báo scope rõ; Service kiểm tra tenant/room scope trước khi liên kết. Không phát sinh bảng mới.


- C14 đã APPROVED: permission revoke cập nhật `revoked_at`/`revoked_by`, không xóa; cấp lại tạo row mới; chỉ một grant active cho mỗi `(customer_id, user_id, permission_code)`. Chiến lược unique active chờ xác minh MySQL thực tế.


- C15 đã APPROVED: mỗi `(customer_id, user_id, server_room_id)` chỉ có một assignment active; revoke giữ lịch sử, cấp lại tạo row mới; cơ chế unique active chờ xác minh MySQL thực tế.


- C16 đã APPROVED: Customer User bắt buộc `account_type=customer` và `service_customer_id=customer_id`; composite FK chỉ dùng nếu schema hiện hữu hỗ trợ, nếu không Service/Policy tenant check và negative tests là bắt buộc.


- C17 đã APPROVED: Customer IT Admin chỉ quản trị Customer User/Server Room cùng tenant, chỉ cấp quyền SRM trong allowlist và mọi thao tác đều được Audit.


- C18 đã APPROVED: disabled/inactive user bị từ chối truy cập SRM; chuyển tenant thì revoke quyền/assignment tenant cũ, không tự chuyển quyền; giữ lịch sử, transaction nhất quán, Audit và negative tests.


- D01 đã APPROVED: MySQL 8.0 là baseline thiết kế/migration; runtime minor/patch version và cấu hình thực tế vẫn phải được xác minh trước khi triển khai.


- D01 runtime đã được xác minh qua Laravel: MySQL Community Server 8.0.46, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`; `sql_mode` đã được ghi nhận trong Decision Register và Migration Blueprint.
