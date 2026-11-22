# UOA Server Room Management

Ứng dụng quản lý Phòng Server theo Server Room Management Policy và Appendix 1–5.

## Công nghệ
- PHP 8.2
- MySQL 8.0 / InnoDB / utf8mb4
- PDO (prepared statements)
- Composer
- PHPMailer (SMTP)
- Worker Queue chạy bằng CLI + Cron/Supervisor

## Phạm vi
- PL1: Access Authorization
- PL2: Server Room Access Log (ENTRY / APPROVAL / OTP CHECK-IN / CHECK-OUT)
- PL3: Daily / Weekly / Monthly Inspection
- PL4: Incident / Violation Management
- PL5: Work / Maintenance Request
- Admin: Dashboard từng quy trình, CRUD, workflow actions, users/roles, rooms/approvers, settings
- Operations: Email Queue, retry, audit log, system health

## Nguyên tắc chuyển đổi từ Apps Script
1. MySQL là nguồn dữ liệu chính; không phụ thuộc Google Sheets/Forms để vận hành.
2. Mọi email nghiệp vụ phải được ghi vào `email_queue` trước khi gửi.
3. Email lỗi không rollback trạng thái nghiệp vụ đã commit.
4. Worker retry theo lịch backoff, giới hạn số lần thử; hỗ trợ retry thủ công từ Admin.
5. Mỗi email có `event_key` chống enqueue trùng.
6. Token chỉ lưu dạng hash; OTP chỉ lưu hash và chỉ bắt đầu thời hạn sau khi email OTP gửi thành công.
7. Các quyết định phê duyệt, check-in/out, đóng sự cố và thay đổi quyền phải qua workflow service, không cho CRUD trực tiếp bỏ qua quy trình.
8. Audit log ghi nhận actor, hành động, đối tượng, thời gian và dữ liệu thay đổi.
9. Không xóa cứng hồ sơ nghiệp vụ đã phát sinh; dùng trạng thái hủy/thu hồi hoặc soft delete phù hợp.

## Cấu trúc
- `app/`: Core, middleware, services, repositories, controllers
- `config/`: cấu hình ứng dụng và database
- `database/`: schema và migrations
- `public/`: web entry point, assets
- `bin/`: CLI worker và tác vụ bảo trì
- `storage/logs/`: log ứng dụng (không commit log thật)

## Triển khai
Xem `docs/IMPLEMENTATION_PLAN.md` và `database/schema.sql`. Không đưa thông tin SMTP, mật khẩu DB, APP_KEY hoặc token thật lên Git.
