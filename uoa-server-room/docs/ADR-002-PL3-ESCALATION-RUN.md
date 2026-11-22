# ADR-002 – Mô hình chu kỳ PL3 Escalation Run

**Phiên bản:** 1.0 – Đã phê duyệt  
**Phạm vi:** PL3 Scheduler, Escalation Run, Escalation Events, Business Settings  
**Trạng thái:** APPROVED – ĐÃ ĐƯỢC CHỦ NGHIỆP VỤ PHÊ DUYỆT  
**Nguồn đối chiếu:** `Code PL3.txt` (Apps Script hiện tại)

## 1. Kết quả đối chiếu mã nguồn hiện tại

Mã nguồn PL3 hiện triển khai cảnh báo thiếu phiếu kiểm tra **hằng ngày (DAILY)**. Chưa thấy lịch cảnh báo WEEKLY hoặc MONTHLY.

Luồng đang có:
1. Scheduler chạy theo cấu hình `ALERT_CHECK_INTERVAL_MINUTES`; mặc định 5 phút, chấp nhận 1/5/10/15/30 phút.
2. Chỉ xử lý nếu `SYSTEM_STATUS = ACTIVE`, `DAILY_ALERT_ENABLED = TRUE`, ngày hiện tại thuộc `WORK_DAYS` và không nằm trong danh sách ngày nghỉ.
3. Không xét cảnh báo trước `DAILY_DEADLINE`.
4. Sau deadline, cấp 1 gửi MSTAR; cấp 2 gửi Room Approver; cấp 3 gửi Manager.
5. `ALERT_LEVEL1_DELAY_MINUTES` tính từ deadline đến cấp 1.
6. `ALERT_LEVEL2_DELAY_MINUTES` tính từ thời điểm cấp 1 được ghi nhận đến cấp 2.
7. `ALERT_LEVEL3_DELAY_MINUTES` tính từ thời điểm cấp 2 được ghi nhận đến cấp 3.
8. Nếu thiếu địa chỉ người nhận ở một cấp, mã nguồn ghi nhận cấp đó là SKIPPED và vẫn lưu thời điểm cấp để tính cấp kế tiếp.
9. Nếu đã có phiếu PL3 hợp lệ đúng Room_ID + Inspection_Date + Form_Response_ID, xóa state cảnh báo của phòng/ngày và dừng các cấp còn lại.
10. State hiện tại lưu trong Script Properties với khóa `ESC_<date>_<roomId>`; mỗi cấp có `sentAt1/2/3`.

Nguồn hiện tại dùng một lịch kiểm tra hằng ngày. Trường `frequency = DAILY/WEEKLY/MONTHLY` trong Data Dictionary hiện chưa được chứng minh bởi Form/Apps Script PL3 đã đối chiếu; không được mặc định coi Weekly/Monthly là nghiệp vụ đang vận hành.

## 2. Vấn đề thiết kế khi chuyển sang MySQL

Script Properties không phải nơi lưu trạng thái phù hợp cho hệ thống nhiều worker hoặc nhiều instance PHP. Cần lưu trạng thái cảnh báo trong MySQL để:
- Không gửi trùng khi Scheduler chạy lặp hoặc nhiều worker cùng xử lý.
- Có thể tiếp tục đúng cấp sau khi ứng dụng khởi động lại.
- Lưu lại cấu hình đã áp dụng cho từng chu kỳ.
- Truy vết từng cấp gửi, bỏ qua, lỗi và dừng do phiếu kiểm tra đã được nộp.

## 3. Phương án khóa chu kỳ

### Khóa duy nhất đã được phê duyệt

Một chu kỳ cảnh báo được nhận diện bởi:

`(room_id, work_date, schedule_type)`

Với phạm vi hiện tại, `schedule_type = DAILY`.

Tạo UNIQUE:
- `UNIQUE(room_id, work_date, schedule_type)` trên `pl3_escalation_runs`.

Không dùng `frequency` như một thành phần khóa cho đến khi có yêu cầu nghiệp vụ Weekly/Monthly được xác nhận. Nếu sau này phát sinh lịch kiểm tra theo tuần/tháng, cần ADR hoặc quyết định nghiệp vụ riêng để xác định kỳ, ngày đến hạn và quy tắc khi nhiều phiếu được nộp trong một kỳ.

### Trạng thái Run đã chốt

- `PENDING`: chưa đến deadline hoặc chưa gửi cấp nào.
- `IN_PROGRESS`: đang xử lý các cấp cảnh báo.
- `STOPPED_INSPECTION_RECEIVED`: đã nhận phiếu hợp lệ, dừng cấp còn lại.
- `COMPLETED`: đã xử lý hết các cấp được cấu hình.
- `ERROR`: có lỗi cần xử lý/retry; không đồng nghĩa đã gửi thành công.

Các trạng thái Run ở trên là bộ trạng thái chuẩn để cụ thể hóa trong Data Dictionary và thiết kế vật lý.

## 4. Escalation Events đã được phê duyệt

Mỗi cấp là một event riêng, liên kết FK tới Run:
- `UNIQUE(escalation_run_id, level)` để mỗi cấp chỉ có một bản ghi logic.
- Lưu recipient snapshot, scheduled_at, attempted_at, sent_at, status, error_code và số lần thử.
- Status tối thiểu: `PENDING`, `SENDING`, `SENT`, `SKIPPED`, `RETRY`, `FAILED`, `STOPPED`.
- Email gửi qua Email Queue dùng event_key idempotent; không gửi SMTP trực tiếp trong transaction Scheduler.

