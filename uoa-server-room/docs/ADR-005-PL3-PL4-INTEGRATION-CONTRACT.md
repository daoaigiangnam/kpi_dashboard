# ADR-005 – Hợp đồng tích hợp PL3 → PL4

**Phiên bản:** 0.2  
**Trạng thái:** PROPOSED – D1 ĐÃ CHỐT; D2–D3 CHỜ XÁC NHẬN  
**Ngày lập:** 22/11/2026  
**Phạm vi:** Định danh nguồn FAIL, idempotency và giao nhận sự kiện giữa PL3 và PL4 khi chuyển sang PHP/MySQL.

## 1. Hiện trạng mã nguồn PL4

Theo `Code PL4.txt`:
- PL4 nhận payload có Inspection_ID, Room_ID và API secret.
- PL4 đọc trực tiếp Google Sheets PL3 (`Inspections`, `Inspection Details`) để tìm các dòng FAIL.
- PL4 kiểm tra Item ID bằng regex `/^I\\d{2}$/`.
- Khóa chống trùng hiện tại là `Inspection_ID|Item_ID`.
- Mỗi FAIL tạo một Issue, nhưng nguồn là dòng Sheet chứ chưa phải ID kết quả quan hệ trong MySQL.

Thiết kế Checklist PL3 động đã xác định `pl3_inspection_results` và `pl3_checklist_version_items`; vì vậy cách định danh bằng chuỗi mã I01–I12 không còn là khóa phù hợp.

## 2. Phương án đề xuất

1. PL3 tạo Inspection và các Inspection Results trong MySQL; mỗi result có PK ổn định.
2. Khi result có trạng thái FAIL hợp lệ, trong cùng transaction tạo một `pl3_integration_outbox` row với UNIQUE `inspection_result_id` và event key ổn định.
3. Worker chuyển sự kiện sang PL4 bằng internal service/event contract; PL4 lấy nguồn chuẩn theo `inspection_result_id`, không đọc Google Sheets.
4. `pl4_issues.source_inspection_result_id` là FK UNIQUE nullable. Một result FAIL chỉ tạo tối đa một Issue.
5. Lưu snapshot trên Issue: inspection code/date, room, checklist item code/title, FAIL remarks và checklist version/item reference cần thiết để báo cáo lịch sử.
6. Retry chỉ xử lý lại cùng outbox row và cùng nguồn result; không tạo Issue trùng.
7. Nếu result không còn FAIL, không tồn tại, không thuộc Inspection/Room được khai báo hoặc nguồn không hợp lệ, từ chối xử lý và ghi Audit/Integration Log.
8. Không dùng regex định dạng Item ID để xác định tính hợp lệ. Validate FK, trạng thái result, version item và quyền nguồn.

## 3. Quyết định và câu hỏi cần xác nhận

### D1 – Khóa nguồn chuẩn và chống tạo trùng: ĐÃ ĐỒNG Ý

Ngày 22/11/2026, chủ nghiệp vụ xác nhận dùng `pl3_inspection_results.id` làm khóa nguồn chuẩn và đặt UNIQUE trên `pl4_issues.source_inspection_result_id`. Một kết quả Inspection Result chỉ được liên kết tối đa một Issue PL4. D1 được xem là đã chốt; D2 và D3 vẫn đang chờ xác nhận.

### Các điểm còn chờ xác nhận

- **D2:** Đồng ý PL3 Outbox và PL4 cùng dùng MySQL trong một ứng dụng PHP, worker xử lý nội bộ; không tiếp tục gọi Apps Script Web App/Google Sheets khi vận hành PHP?
- **D3:** Khi một Issue PL3 đã được tạo, nếu Checklist version hoặc nội dung item thay đổi về sau, Issue giữ nguyên snapshot tại thời điểm tạo; không đồng bộ ngược các thay đổi sau đó?

## 4. Các nguyên tắc không thay đổi

- Một kết quả FAIL sinh tối đa một Issue.
- Issue MANUAL không có nguồn PL3.
- Priority PL3 lấy từ Business Settings theo ADR-004.
- State machine PL4 theo WORKFLOW_SPECIFICATION; không dùng PENDING_APPROVAL/REJECTED legacy.
- Email qua Email Queue; API secret/token không ghi log.
- Không tạo Migration/PHP trước khi ADR này được phê duyệt và ERD/Data Dictionary được rà soát đồng bộ.
