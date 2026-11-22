# ADR-002 – Mô hình chu kỳ PL3 Escalation Run

**Phiên bản:** 0.1 – Đề xuất chờ phê duyệt  
**Phạm vi:** PL3 Scheduler, Escalation Run, Escalation Events, Business Settings  
**Trạng thái:** PROPOSED – CHƯA ĐƯỢC PHÊ DUYỆT  
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

### Đề xuất khóa duy nhất

Một chu kỳ cảnh báo được nhận diện bởi:

`(room_id, work_date, schedule_type)`

Với phạm vi hiện tại, `schedule_type = DAILY`.

Tạo UNIQUE:
- `UNIQUE(room_id, work_date, schedule_type)` trên `pl3_escalation_runs`.

Không dùng `frequency` như một thành phần khóa cho đến khi có yêu cầu nghiệp vụ Weekly/Monthly được xác nhận. Nếu sau này phát sinh lịch kiểm tra theo tuần/tháng, cần ADR hoặc quyết định nghiệp vụ riêng để xác định kỳ, ngày đến hạn và quy tắc khi nhiều phiếu được nộp trong một kỳ.

### Trạng thái Run đề xuất

- `PENDING`: chưa đến deadline hoặc chưa gửi cấp nào.
- `IN_PROGRESS`: đang xử lý các cấp cảnh báo.
- `STOPPED_INSPECTION_RECEIVED`: đã nhận phiếu hợp lệ, dừng cấp còn lại.
- `COMPLETED`: đã xử lý hết các cấp được cấu hình.
- `ERROR`: có lỗi cần xử lý/retry; không đồng nghĩa đã gửi thành công.

Tên trạng thái cuối cùng sẽ được chuẩn hóa trong Data Dictionary khi quyết định được phê duyệt.

## 4. Escalation Events

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

**Khuyến nghị:** giữ chế độ tuần tự này khi chuyển đổi để tương thích hành vi đang chạy. Tuy nhiên, D13 trước đây đã xác định cách tính thời gian cảnh báo phải cấu hình động và còn mở lựa chọn tính từ deadline hoặc từ lần cảnh báo trước. Do đó, chế độ tuần tự phải được người phụ trách xác nhận trước khi chốt.

Nếu cấp trước bị SKIPPED do thiếu người nhận, có hai lựa chọn cần xác nhận:
- A. Giữ tương thích nguồn: ghi nhận thời điểm SKIPPED làm mốc tính delay cấp kế tiếp.
- B. Không cho cấp sau chạy cho đến khi cấu hình người nhận được sửa.

Nguồn Apps Script hiện tại đang thực hiện lựa chọn A.

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

## 8. Quyết định cần phê duyệt

- [ ] **D1:** Xác nhận phạm vi hiện tại chỉ có DAILY; Weekly/Monthly chưa triển khai.
- [ ] **D2:** Chấp nhận khóa UNIQUE `(room_id, work_date, schedule_type)`, với `schedule_type = DAILY`.
- [ ] **D3:** Giữ tính delay tuần tự từ mốc cấp cảnh báo trước như Apps Script hiện tại.
- [ ] **D4:** Nếu cấp trước SKIPPED do thiếu recipient, vẫn lấy thời điểm SKIPPED làm mốc cấp tiếp theo (tương thích nguồn).
- [ ] **D5:** Khi có phiếu hợp lệ, dừng các cấp chưa gửi; giữ nguyên lịch sử các cấp đã gửi.
- [ ] **D6:** Email Queue xử lý gửi/retry; Escalation Event lưu trạng thái nghiệp vụ từng cấp.

## 9. Trạng thái và phạm vi cập nhật

ADR-002 đang chờ phê duyệt. Chưa cập nhật Data Dictionary/ERD theo các đề xuất trong tài liệu này; chưa tạo Migration hoặc PHP Scheduler.

Sau khi được duyệt, cập nhật đồng bộ:
- `ERD_DESIGN.md`
- `DATA_DICTIONARY.md`
- `ERD_PHYSICAL.md`
- `WORKFLOW_SPECIFICATION.md` nếu có quyết định nghiệp vụ mới.