Cần phân biệt:
- `SKIPPED`: không có recipient hợp lệ theo dữ liệu cấu hình.
- `FAILED/RETRY`: có recipient nhưng gửi email thất bại; phải retry theo chính sách Email Queue.
- `STOPPED`: phiếu PL3 hợp lệ đã đến trước khi cấp đó được gửi.

## 5. Cách tính thời gian cảnh báo

Mã nguồn hiện tại sử dụng cách tính **tuần tự sau thời điểm cấp trước**:
- Level 1 due = DAILY_DEADLINE + delay cấp 1.
- Level 2 due = thời điểm cấp 1 được ghi nhận + delay cấp 2.
- Level 3 due = thời điểm cấp 2 được ghi nhận + delay cấp 3.

**Quyết định đã phê duyệt D3:** giữ chế độ tuần tự. Level 1 đến hạn tại `DAILY_DEADLINE + ALERT_LEVEL1_DELAY_MINUTES`; Level 2 đến hạn tại thời điểm ghi nhận kết quả Level 1 cộng `ALERT_LEVEL2_DELAY_MINUTES`; Level 3 tương tự theo Level 2 và `ALERT_LEVEL3_DELAY_MINUTES`. Các giá trị delay vẫn là cấu hình động trong Admin, không hard-code.

**Quyết định đã phê duyệt D4:** nếu cấp trước bị `SKIPPED` do thiếu recipient hợp lệ, thời điểm ghi nhận `SKIPPED` vẫn là mốc tính delay của cấp kế tiếp. Đây là hành vi tương thích Apps Script hiện tại.

## 6. Khi phiếu PL3 được nộp

Điều kiện nhận phiếu để dừng cảnh báo phải theo cùng quy tắc nguồn:
- Cùng `room_id`.
- Cùng `inspection_date` của ngày làm việc đang xét.
- Có Form Response ID hợp lệ/idempotent.

Khi phiếu hợp lệ được commit:
1. Khóa Run tương ứng trong transaction.
2. Chuyển Run sang `STOPPED_INSPECTION_RECEIVED` nếu chưa hoàn tất.
3. Các event chưa gửi chuyển `STOPPED`.
4. Event đã gửi giữ nguyên lịch sử, không xóa.
5. Worker không được gửi event đã STOPPED.
6. Nếu Scheduler và submit PL3 chạy đồng thời, transaction/conditional update phải bảo đảm chỉ một kết quả thắng; không gửi cảnh báo sau khi đã ghi nhận phiếu hợp lệ.

## 7. Scheduler và chống xử lý trùng

- Scheduler đọc các Run đến hạn; không tự tạo nhiều Run trùng khóa.
- Tạo Run bằng INSERT có UNIQUE key; khi trùng thì lấy Run hiện có.
- Worker claim event bằng transaction/row lock hoặc cơ chế claim an toàn tương đương.
- Chỉ chuyển event sang SENDING khi claim thành công.
- Không giữ transaction trong lúc gọi SMTP; Email Queue chịu trách nhiệm gửi/retry.
- Sau khi queue ghi nhận kết quả, cập nhật Event bằng conditional update.
- Dùng Audit Log ghi các sự kiện RUN_CREATED, LEVEL_SCHEDULED, LEVEL_SENT, LEVEL_SKIPPED, LEVEL_FAILED, RUN_STOPPED_BY_INSPECTION.
- Không xóa Run/Event để làm sạch state; áp dụng retention/archive theo chính sách riêng.

## 8. Quyết định đã phê duyệt

Ngày phê duyệt: **22/11/2026**  
Người phê duyệt: **Chủ nghiệp vụ hệ thống UOA Server Room Management**

| Mã | Quyết định | Kết quả |
|---|---|---|
| D1 | Phạm vi hiện tại chỉ có lịch `DAILY`; chưa xác nhận/triển khai `WEEKLY` hoặc `MONTHLY` | **ĐỒNG Ý** |
| D2 | Khóa duy nhất Run là `(room_id, work_date, schedule_type)`; `schedule_type = DAILY` trong phạm vi hiện tại | **ĐỒNG Ý** |
| D3 | Tính delay tuần tự từ mốc cấp cảnh báo trước; Level 1 tính từ `DAILY_DEADLINE + ALERT_LEVEL1_DELAY_MINUTES` | **ĐỒNG Ý** |
| D4 | Nếu cấp trước `SKIPPED` do thiếu recipient, lấy thời điểm `SKIPPED` làm mốc tính cấp kế tiếp | **ĐỒNG Ý** |
| D5 | Khi có Inspection hợp lệ đúng phòng/ngày, dừng các event chưa gửi; giữ nguyên lịch sử event đã xử lý | **ĐỒNG Ý** |
| D6 | Gửi email qua Email Queue có retry; Escalation Event lưu trạng thái từng cấp và liên kết queue | **ĐỒNG Ý** |

Tất cả 6/6 quyết định đã được xác nhận. ADR-002 có hiệu lực làm căn cứ cập nhật ERD và Data Dictionary. Việc phê duyệt ADR không đồng nghĩa đã triển khai Migration hoặc PHP Scheduler.

## 9. Trạng thái và phạm vi cập nhật

Các tài liệu kiến trúc được cập nhật đồng bộ theo ADR-002:
- `ERD_DESIGN.md`
- `DATA_DICTIONARY.md`
- `ERD_PHYSICAL.md`
- `WORKFLOW_SPECIFICATION.md`
