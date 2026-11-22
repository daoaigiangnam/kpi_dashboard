# ADR-009 – PL4 Audit Trail & Notification History

- **Trạng thái:** APPROVED
- **Phiên bản:** 1.0
- **Ngày phê duyệt:** 22/11/2026
- **Phân hệ:** PL4 – Issue Management
- **Phạm vi:** PL4-R07 – Audit Trail, lịch sử nghiệp vụ và Notification History
- **Liên quan:** ADR-001, ADR-004, ADR-005, ADR-006, ADR-007, ADR-008

## 1. Bối cảnh

PL4 có nhiều loại lịch sử chuyên biệt: Assignment/Claim, Work Report, UOA Confirmation và Issue Status History. Hệ thống cũng có Audit Log tập trung system_audit_logs và Email Queue dùng chung gồm email_queue cùng email_queue_attempts.

Cần cung cấp một Timeline thống nhất để UOA truy vết toàn bộ vòng đời ISSUE mà không tạo các bảng lịch sử trùng lặp, không làm thay đổi state machine và không biến Audit Log thành nguồn duy nhất xác định trạng thái hiện tại.

## 2. Quyết định được phê duyệt

### D1 – Dùng Audit Log chung cho mọi sự kiện quan trọng của ISSUE

1. Mọi thao tác quan trọng trên ISSUE phải ghi vào system_audit_logs, liên kết trực tiếp với business_entities.id của ISSUE qua business_entity_id theo ADR-001.
2. Audit event phải thể hiện tối thiểu: module, event code/name, workflow step, actor type và actor snapshot (nếu có), action channel, kết quả, thời điểm và correlation ID.
3. Các event code PL4 tối thiểu:

| Event Code | Ý nghĩa |
|---|---|
| PL4_ISSUE_CREATED | Tạo ISSUE |
| PL4_ASSIGNMENT_CREATED | Tạo lần phân công |
| PL4_ASSIGNMENT_CLAIMED | Assignee nhận việc |
| PL4_ASSIGNMENT_DECLINED | Assignee từ chối |
| PL4_ASSIGNMENT_EXPIRED | Assignment hết hạn |
| PL4_ASSIGNMENT_RELEASED | Assignment được giải phóng |
| PL4_WORK_REPORT_SUBMITTED | Assignee gửi Work Report |
| PL4_UOA_CONFIRMED | UOA xác nhận |
| PL4_UOA_NOT_CONFIRMED | UOA không xác nhận |
| PL4_ISSUE_STATUS_CHANGED | ISSUE chuyển trạng thái |

4. Thao tác bị từ chối hoặc thất bại do quyền, trạng thái, token hoặc cấu hình phải được Audit theo chuẩn SUCCESS/FAILED/DENIED của hệ thống. Không ghi dữ liệu bí mật hoặc raw token.
5. Audit của thao tác trên Assignment, Work Report hoặc Confirmation vẫn liên kết về Business Entity của ISSUE gốc. ID bản ghi con được lưu trong metadata an toàn khi cần truy vết.

### D2 – Lịch sử nghiệp vụ chuyên biệt là append-only

1. Mỗi lần phân công tạo một dòng mới trong pl4_assignments; không ghi đè hoặc xóa Assignment cũ.
2. Mỗi lần Assignee gửi kết quả tạo một dòng mới trong pl4_work_reports.
3. Mỗi lần UOA xác nhận/không xác nhận tạo một dòng mới trong pl4_uoa_confirmations.
4. Mỗi lần trạng thái ISSUE thay đổi tạo một dòng mới trong pl4_issue_status_history.
5. Các bảng chuyên biệt lưu dữ liệu nghiệp vụ chi tiết; system_audit_logs lưu dấu vết kiểm soát tập trung. Hai lớp này bổ sung cho nhau, không thay thế nhau.
6. Không sửa/xóa lịch sử đã phát sinh. Trạng thái hiện tại lấy từ bảng nghiệp vụ pl4_issues, không suy ra chỉ từ Audit Log.

