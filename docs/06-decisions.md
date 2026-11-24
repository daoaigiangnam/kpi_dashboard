# 06. Quyết định kiến trúc và các điểm cần chốt

## 1. Đã xác nhận
| ID | Quyết định | Trạng thái |
|---|---|---|
| D01 | Backend PHP thuần, không Laravel | Approved |
| D02 | Database MySQL | Approved |
| D03 | Nhập web + import Excel + API khi khả thi | Approved |
| D04 | Checklist tự sinh, quản lý được chỉnh sửa/bổ sung | Approved |
| D05 | Hybrid pricing: workload + labor/FTE + non-labor + margin | Design baseline |
| D06 | Tính 4 package và custom scenario | Approved |
| D07 | Phát triển độc lập trên goi_dich_vu_mare | Approved |
| D08 | Chưa viết application code trước design freeze | Approved |

## 2. Quyết định kỹ thuật đề xuất
- ADR-001 Modular Monolith: một deployable PHP application, module hóa domain; tránh microservices quá sớm.
- ADR-002 PDO + Application Services: PDO cho data access; use case giữ transaction; domain services giữ workload/pricing/checklist logic; không đặt công thức trong controller/view.
- ADR-003 Snapshot/versioning: Assessment revision, service/package version, rate set, workload rule, pricing calculation và quote version được lưu rõ.
- ADR-004 Customer boundary: một hệ thống nội bộ quản lý nhiều customer; mọi use case/query phải kiểm tra customer access.
- ADR-005 Import staging: upload → mapping → preview → validation → explicit commit.
- ADR-006 Connector read-only by default: chỉ đọc; hành động thay đổi hạ tầng cần authorization và thiết kế riêng.
- ADR-007 Rule-based checklist: AI có thể gợi ý sau này nhưng manager xác nhận; rule engine là nguồn quyết định.
- ADR-008 Deterministic pricing: cùng input snapshot + rule/rate versions phải cho kết quả giống nhau; override phải có actor/reason/audit.

## 3. Câu hỏi cần xác nhận trước database freeze
1. MySQL mục tiêu: 8.0 hay 8.4 LTS? Có bắt buộc MariaDB compatibility không?
2. Triển khai: máy chủ nội bộ/VPS hay public cloud; có cần truy cập Internet?
3. Người dùng: chỉ nhân viên nội bộ hay sau này có customer portal?
4. Tiền tệ/thuế: chỉ VND và VAT Việt Nam hay đa tiền tệ?
5. Labor costing: cost/hour, cost/month hay kết hợp; overtime/on-call/travel tính thế nào?
6. SLA: giờ hỗ trợ chuẩn, lịch nghỉ lễ, timezone, response và resolution targets?
7. Inventory: thiết bị nào bắt buộc serial-level; nhóm nào cho quantity-only?
8. Import: khách hàng dùng file tự do hay template chuẩn?
9. Connector ưu tiên: Microsoft 365/Entra, Google Workspace, endpoint management, firewall/network, virtualization hay monitoring?
10. Checklist: recurring theo tuần/tháng; có cần calendar và task dependency?
11. Approval: ai duyệt, discount authority, margin threshold, người tạo có được tự duyệt?
12. Retention: thời hạn lưu PII, file khảo sát, audit và quote?

## 4. Definition of Design Freeze
Chỉ chuyển implementation khi:
- ERD/schema đã rà soát FK, unique, index và lifecycle.
- Role-permission matrix được duyệt.
- Assessment/Quote/Checklist state machine được duyệt.
- Workload, labor allocation, overhead, margin và one-time cost có test case.
- API contract và import lifecycle được duyệt.
- Retention, backup, secret management và deployment target được xác nhận.
- Không còn câu hỏi P0 ảnh hưởng cấu trúc dữ liệu chưa quyết định.

## 5. Chưa triển khai
Chưa có PHP source, SQL migration, frontend code, seed data thực thi hoặc connector chạy thật. Đây là design baseline; thay đổi sau phê duyệt phải ghi ADR/version.
