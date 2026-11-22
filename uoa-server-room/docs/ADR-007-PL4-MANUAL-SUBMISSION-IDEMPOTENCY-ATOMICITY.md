# ADR-007 – PL4 Manual Issue Submission Idempotency & Atomicity

- **Trạng thái:** APPROVED
- **Phiên bản:** 1.0
- **Ngày phê duyệt:** 22/11/2026
- **Phân hệ:** PL4 – Issue Management
- **Phạm vi:** PL4-R04 / PL4-R05 – tạo ISSUE thủ công từ Form Response
- **Liên quan:** ADR-001, ADR-004, ADR-006

## 1. Bối cảnh

Mã Apps Script PL4 hiện có cột `Form_Response_ID` trong PL4 Form Responses, nhưng handler tạo ISSUE thủ công chưa kiểm tra Response ID đã được xử lý trước khi tạo ISSUE. Luồng hiện tại tạo ISSUE, append Form Response và ghi Audit Log thành các thao tác riêng; do đó có thể để lại dữ liệu dở dang khi một bước thất bại. Trigger chạy lại cũng có nguy cơ tạo ISSUE trùng.

Hệ thống PHP mục tiêu dùng MySQL/InnoDB. Cần bảo đảm một lần gửi Form chỉ tạo một hồ sơ ISSUE, và các bản ghi nghiệp vụ bắt buộc được commit hoặc rollback cùng nhau.

## 2. Quyết định được phê duyệt

### D1 – Idempotency theo Form Response

1. Mỗi lần gửi PL4 Form được định danh bằng cặp `source_form_id` + `form_response_id`.
2. Cặp khóa này là duy nhất trong bảng `pl4_manual_submissions` bằng UNIQUE constraint.
3. Khi nhận lại cùng Form Response (retry, trigger chạy lại hoặc request lặp), hệ thống không tạo ISSUE mới; trả về/tham chiếu ISSUE đã được tạo cho submission đó.
4. Một Form Response không được liên kết với nhiều ISSUE. Việc chống trùng phải được DB bảo vệ, không chỉ dựa vào kiểm tra trước trong PHP.
5. Form ID và Response ID là metadata nguồn; không dùng Response ID làm mã ISSUE hoặc khóa chính nghiệp vụ.

### D2 – Một MySQL Transaction cho toàn bộ ghi nhận

Trong cùng một transaction InnoDB, PHP phải thực hiện:
1. Tạo `business_entities` và `pl4_issues` theo ADR-001, với source type MANUAL và snapshot Priority theo ADR-004.
2. Tạo `pl4_manual_submissions`, lưu Form ID, Response ID, Issue FK, reporter snapshot và thời điểm tiếp nhận.
3. Ghi `system_audit_logs` event `PL4_ISSUE_CREATED` / `CREATED_MANUAL` gắn đúng Business Entity.
4. Tạo Email Queue record thông báo Assignee, với `event_key` ổn định và duy nhất cho submission/sự kiện tạo Issue.

Nếu bất kỳ bước ghi DB nào thất bại, rollback toàn bộ transaction; không để sót Issue, submission, audit hoặc email queue record. Việc enqueue email ở đây chỉ là ghi hàng đợi vào DB, không thực hiện gửi SMTP trong transaction.

### D3 – Gửi email sau Commit qua Email Queue Worker

1. Không gọi SMTP/Mail API trực tiếp trong transaction tạo ISSUE.
2. Chỉ sau khi transaction commit thành công, Email Queue Worker mới claim và gửi email.
3. Lỗi SMTP không rollback hoặc xóa ISSUE đã commit; queue retry theo policy dùng chung.
4. Email event có `event_key` UNIQUE để chống enqueue lặp khi retry xử lý submission.
5. Nếu submission đã tồn tại, không tạo email thông báo ISSUE MỚI lần nữa; việc retry gửi email được thực hiện bằng chính Email Queue record hiện hữu.

## 3. Luồng xử lý chuẩn