### D3 – Notification History lấy từ Email Queue, không tạo bảng trùng

1. Không tạo bảng riêng pl4_notification_history.
2. Thông tin email được tổng hợp từ:
   - email_queue: event type, recipient, subject, trạng thái hiện tại, thời điểm tạo, thời điểm gửi thành công, Email Queue ID và message ID nếu có.
   - email_queue_attempts: từng lần thử, thời điểm, outcome, error code/thông tin lỗi đã làm sạch, SMTP Message-ID và thời lượng nếu có.
   - system_audit_logs: dấu vết enqueue, retry thủ công và các thao tác quản trị hoặc từ chối liên quan theo ADR-008.
3. Mỗi recipient là một queue item riêng; retry tiếp tục trên queue item hiện hữu. Không tạo queue item mới chỉ để ghi nhận một lần retry.
4. Lỗi gửi email không thay đổi hoặc rollback trạng thái ISSUE đã commit. Semantics giao nhận vẫn là at-least-once theo ADR-008.
5. Không ghi raw token, OTP, secret, credential hoặc nội dung nhạy cảm không cần thiết vào Queue, Attempt, Audit hay Timeline.

### D4 – Timeline hợp nhất là màn hình truy vấn, không phải bảng lịch sử mới

1. Chi tiết ISSUE phải có Timeline hợp nhất, sắp xếp theo thời điểm, lấy dữ liệu từ:
   - pl4_issues cho thông tin hồ sơ và trạng thái hiện tại;
   - pl4_issue_status_history cho chuyển trạng thái;
   - pl4_assignments cho phân công/claim/decline/expiry/release;
   - pl4_work_reports cho từng lần báo cáo và minh chứng;
   - pl4_uoa_confirmations cho quyết định UOA;
   - system_audit_logs cho dấu vết kiểm soát;
   - email_queue và email_queue_attempts cho Notification History.
2. Timeline phải thể hiện được chuỗi: tạo ISSUE → phân công → nhận việc → báo cáo xử lý → UOA xác nhận/không xác nhận → các lần thông báo → đóng ISSUE.
3. Một sự kiện có thể có bản ghi nghiệp vụ chi tiết và Audit event tương ứng. UI phải gom/nhóm hợp lý để người xem không hiểu nhầm đó là hai hành động khác nhau; vẫn cho phép mở chi tiết Audit khi có quyền.
4. Hỗ trợ lọc theo nhóm sự kiện, actor, kết quả và khoảng thời gian. Quyền xem chi tiết theo ma trận phân quyền đã chốt; UOA xem Timeline nghiệp vụ trong phạm vi được phép.
5. Không cấp quyền xem raw Audit/Security Log cho MSTAR hoặc quyền xem hồ sơ nghiệp vụ cho Super Admin trái với D19.

### D5 – Chuẩn hóa liên kết Audit theo ADR-001

1. system_audit_logs.business_entity_id là FK trực tiếp tới business_entities.id; trường này nullable chỉ đối với sự kiện hệ thống không gắn hồ sơ.
2. Không dùng cặp polymorphic entity_type + entity_id để liên kết Audit tới hồ sơ nghiệp vụ.
3. Audit event PL4 gắn ISSUE bằng Business Entity của ISSUE. ID của Assignment/Work Report/Confirmation có thể lưu trong metadata, không tạo FK đa hình.
4. Chỉ mục truy vấn Timeline tối thiểu gồm (business_entity_id, occurred_at); giữ các chỉ mục event code, actor, correlation và result phù hợp.
5. system_audit_logs append-only; ứng dụng không có thao tác UPDATE/DELETE. Ghi Audit bắt buộc cho thay đổi nghiệp vụ phải nằm trong cùng transaction với thay đổi đó.

## 3. Luồng Timeline PL4 chuẩn

