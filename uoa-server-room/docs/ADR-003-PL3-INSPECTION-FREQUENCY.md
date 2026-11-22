# ADR-003 – Phạm vi tần suất kiểm tra PL3

**Phiên bản:** 1.0  
**Trạng thái:** APPROVED – ĐÃ ĐƯỢC CHỦ NGHIỆP VỤ XÁC NHẬN  
**Ngày phê duyệt:** 22/11/2026  
**Phạm vi:** PL3 Inspection Frequency; không thay đổi ADR-002 về Escalation Run

## 1. Bối cảnh

Phụ lục 3 PDF có ba lựa chọn Inspection Frequency: Daily when MSTAR is onsite, Weekly và Monthly. Tuy nhiên, Apps Script PL3 được cung cấp không có trường Frequency trong `INSPECTION_HEADERS`, không tạo câu hỏi Frequency trong `createForm_()`, không đọc/lưu Frequency trong `handlePL3Submit()`; scheduler được rà soát là `runDailyAlertScheduler()`.

Để hệ thống online có một quy trình thống nhất, chủ nghiệp vụ chọn chỉ triển khai kiểm tra hằng ngày.

## 2. Quyết định được phê duyệt

**D1 – Chọn phương án B: Chỉ triển khai DAILY.**

1. Form PL3 online chỉ có quy trình kiểm tra **DAILY – hằng ngày**.
2. Không tạo trường lựa chọn Inspection Frequency.
3. Không lưu cột `inspection_frequency` trong `pl3_inspections`.
4. Không triển khai nghiệp vụ kiểm tra Weekly hoặc Monthly trong phạm vi hiện tại.
5. Lịch cảnh báo thiếu phiếu tiếp tục chỉ là `DAILY`, tuân thủ ADR-002.
6. Cụm từ “Daily when MSTAR is onsite” trong Phụ lục 3 được ghi nhận là nội dung nguồn; quy tắc xác định ngày làm việc/không làm việc tiếp tục căn cứ cấu hình lịch DAILY, WORK_DAYS và Holidays đã có, không tự suy diễn lịch Weekly/Monthly.

## 3. Phân biệt phạm vi

- **Inspection cadence:** quy trình kiểm tra được số hóa trong phiên bản hiện tại – DAILY.
- **Escalation schedule:** lịch Scheduler tạo Run cảnh báo thiếu phiếu – DAILY theo ADR-002.

Hai nội dung cùng đang là DAILY nhưng là hai khái niệm nghiệp vụ/kỹ thuật riêng; không gộp trường hoặc bảng chỉ để biểu diễn chúng.

## 4. Ngoài phạm vi và thay đổi tương lai

Weekly/Monthly không được triển khai trong Form, database hoặc Scheduler hiện tại. Nếu phát sinh nhu cầu, phải lập ADR mới xác định tối thiểu: định nghĩa kỳ, ngày đến hạn, ngày làm việc/ngoại lệ, số phiếu cần có trong kỳ, cách xác định thiếu phiếu, cảnh báo/escalation và báo cáo tuân thủ. Không mở rộng bằng cách chỉ thêm một giá trị enum.

## 5. Ảnh hưởng thiết kế

- Không thêm `inspection_frequency` vào `pl3_inspections`.
- Không thêm lựa chọn Weekly/Monthly vào Form PL3.
- Các báo cáo PL3 hiện tại là báo cáo kiểm tra DAILY.
- Giữ nguyên `pl3_escalation_runs.schedule_type = DAILY` và các quy tắc đã phê duyệt trong ADR-002.
- Đây là quyết định nghiệp vụ và thiết kế; chưa đồng nghĩa đã triển khai Migration hoặc PHP.

## 6. Nguồn và giới hạn xác minh

Quyết định được đưa ra sau khi đối chiếu Phụ lục 3 PDF và mã Apps Script PL3 được cung cấp. Giao diện Google Form đang triển khai thực tế chưa được truy cập/xác minh độc lập; vì vậy không khẳng định bản live hiện tại có đúng hoặc khác mã setup. Quyết định này áp dụng cho hệ thống PHP online sẽ xây dựng.
