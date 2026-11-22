# ADR-008 – PL4 Email Notification & Shared Email Queue

- **Trạng thái:** APPROVED
- **Phiên bản:** 1.0
- **Ngày phê duyệt:** 22/11/2026
- **Phân hệ:** PL4 – Issue Management
- **Phạm vi:** PL4-R06 – thông báo email, enqueue, retry và audit metadata
- **Liên quan:** ADR-001, ADR-004, ADR-005, ADR-006, ADR-007

## 1. Bối cảnh

Mã Apps Script PL4 được rà soát có các lời gọi `MailApp.sendEmail()` trực tiếp ở luồng giao ISSUE cho Assignee, thông báo Reporter khi Assignee gửi kết quả, yêu cầu UOA Approver xác nhận và thông báo kết quả quyết định. Thông báo tạo ISSUE từ PL3 hiện có hàm gửi cho Reporter nhưng đoạn gọi đang bị comment. Đây là hiện trạng mã nguồn được cung cấp, không phải xác minh deployment/live Form.

PHP mục tiêu dùng chung MySQL và Email Queue Worker cho PL1–PL5. Cần thống nhất người nhận, thời điểm enqueue, chống trùng, retry và quan hệ giữa lỗi email với giao dịch nghiệp vụ.

## 2. Quyết định được phê duyệt

### D1 – Dùng chung Email Queue

1. Tất cả email nghiệp vụ PL4 phải được tạo thành bản ghi trong bảng dùng chung `email_queue`; không gọi SMTP/Mail API trực tiếp từ Controller, Workflow Service hoặc trong transaction nghiệp vụ.
2. Mỗi email recipient là một queue item độc lập. Không gộp Reporter và UOA Approver vào một email nhiều người nhận khi họ có vai trò/thông tin cần nhận khác nhau.
3. Email Queue sử dụng `event_key` ổn định và UNIQUE để chống enqueue trùng. Key phải xác định được Issue, business event, đối tượng nhận và phiên bản/lần phát sinh hợp lệ của event.
4. Queue item lưu module/event type, Business Entity, người nhận, subject/body, trạng thái, số lần thử và metadata cần thiết. Không lưu SMTP credential, secret, raw OTP hoặc raw action token.
5. Email Worker gửi sau Commit. Lỗi gửi email không rollback hoặc đảo ngược Issue, Assignment, Work Report hay UOA Confirmation đã commit.

### D2 – Người nhận và sự kiện thông báo PL4

| Event | Người nhận | Quy tắc |
|---|---|---|
| `PL4_ISSUE_CREATED_MANUAL` | Assignee được phân công | Gửi thông báo ISSUE mới sau khi Issue/Assignment và queue item commit |
| `PL4_ISSUE_CREATED_FROM_PL3` | Reporter PL3 | Thông báo đã tiếp nhận FAIL và tạo ISSUE; không gửi MSTAR mặc định |
| `PL4_ASSIGNMENT_ASSIGNED` | Assignee được chỉ định | Gửi khi Assignment mới được tạo; link chỉ gắn đúng Issue + Assignment |
| `PL4_WORK_REPORT_SUBMITTED` | Reporter và UOA Approver theo Room | **Hai queue item riêng**. Đây là phương án A được chủ nghiệp vụ xác nhận: Reporter biết kết quả; UOA nhận yêu cầu xác nhận |
| `PL4_UOA_CONFIRMED` | Reporter và Assignee của Work Report | Thông báo UOA đã xác nhận, ISSUE chuyển CLOSED |
| `PL4_UOA_NOT_CONFIRMED` | Assignee và Reporter | Assignee nhận lý do cần tiếp tục xử lý; Reporter nhận thông tin trạng thái quay lại IN_PROGRESS |

Không gửi email cho MSTAR trong sự kiện Assignee gửi Work Report. MSTAR không phải Assignee hoặc UOA Approver và không có vai trò xác nhận/đóng ISSUE trong workflow PL4 đã chốt.

Nếu thiếu hoặc không xác định duy nhất được địa chỉ người nhận bắt buộc (đặc biệt UOA Approver theo Room), Workflow Service phải không âm thầm bỏ qua người nhận. Việc tạo sự kiện nghiệp vụ được xử lý theo quy tắc cấu hình/đầu mối hợp lệ; ghi Audit Log lỗi cấu hình và yêu cầu khắc phục. Không tự chọn người nhận thay thế.

### D3 – Atomicity và idempotency khi enqueue

1. Khi một business event làm phát sinh thông báo, thay đổi nghiệp vụ, lịch sử tương ứng, Audit Log và các Email Queue item bắt buộc phải được ghi trong cùng MySQL/InnoDB transaction.
2. Với Assignee gửi Work Report: tạo Work Report append-only, chuyển Issue sang `PENDING_UOA_CONFIRMATION`, ghi Status History/Audit và enqueue hai email riêng cho Reporter + UOA Approver trong cùng transaction.
3. Nếu một bước ghi DB/enqueue bắt buộc thất bại, rollback toàn bộ transaction; không để Work Report đã ghi nhưng thiếu một trong hai thông báo bắt buộc.
4. Retry cùng command/event không tạo Work Report, Status History, Audit event hoặc Email Queue item trùng. Sử dụng idempotency key của command và `event_key` UNIQUE của từng queue item.
5. Queue item đã tồn tại ở trạng thái RETRY/FAILED được xử lý theo chính queue item đó; không tạo event thông báo mới chỉ vì người dùng gửi lại request.

### D4 – Retry, trạng thái và giới hạn giao nhận

