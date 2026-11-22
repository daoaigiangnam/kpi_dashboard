# ADR-006 – PL4 Assignment / Claim Lifecycle

**Phiên bản:** 1.0  
**Trạng thái:** APPROVED  
**Ngày phê duyệt:** 22/11/2026  
**Phạm vi:** Phân công, nhận việc, chống nhận trùng và tái phân công ISSUE PL4.

## 1. Bối cảnh

ISSUE PL3 có thể được tạo tự động mà chưa xác định Assignee. Mã Apps Script hiện tại cho phép Reporter chuyển tiếp liên kết cho người xử lý. PHP cần quản lý Assignment thành lịch sử riêng, kiểm soát đúng người nhận và tránh hai người cùng nhận một ISSUE.

## 2. Quyết định đã phê duyệt

1. ISSUE PL3 được tạo ở trạng thái `OPEN`, chưa có Assignee.
2. Reporter hoặc người có quyền phân công được chỉ định Assignee. Mỗi lần chỉ định tạo một dòng `pl4_assignments` mới; không ghi đè Assignment cũ.
3. Assignee được chỉ định nhận liên kết bảo mật gắn với đúng ISSUE và Assignment. Chỉ email/actor đã được chỉ định mới được chấp nhận thao tác nhận việc; không coi việc chuyển tiếp URL là chuyển quyền.
4. Khi Assignee chấp nhận, hệ thống cập nhật Assignment sang `CLAIMED`, ghi `claimed_at`, actor và Audit Log; ISSUE chuyển từ `OPEN` sang `IN_PROGRESS`.
5. Tại một thời điểm, một ISSUE chỉ có tối đa một Assignment đang hiệu lực. Thao tác claim phải được thực hiện nguyên tử bằng transaction/row lock và kiểm tra trạng thái hiện tại. Hai yêu cầu đồng thời không được cùng thành công.
6. Nếu Assignee từ chối, không nhận hoặc cần thay người, người có quyền phân công kết thúc Assignment hiện tại bằng trạng thái phù hợp (`DECLINED`, `EXPIRED` hoặc `RELEASED`) và tạo Assignment mới. Không xóa lịch sử.
7. Tái phân công sau khi ISSUE đã `IN_PROGRESS` không tự ý xóa lịch sử trạng thái ISSUE; Assignment mới phải được nhận trước khi người mới thực hiện hành động nghiệp vụ. Chuyển trạng thái ISSUE tiếp tục tuân thủ state machine PL4 đã duyệt.
8. Work Report phải tham chiếu Assignment thực hiện (`assignment_id`) để xác định đúng người xử lý và lịch sử phân công.
9. Liên kết nhận việc dùng token bảo mật lưu hash, giới hạn đúng ISSUE/Assignment/action, có expiry, revocation và used_at; không lưu token rõ hoặc ghi token vào Audit Log.
10. Mọi lần phân công, nhận việc, từ chối, hết hạn, giải phóng và tái phân công phải được ghi Audit Log; dữ liệu Assignment là append-only về mặt lịch sử, chỉ cập nhật các mốc/trạng thái vòng đời cần thiết.

## 3. Trạng thái Assignment

- `ASSIGNED`: đã chỉ định, đang chờ Assignee chấp nhận.
- `CLAIMED`: Assignee đã nhận việc; Assignment đang hiệu lực.
- `DECLINED`: Assignee từ chối.
- `EXPIRED`: liên kết nhận việc hết hạn mà chưa được chấp nhận.
- `RELEASED`: Assignment được người có quyền kết thúc để tái phân công.

Không dùng trạng thái Assignment để thay thế trạng thái ISSUE. ISSUE vẫn theo state machine PL4 đã phê duyệt.

## 4. Ràng buộc kỹ thuật

- Mỗi ISSUE có thể có nhiều Assignment theo thời gian.
- Mỗi ISSUE có tối đa một Assignment ở trạng thái đang hiệu lực (`ASSIGNED` hoặc `CLAIMED`).
- Service phải khóa dòng ISSUE khi tạo/claim/release Assignment; kiểm tra trạng thái và quyền trong cùng transaction.
- Có thể bổ sung unique constraint/index phù hợp để hỗ trợ điều kiện một Assignment hiệu lực; không chỉ dựa vào kiểm tra ở giao diện.
- Mọi thao tác claim phải idempotent: gửi lại cùng action không tạo thêm Assignment hoặc claim lần hai.
- Không cho Assignee cũ gửi Work Report bằng Assignment đã RELEASED/DECLINED/EXPIRED.

## 5. Phạm vi không thay đổi

ADR này không thay đổi:
- Nguồn PL3 và chống tạo ISSUE trùng theo ADR-005.
- Priority theo ADR-004.
- State machine ISSUE: `OPEN → IN_PROGRESS → PENDING_UOA_CONFIRMATION → CLOSED`; UOA NOT_CONFIRMED trả về `IN_PROGRESS`.
- Quyền UOA xác nhận cuối cùng; Assignee không được tự đóng ISSUE.
- Email Queue dùng chung; email lỗi không đảo ngược quyết định nghiệp vụ.
