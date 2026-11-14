# Server Room Management (SRM)

Tài liệu thiết kế module quản lý phòng máy chủ đa khách hàng trong KPI Dashboard.

## Trạng thái

- Kiến trúc nghiệp vụ: đã thống nhất ở mức định hướng.
- Data Dictionary: đã mô tả sơ bộ đủ 18/18 bảng; còn các quyết định FK/index/delete rule và các điểm nghiệp vụ cần chốt.
- ERD chi tiết và ràng buộc FK/index: đang rà soát; chưa chốt.
- Laravel migrations và code ứng dụng: chưa triển khai.
- Không chạy migration hoặc thay đổi dữ liệu production trong giai đoạn thiết kế.

## Tiến độ cập nhật gần nhất\n\n- Hoàn thành mô tả sơ bộ 10 bảng còn lại trong Data Dictionary: Inspections, Inspection Results, Incidents, Incident Actions, Maintenance Requests, Equipment Movements, Environment Logs, Approvals, Attachments và Audit Logs.\n- Tổng cộng đã có mô tả sơ bộ cho 18/18 bảng.\n- Các điểm còn mở được ghi rõ trong Data Dictionary; chưa tạo Migration.\n\n## Tài liệu

1. [Kiến trúc và các quyết định đã thống nhất](./01-architecture-and-decisions.md)
2. [Data Dictionary – bản nháp](./02-data-dictionary-draft.md)

## Phạm vi

SRM quản lý địa điểm, phòng Server, thiết bị hạ tầng, quyền truy cập, nhật ký ra vào, checklist/kiểm tra, sự cố, bảo trì, di chuyển thiết bị, môi trường vận hành, phê duyệt, tệp đính kèm và lịch sử thao tác.

## Nguyên tắc

- Tôn trọng schema hiện hữu của KPI Dashboard; tái sử dụng `service_customers`, `customer_branches`, `users`.
- Cô lập dữ liệu theo khách hàng ở cả tầng truy vấn, policy và ràng buộc dữ liệu phù hợp.
- Chỉ bắt đầu migrations sau khi hoàn tất Data Dictionary, ERD và rà soát schema thực tế.
