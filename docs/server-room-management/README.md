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
- Chưa tạo permission seed, migration hoặc code authorization.

## Tài liệu

1. [Kiến trúc và các quyết định đã thống nhất](./01-architecture-and-decisions.md)
2. [Data Dictionary – bản nháp](./02-data-dictionary-draft.md)
3. [ERD và quan hệ dữ liệu – bản nháp](./03-erd-and-relationships-draft.md)
4. [Ma trận phân quyền và luồng phê duyệt – bản đề xuất](./04-permissions-and-approval-workflows-draft.md)

## Phạm vi

SRM quản lý địa điểm, phòng Server, thiết bị hạ tầng, quyền truy cập, nhật ký ra vào, checklist/kiểm tra, sự cố, bảo trì, di chuyển thiết bị, môi trường vận hành, phê duyệt, tệp đính kèm và lịch sử thao tác.

## Nguyên tắc

- Tôn trọng schema hiện hữu của KPI Dashboard; tái sử dụng service_customers, customer_branches, users và RBAC hiện có.
- Cô lập dữ liệu theo khách hàng ở cả tầng truy vấn, policy và ràng buộc dữ liệu phù hợp.
- Chỉ bắt đầu migrations sau khi hoàn tất Data Dictionary, ERD, quyền và rà soát schema thực tế.
