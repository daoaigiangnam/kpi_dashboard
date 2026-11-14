# Server Room Management (SRM)

Tài liệu thiết kế module quản lý phòng máy chủ đa khách hàng trong KPI Dashboard.

## Trạng thái

- Kiến trúc nghiệp vụ: đã thống nhất ở mức định hướng.
- Data Dictionary: mô tả sơ bộ 18 bảng cốt lõi; đang rà soát quan hệ và bảng liên kết.
- ERD logic: đã có bản nháp; chưa chốt FK/index/delete rules.
- Laravel migrations và code ứng dụng: chưa triển khai.
- Không chạy migration hoặc thay đổi dữ liệu production trong giai đoạn thiết kế.

## Tiến độ cập nhật gần nhất

- Đã mô tả sơ bộ 18 bảng cốt lõi.
- Đã rà soát ERD logic: đề xuất thêm 2 bảng pivot cho quan hệ nhiều-nhiều giữa sự cố/bảo trì và tài sản.
- Đã đề xuất mô hình Rack bằng self-reference trên server_room_assets; movement có vị trí nguồn/đích.
- Tổng dự kiến: 20 bảng (18 core + 2 pivot), chưa phải schema được duyệt.
- Migration và code ứng dụng chưa triển khai.

## Tài liệu

1. [Kiến trúc và các quyết định đã thống nhất](./01-architecture-and-decisions.md)
2. [Data Dictionary – bản nháp](./02-data-dictionary-draft.md)
3. [ERD và quan hệ dữ liệu – bản nháp](./03-erd-and-relationships-draft.md)

## Phạm vi

SRM quản lý địa điểm, phòng Server, thiết bị hạ tầng, quyền truy cập, nhật ký ra vào, checklist/kiểm tra, sự cố, bảo trì, di chuyển thiết bị, môi trường vận hành, phê duyệt, tệp đính kèm và lịch sử thao tác.

## Nguyên tắc

- Tôn trọng schema hiện hữu của KPI Dashboard; tái sử dụng service_customers, customer_branches, users.
- Cô lập dữ liệu theo khách hàng ở cả tầng truy vấn, policy và ràng buộc dữ liệu phù hợp.
- Chỉ bắt đầu migrations sau khi hoàn tất Data Dictionary, ERD và rà soát schema thực tế.