1. **Tạo ISSUE:** ghi Issue, Business Entity, Status History khởi tạo (nếu áp dụng), Audit và Email Queue theo ADR-005/007/008 trong transaction tương ứng.
2. **Phân công/nhận việc:** tạo Assignment hoặc cập nhật trạng thái vòng đời Assignment theo ADR-006; ghi Audit và trạng thái ISSUE nếu có chuyển trạng thái.
3. **Báo cáo:** tạo Work Report, chuyển ISSUE sang PENDING_UOA_CONFIRMATION, ghi Status History, Audit và hai queue item Reporter/UOA Approver theo ADR-008 trong cùng transaction.
4. **UOA xác nhận:** tạo Confirmation, chuyển ISSUE sang CLOSED, ghi Status History/Audit và enqueue thông báo theo ADR-008 trong cùng transaction.
5. **UOA không xác nhận:** tạo Confirmation với reason bắt buộc, chuyển ISSUE về IN_PROGRESS, ghi Status History/Audit và enqueue thông báo theo ADR-008 trong cùng transaction.
6. **Gửi email:** Worker ghi từng attempt; Queue/Audit phản ánh kết quả gửi độc lập với trạng thái nghiệp vụ.

## 4. Bảo mật và toàn vẹn

- Actor bên ngoài có thể không có tài khoản Admin; lưu actor type/name/email snapshot theo chính sách dữ liệu và liên kết user ID khi có tài khoản.
- Không ghi raw action token, OTP, password, SMTP credential, API secret hoặc session secret.
- Lỗi Audit bắt buộc trong transaction nghiệp vụ phải làm rollback thay đổi nghiệp vụ.
- Email Worker/SMTP là xử lý ngoài transaction nghiệp vụ; ghi attempt/kết quả riêng, không giữ DB transaction trong lúc gọi SMTP.
- Audit Log không phải nguồn duy nhất để tính trạng thái hiện tại; bảng nghiệp vụ và History chuyên biệt vẫn là nguồn dữ liệu tương ứng.

## 5. Tiêu chí nghiệm thu

1. Có thể truy vết một ISSUE từ lúc tạo đến lúc đóng, bao gồm Assignment, Claim, Work Report và từng quyết định UOA.
2. Mọi thao tác quan trọng có actor, thời điểm, channel, event code, result và correlation ID phù hợp.
3. Nhiều lần phân công/báo cáo/xác nhận được giữ nguyên, không ghi đè lịch sử cũ.
4. Timeline hiển thị trạng thái email và từng lần thử gửi từ Queue/Attempts; không cần bảng Notification History riêng.
5. Retry email không tạo queue item mới và không thay đổi trạng thái ISSUE.
6. Audit của bản ghi con liên kết về đúng Business Entity ISSUE; không sử dụng polymorphic entity reference.
7. Không có raw token/OTP/secret/credential trong Audit, Queue, Attempt hoặc Timeline.
8. Quyền Timeline tuân thủ D18/D19; Super Admin không xem hồ sơ nghiệp vụ, MSTAR không mặc định xem raw Audit chi tiết.
9. Không thay đổi state machine PL4 hoặc các quyết định ADR-005 đến ADR-008.

## 6. Phạm vi không thay đổi

- Không thay đổi state machine PL4 đã duyệt.
- Không thay đổi Assignment/Claim Lifecycle theo ADR-006.
- Không thay đổi idempotency/atomicity theo ADR-005 và ADR-007.
- Không thay đổi Notification Matrix, Email Queue, retry policy theo ADR-008.
- Không tạo bảng pl4_issue_audit_logs hoặc pl4_notification_history.
- ADR này là thiết kế; chưa tạo Migration hoặc PHP code.

## 7. Tài liệu liên quan

- ERD_DESIGN.md
- DATA_DICTIONARY.md
- ERD_PHYSICAL.md
- WORKFLOW_SPECIFICATION.md
- IMPLEMENTATION_PLAN.md
