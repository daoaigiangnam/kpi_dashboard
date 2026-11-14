# Server Room Management (SRM)

Tài liệu thiết kế module quản lý phòng máy chủ đa khách hàng trong KPI Dashboard.

## Trạng thái

- Kiến trúc nghiệp vụ: đã thống nhất ở mức định hướng.
- Data Dictionary: mô tả sơ bộ 18 bảng cốt lõi; đang rà soát quan hệ và bảng liên kết.
- ERD logic: bản nháp đã có; chưa chốt FK/index/delete rules.
- Phân quyền và workflow: đã có bản đề xuất; đã chốt phương án B; Customer User assigned-only; Customer IT Admin được cấp quyền và gán phòng trong tenant.
- Laravel migrations và code ứng dụng: chưa triển khai.
- Không chạy migration hoặc thay đổi dữ liệu production trong giai đoạn thiết kế.

## Tiến độ cập nhật gần nhất

- Hoàn thành rà soát RBAC hiện hữu: user_groups, permissions, group_permissions, users.user_group_id, account_type và service_customer_id.
- Đề xuất tái sử dụng RBAC hiện hữu cho internal users; không tạo RBAC thứ hai.
- Đề xuất quyền SRM tách biệt cho customer accounts, giữ nguyên nguyên tắc customer không kế thừa quyền quản trị nội bộ.
- Đã soạn ma trận vai trò, permission codes, tenant scope, SoD và workflow phê duyệt.
- Đã chốt 2 bảng access-control: customer_user_srm_permissions và customer_user_server_rooms.
- Tổng thiết kế hiện tại: 22 bảng mới dự kiến (18 core + 2 pivots + 2 access-control).
- Đã lập tài liệu quy tắc tenant integrity, Rack/U, equipment movement và State Machine cho 7 nhóm hồ sơ.
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