1. Dùng trạng thái chung: `PENDING`, `SENDING`, `RETRY`, `SENT`, `FAILED`; `CANCELLED` chỉ dùng khi nghiệp vụ cho phép hủy một thông báo chưa gửi.
2. Giữ policy Queue đã thống nhất: batch tối đa 8; tối đa 12 lần thử; exponential backoff từ 5 phút, giới hạn 6 giờ; phục hồi `SENDING` quá 15 phút về RETRY theo cơ chế khóa an toàn.
3. Mỗi lần gửi lưu trong `email_queue_attempts`, gồm số lần thử, thời điểm, outcome, error code/thông tin lỗi đã làm sạch và SMTP Message-ID nếu có.
4. `FAILED` được hiển thị để quản trị viên có thẩm quyền kiểm tra và retry thủ công. Không sửa hoặc xóa lịch sử attempt.
5. Chấp nhận semantics at-least-once: nếu SMTP đã nhận email nhưng Worker dừng trước khi cập nhật SENT, có thể phát sinh gửi lại. Không cam kết exactly-once delivery. Dùng event key, message ID và lịch sử attempt để giảm thiểu và truy vết gửi trùng.

### D5 – Audit và bảo mật

1. Ghi Audit metadata cho enqueue, từng lần gửi, gửi thành công, gửi thất bại, retry thủ công và từ chối do thiếu quyền/cấu hình.
2. Audit gắn đúng Business Entity/Issue, event code, recipient (email đã chuẩn hóa hoặc snapshot theo chính sách dữ liệu), Email Queue ID, correlation ID, thời điểm và kết quả.
3. Không ghi raw token, OTP, secret, mật khẩu SMTP hoặc credential vào Audit Log, Queue log hay lỗi hiển thị.
4. Email chỉ chứa thông tin tối thiểu cần thiết và link bảo mật đúng Issue/Assignment/action; không dùng email như quyền truy cập thay thế cho xác thực token/actor.

## 3. Luồng chuẩn – Assignee gửi Work Report

1. Xác thực Assignee và Assignment đang CLAIMED, đúng Issue; validate report result COMPLETED/NOT_COMPLETED và nội dung bắt buộc.
2. Mở transaction và khóa Issue/Assignment theo quy tắc concurrency.
3. Tạo Work Report mới (append-only); không ghi đè report cũ.
4. Chuyển Issue sang `PENDING_UOA_CONFIRMATION`; ghi Status History và Audit Log.
5. Tạo hai Email Queue item riêng: một cho Reporter, một cho UOA Approver snapshot theo Room. Cả hai có event key riêng, cùng tham chiếu Work Report/Issue và correlation ID.
6. Commit transaction.
7. Worker gửi từng email độc lập. Một email thành công không làm mất hoặc ngăn retry email còn lại.
8. Nếu transaction lỗi, rollback Work Report, trạng thái, Audit và cả hai queue item. Nếu SMTP lỗi sau Commit, Issue vẫn ở `PENDING_UOA_CONFIRMATION`; Queue retry theo policy.

## 4. Quy tắc event_key đề xuất

- Event tạo ISSUE MANUAL: định danh theo `pl4_manual_submissions.id + recipient_role`.
- Event tạo ISSUE từ PL3: định danh theo `pl3_inspection_results.id + recipient_role`.
- Event phân công: định danh theo `pl4_assignments.id + recipient_role`.
- Event Work Report: định danh theo `pl4_work_reports.id + recipient_role`; Reporter và UOA Approver phải có key khác nhau.
- Event UOA Confirmation: định danh theo `pl4_uoa_confirmations.id + recipient_role`.

Các key phải được chuẩn hóa thống nhất trong implementation; không dùng timestamp ngẫu nhiên làm thành phần duy nhất để né UNIQUE.

## 5. Tiêu chí nghiệm thu

1. Assignee gửi một Work Report hợp lệ tạo đúng một Work Report và hai queue item riêng cho Reporter + UOA Approver.
2. Không tạo email cho MSTAR trong sự kiện Work Report.
3. Retry cùng command không tạo thêm Work Report, Audit event nghiệp vụ hoặc queue item trùng.
4. Thiếu UOA Approver hợp lệ không được âm thầm bỏ qua thông báo bắt buộc.
5. Lỗi DB khi enqueue một trong hai email làm rollback toàn bộ transaction báo cáo.
6. SMTP lỗi không làm thay đổi trạng thái Issue hoặc xóa Work Report đã commit; email retry độc lập.
7. Mỗi lần thử gửi có attempt record; FAILED có thể được retry thủ công theo quyền.
8. Không có raw token/OTP/secret trong Queue, Audit hoặc error log.
9. Email gửi theo at-least-once; không tuyên bố bảo đảm exactly-once.

## 6. Phạm vi không thay đổi

- Không thay đổi state machine PL4: Assignee report COMPLETED/NOT_COMPLETED → `PENDING_UOA_CONFIRMATION`; UOA CONFIRMED → `CLOSED`; NOT_CONFIRMED + reason → `IN_PROGRESS`.
- Không thay đổi Assignment/Claim Lifecycle theo ADR-006.
- Không thay đổi idempotency/atomicity của ISSUE MANUAL theo ADR-007 và PL3 → PL4 theo ADR-005.
- Không đưa MSTAR vào vai trò Assignee, Approver hoặc người xác nhận PL4.
- ADR này là thiết kế; chưa tạo Migration hoặc PHP code.

## 7. Tài liệu liên quan

- `ERD_DESIGN.md`
- `DATA_DICTIONARY.md`
- `ERD_PHYSICAL.md`
- `WORKFLOW_SPECIFICATION.md`
- `IMPLEMENTATION_PLAN.md`
