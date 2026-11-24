# ITSM Service Assessment & Pricing Platform

Nền tảng web nội bộ quản lý khảo sát hạ tầng khách hàng, đánh giá hiện trạng, xác định phạm vi dịch vụ IT Outsourcing, tính giá, tạo checklist triển khai và quản lý phê duyệt.

## Trạng thái
- Nhánh: goi_dich_vu_mare
- Trạng thái: Design baseline – chưa triển khai mã nguồn ứng dụng
- Backend: PHP thuần 8.2+
- Database: MySQL 8.0+
- Khảo sát: nhập web, import Excel, tích hợp API khi khả thi
- Checklist: tự sinh theo quy tắc, quản lý được bổ sung/chỉnh sửa
- Kiến trúc: Modular Monolith, REST API, server-rendered UI

## Quy trình nghiệp vụ
1. Tạo khách hàng và lần khảo sát.
2. Thu thập doanh nghiệp, chi nhánh, nhân sự, tài sản, hệ thống và nhà cung cấp.
3. Đánh giá hiện trạng, rủi ro và bằng chứng.
4. Chọn Service Catalog, xác định phạm vi, SLA/KPI, trách nhiệm và loại trừ.
5. Chuyển phạm vi thành Work Items, workload, giờ công và nhu cầu nhân sự.
6. Pricing Engine tính Basic, Standard, Advanced, Enterprise.
7. Tạo Scenario tùy chỉnh và tính lại từ đầu.
8. Sinh checklist Remediation, Onboarding, Recurring Operation, Improvement.
9. Phê duyệt, phát hành báo giá và lưu phiên bản.

## Tài liệu thiết kế
- Kiến trúc: docs/01-architecture.md
- ERD: docs/02-erd.md
- Database schema: docs/03-database-schema.md
- API contract: docs/04-api-contract.md
- Module và kế hoạch: docs/05-module-breakdown.md
- Quyết định kiến trúc: docs/06-decisions.md

## Nguyên tắc cốt lõi
- Dữ liệu khảo sát gốc không bị ghi đè khi tạo báo giá tùy chỉnh.
- Một Assessment có thể có nhiều Quote và Scenario.
- Giá truy vết được về tài sản, dịch vụ, Work Item, định mức và phiên bản cấu hình.
- Không phát hành báo giá nếu chưa qua điều kiện kiểm soát.
- Checklist lưu nguồn sinh, cho phép người có quyền điều chỉnh.
- Báo giá đã phát hành là snapshot; thay đổi catalog/rate không làm đổi báo giá cũ.
- Không lưu mật khẩu quản trị khách hàng dạng plaintext.
- Không quét mạng hoặc thay đổi cấu hình khách hàng khi chưa có ủy quyền.

Tài liệu hiện tại là thiết kế để rà soát/phê duyệt. Chưa có migration, PHP source, frontend source hoặc triển khai production.