1. Nhận Form ID + Form Response ID và validate payload.
2. Mở transaction.
3. Thử tạo submission theo unique key (hoặc khóa/đọc submission hiện hữu theo chiến lược xử lý duplicate an toàn).
4. Nếu đã tồn tại: lấy ISSUE liên kết, không tạo Issue/Audit/Email event mới; kết thúc theo nhánh duplicate.
5. Nếu mới: tạo Business Entity + Issue, liên kết submission, ghi Audit và enqueue email trong cùng transaction.
6. Commit; sau commit Worker gửi email.
7. Nếu lỗi DB: rollback; request có thể retry với cùng khóa nguồn.

## 4. Mô hình dữ liệu

Bảng `pl4_manual_submissions` gồm tối thiểu:
- `id` PK.
- `issue_id` FK `pl4_issues(id)`, NOT NULL.
- `source_form_id` VARCHAR(190), NOT NULL.
- `form_response_id` VARCHAR(190), NOT NULL.
- `reporter_name_snapshot` VARCHAR(160) NULL.
- `reporter_email_snapshot` VARCHAR(190) NULL.
- `submitted_at` DATETIME(3) NOT NULL (UTC).
- `created_at` DATETIME(3) NOT NULL.
- UNIQUE `(source_form_id, form_response_id)`.
- INDEX `(issue_id)`; FK Issue bảo đảm liên kết submission.
- Mỗi submission liên kết đúng một ISSUE; không cho phép một Form Response tạo nhiều ISSUE.

Không cần lưu toàn bộ câu trả lời Form thành bản sao JSON nếu các trường nghiệp vụ đã được chuẩn hóa vào ISSUE và submission snapshot. Nếu cần lưu raw payload phục vụ bằng chứng, phải có quyết định retention/privacy riêng.

## 5. Ranh giới và lưu ý kỹ thuật

- ADR này áp dụng cho PL4 tạo ISSUE thủ công. PL3 → PL4 tiếp tục theo Outbox và UNIQUE `source_inspection_result_id` đã chốt trong ADR-005.
- `pl4_manual_submissions` là bảng nguồn/idempotency nghiệp vụ của PL4, không thay thế `idempotency_keys` dùng chung nếu có nhu cầu idempotency cho API khác.
- Không áp dụng Transaction giả lập cho Google Sheets/Apps Script. Apps Script hiện tại không có MySQL transaction; bảo đảm atomicity ở đây thuộc hệ thống PHP/MySQL mục tiêu.
- Audit ghi cùng transaction với thay đổi nghiệp vụ. Không ghi raw token, OTP, secret hoặc credential vào Audit.
- Email queue record được ghi cùng transaction; gửi SMTP chỉ diễn ra sau Commit.
- Migration/PHP implementation chưa nằm trong phạm vi ADR; tài liệu này là thiết kế đã phê duyệt.

## 6. Tiêu chí nghiệm thu

1. Hai request đồng thời cùng Form ID + Response ID chỉ tạo một ISSUE.
2. Trigger/request retry cùng Response ID trả về ISSUE cũ, không tạo ISSUE thứ hai.
3. UNIQUE constraint chặn duplicate ngay cả khi PHP pre-check bị race.
4. Lỗi khi tạo Issue/submission/audit/email queue làm rollback toàn bộ dữ liệu trong transaction.
5. ISSUE đã commit vẫn tồn tại khi SMTP lỗi; queue chuyển RETRY/FAILED theo policy.
6. Retry duplicate không tạo Audit `CREATED_MANUAL` hoặc Email Queue event tạo Issue thứ hai.
7. Submission luôn truy ngược được tới đúng ISSUE.

## 7. Tài liệu liên quan

- `ERD_DESIGN.md`
- `DATA_DICTIONARY.md`
- `ERD_PHYSICAL.md`
- `WORKFLOW_SPECIFICATION.md`
- `IMPLEMENTATION_PLAN.md`
