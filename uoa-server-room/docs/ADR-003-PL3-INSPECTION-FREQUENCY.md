# ADR-003 – Phạm vi và dữ liệu tần suất kiểm tra PL3

**Phiên bản:** 0.1 – Đề xuất, chờ xác nhận nghiệp vụ  
**Trạng thái:** PROPOSED – CHƯA ĐƯỢC PHÊ DUYỆT  
**Phạm vi:** PL3 Inspection Frequency; không thay đổi ADR-002 về Escalation Run

## 1. Mục đích

Làm rõ sự khác nhau giữa:
- Tần suất kiểm tra được nêu trong Phụ lục 3.
- Tần suất mà Google Form/Apps Script hiện tại thực sự thu thập và tự động xử lý.
- Tần suất chạy cảnh báo thiếu phiếu.

ADR-002 chỉ phê duyệt lịch cảnh báo thiếu phiếu DAILY. ADR này không tự động mở rộng lịch cảnh báo sang WEEKLY/MONTHLY.

## 2. Bằng chứng từ nguồn đã kiểm tra

### 2.1. Phụ lục 3 PDF

Phần “Inspection Frequency / Lựa chọn tần suất” hiển thị ba lựa chọn:
- Daily when MSTAR is onsite / Hằng ngày khi MSTAR làm việc Onsite.
- Weekly / Hằng tuần.
- Monthly / Hằng tháng.

### 2.2. Apps Script PL3

Trong `Code PL3.txt`:
- `INSPECTION_HEADERS` không có cột `Frequency`.
- Hàm `createForm_()` tạo Form nhưng không thêm câu hỏi Inspection Frequency.
- `handlePL3Submit()` không đọc/lưu giá trị Frequency.
- Scheduler hiện tại là `runDailyAlertScheduler()`, sử dụng `DAILY_DEADLINE`, `WORK_DAYS`, Holidays và `DAILY_ALERT_ENABLED`.
- Chưa thấy scheduler Weekly hoặc Monthly trong phần mã nguồn đã rà soát.

Do đó chưa thể kết luận giá trị Frequency trong bản PDF đang được lưu trong hệ thống hiện tại. Cũng chưa thể xác nhận giao diện Google Form đang triển khai thực tế có khác với mã setup được cung cấp hay không.

## 3. Hai khái niệm không được gộp

1. `inspection_frequency`: tần suất/loại kiểm tra được người thực hiện chọn hoặc được quy định cho hồ sơ.
2. `schedule_type` của `pl3_escalation_runs`: lịch mà Scheduler dùng để tạo chu kỳ cảnh báo thiếu phiếu.

Theo ADR-002, `schedule_type` hiện chỉ là `DAILY`. Không được suy ra Weekly/Monthly escalation chỉ vì Phụ lục 3 có các lựa chọn Frequency.

## 4. Phương án cần xác nhận

### Phương án A – Ghi nhận tần suất kiểm tra, chỉ tự động cảnh báo DAILY

- Form PHP có trường Inspection Frequency với ba lựa chọn đúng như Phụ lục 3.
- Lưu giá trị vào `pl3_inspections.inspection_frequency`.
- Giá trị này là dữ liệu nghiệp vụ của từng phiếu; không tự tạo Weekly/Monthly Scheduler.
- Escalation Run tiếp tục tuân thủ ADR-002: chỉ DAILY.
- Chỉ triển khai khi chủ nghiệp vụ xác nhận trường Frequency thực sự cần có trong Form mới.

### Phương án B – Chỉ triển khai kiểm tra DAILY

- Không đưa Frequency vào Form hoặc bảng Inspection.
- Ghi nhận rằng lựa chọn Weekly/Monthly trên PDF chưa được triển khai trong hệ thống số hóa hiện tại.
- Nếu cần Weekly/Monthly sau này, phải có quyết định riêng về lịch, kỳ, ngày đến hạn, nhiều phiếu trong một kỳ và cảnh báo tương ứng.

## 5. Quyết định cần chủ nghiệp vụ xác nhận

- [ ] **D1:** Chọn Phương án A – Form PHP thu thập Frequency theo Phụ lục 3, nhưng cảnh báo tự động vẫn chỉ DAILY.
- [ ] **D2:** Chọn Phương án B – hệ thống số hóa chỉ triển khai DAILY, chưa đưa Frequency vào Form/Inspection.
- [ ] **D3:** Dù chọn A hay B, xác nhận không tự động triển khai Weekly/Monthly escalation trong phạm vi ADR-002.

## 6. Điều kiện trước Migration

Chưa áp đặt NOT NULL, CHECK enum hoặc quy tắc kỳ Weekly/Monthly cho đến khi D1–D3 được xác nhận và đối chiếu bản Form thực tế. Nếu không truy cập được Form đang triển khai, cần ghi nhận đây là giới hạn xác minh nguồn, không được coi mã setup là bằng chứng tuyệt đối về giao diện live.

