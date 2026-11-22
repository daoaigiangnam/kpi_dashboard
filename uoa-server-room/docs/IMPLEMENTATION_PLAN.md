# Kế hoạch triển khai

## Giai đoạn 1 – Nền tảng dữ liệu và bảo mật
- [x] Chốt phạm vi PL1–PL5 theo Policy và biểu mẫu.
- [x] Chốt kiến trúc PHP 8.2 + MySQL 8 + SMTP Queue Worker.
- [ ] Schema MySQL và migration.
- [ ] Đăng nhập, session security, CSRF, RBAC.
- [ ] Admin layout, menu, dashboard tổng quan.
- [ ] Danh mục Server Rooms, Room Approvers, Users, Roles, Settings.

## Giai đoạn 2 – PL1 Access Authorization
- [ ] Danh sách quyền truy cập được cấp.
- [ ] Tạo yêu cầu NEW / CHANGE / REVOKE.
- [ ] Kiểm tra trùng người + email + phòng theo quy tắc Apps Script.
- [ ] Phê duyệt / từ chối bằng token an toàn hoặc tài khoản có quyền.
- [ ] Đồng bộ Access Register từ trạng thái đã duyệt.
- [ ] Lịch sử và CRUD có kiểm soát.

## Giai đoạn 3 – PL2 Access Log
- [ ] ENTRY request và snapshot approver theo phòng tại thời điểm gửi.
- [ ] UOA approve/reject.
- [ ] Gửi OTP qua queue; OTP 10 phút tính từ lúc gửi thành công.
- [ ] CHECK-IN ghi người thực tế vào phòng.
- [ ] CHECK-OUT chỉ mở đúng Visit ID đang OPEN; ghi kết quả, ảnh và thời gian.
- [ ] Chặn xử lý lặp và ghi Audit Log.

## Giai đoạn 4 – PL3 Inspection
- [ ] Danh mục 12 checklist items có cấu hình.
- [ ] Chỉ triển khai Checklist DAILY theo ADR-003; không đưa Weekly/Monthly vào Form/schema hiện tại.
- [ ] Phiếu kiểm tra, kết quả PASS / FAIL / N/A, ghi chú và nhiệt độ/độ ẩm.
- [ ] UOA xác nhận đã tiếp nhận hồ sơ; phân biệt với xác nhận khắc phục.
- [ ] FAIL tạo PL4 qua Outbox/PHP Worker; khóa nguồn `pl3_inspection_results.id`, chống trùng theo ADR-005; snapshot Checklist bất biến trên ISSUE.

## Giai đoạn 5 – PL4 Incident / Violation
- [ ] Tạo từ PL3 FAIL hoặc tạo độc lập; ISSUE PL3 ban đầu OPEN/chưa có Assignee.
- [ ] PL4-R04/R05: tạo ISSUE MANUAL idempotent theo Form ID + Form Response ID; UNIQUE tại `pl4_manual_submissions`; retry trả ISSUE cũ.
- [ ] Tạo Business Entity + Issue + Manual Submission + Audit Log + Email Queue trong cùng MySQL Transaction; rollback toàn bộ khi lỗi DB.
- [ ] Email thông báo ISSUE MANUAL gửi sau Commit qua Email Queue Worker; không SMTP trực tiếp trong Transaction (ADR-007).
- [ ] Phân công/claim/tái phân công theo ADR-006; chỉ Assignee được chỉ định claim, một Assignment hiệu lực/ISSUE, chống claim đồng thời.
- [ ] Mức độ ảnh hưởng, xử lý ban đầu, nguyên nhân, owner, due date.
- [ ] Theo dõi khắc phục, xác minh và đóng phiếu.
- [ ] Gửi yêu cầu xác nhận và email kết quả qua queue.
- [ ] Theo ADR-008: email PL4 dùng Queue chung; Work Report enqueue hai email riêng cho Reporter và UOA Approver trong cùng transaction; thông báo UOA CONFIRMED/NOT_CONFIRMED cho Reporter + Assignee; không gửi MSTAR trong sự kiện Work Report.
- [ ] Bảo đảm event_key riêng theo business event + recipient role; retry cùng command không tạo thêm Work Report/Queue item; ghi attempt/audit và hỗ trợ retry FAILED theo quyền.

## Giai đoạn 6 – PL5 Work / Maintenance
- [ ] Đăng ký khung thời gian, đơn vị thực hiện, thiết bị/hệ thống.
- [ ] Mô tả công việc, tác động dịch vụ, rollback plan.
- [ ] UOA approval, thực hiện, post-work verification, completion.
- [ ] Lịch sử và trạng thái.

## Giai đoạn 7 – Vận hành
- [ ] Email Queue Dashboard: PENDING / PROCESSING / RETRY / SENT / FAILED / CANCELLED.
- [ ] Retry thủ công, xem lỗi, lịch sử lần gửi.
- [ ] Worker CLI chạy bằng Supervisor hoặc cron; khóa job và phục hồi job treo.
- [ ] Audit Log, báo cáo, export.
- [ ] Kiểm thử workflow, quyền, concurrency, mail failure và backup/restore.

## Quy tắc Email Queue
- Enqueue trong cùng transaction với nghiệp vụ tạo event (transactional outbox).
- Unique `event_key` để chống enqueue trùng.
- Worker claim job bằng transaction + `SELECT ... FOR UPDATE SKIP LOCKED` (MySQL 8).
- Tăng attempts và ghi last_error; exponential backoff có giới hạn và jitter.
- Quá max attempts chuyển FAILED, không xóa nội dung/email log.
- Job SENDING quá thời gian lock được phục hồi về RETRY.
- Email đã gửi không được worker gửi lại; trạng thái SENT là bất biến.
- Chấp nhận semantics at-least-once ở tình huống SMTP đã nhận mail nhưng process chết trước khi cập nhật SENT; giảm thiểu bằng event id, message id và log gửi.

## Lưu ý nghiệp vụ cần giữ đúng
- PL1: mỗi người có thể có quyền ở các phòng khác nhau; CHANGE/REVOKE chỉ áp dụng đúng phòng đang ACTIVE.
- PL2: approver được snapshot tại thời điểm ENTRY; thay đổi cấu hình phòng sau đó không đổi người duyệt của request cũ.
- PL2: OTP expiry chỉ kích hoạt sau khi SMTP báo gửi thành công; OTP mới hủy các OTP đang chờ cũ.
- PL3: UOA xác nhận tiếp nhận hồ sơ không đồng nghĩa xác nhận đã khắc phục.
- PL3 → PL4: một checklist FAIL chỉ tạo tối đa một PL4 issue.
- PL4: đóng phiếu phải có kết quả UOA xác nhận; Assignee report không tự đóng. Assignment/claim/tái phân công theo ADR-006 và giữ lịch sử.
- PL5: phải lưu phương án dự phòng/hoàn tác và kết quả kiểm tra sau công việc.
