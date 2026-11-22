# ADR-005 – Hợp đồng tích hợp PL3 → PL4

**Phiên bản:** 1.0  
**Trạng thái:** APPROVED  
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

## 3. Quyết định đã phê duyệt

### D1 – Khóa nguồn chuẩn và chống tạo trùng: ĐÃ ĐỒNG Ý

Ngày 22/11/2026, chủ nghiệp vụ xác nhận dùng `pl3_inspection_results.id` làm khóa nguồn chuẩn và đặt UNIQUE trên `pl4_issues.source_inspection_result_id`. Một kết quả Inspection Result chỉ được liên kết tối đa một Issue PL4. D1 được phê duyệt.

### D2 – Cùng MySQL, xử lý Outbox bằng PHP Worker: ĐÃ ĐỒNG Ý

Ngày 22/11/2026, chủ nghiệp vụ xác nhận PL3 Outbox và PL4 cùng dùng MySQL trong ứng dụng PHP; PHP Worker xử lý Outbox nội bộ. Khi vận hành hệ thống PHP mới, không tiếp tục gọi Apps Script Web App hoặc đọc Google Sheets làm nguồn dữ liệu tích hợp.

Hệ quả thiết kế: Outbox row được tạo trong cùng transaction với Inspection Result; Worker claim và xử lý theo cơ chế an toàn khi chạy đồng thời; retry cùng event/source ID; ghi trạng thái, số lần thử, thời điểm thử tiếp theo và lỗi đã làm sạch; Issue được tạo trong transaction có UNIQUE `source_inspection_result_id`. Worker không gửi email trực tiếp mà tạo Email Queue record trong transaction nghiệp vụ phù hợp.

### D3 – Snapshot dữ liệu PL3 trên Issue: ĐÃ ĐỒNG Ý

Ngày 22/11/2026, chủ nghiệp vụ xác nhận ISSUE PL3 giữ nguyên snapshot tại thời điểm tạo. Khi Checklist version, mã câu hỏi, tiêu đề, mô tả hoặc nội dung câu trả lời thay đổi trong tương lai, dữ liệu snapshot trên ISSUE đã tạo không được cập nhật ngược.

ISSUE phải lưu đồng thời:
- FK nguồn `source_inspection_result_id` và FK tham chiếu Checklist version item để truy nguyên.
- Snapshot mã Inspection, ngày kiểm tra, mã/tên/phạm vi phòng tại thời điểm tạo.
- Snapshot mã phiên bản Checklist, mã item, tiêu đề/mô tả item tại thời điểm kiểm tra.
- Snapshot kết quả FAIL và nội dung remarks/action.

FK phục vụ truy nguyên dữ liệu gốc; snapshot là bằng chứng lịch sử để hiển thị/báo cáo và không bị ghi đè bởi thay đổi danh mục. Không xóa hoặc sửa phiên bản Checklist đã PUBLISHED; D3 bổ sung lớp bảo vệ lịch sử ở phía ISSUE.

## 4. Các nguyên tắc không thay đổi

- Một kết quả FAIL sinh tối đa một Issue.
- Issue MANUAL không có nguồn PL3.
- Priority PL3 lấy từ Business Settings theo ADR-004.
- State machine PL4 theo WORKFLOW_SPECIFICATION; không dùng PENDING_APPROVAL/REJECTED legacy.
- Email qua Email Queue; không gửi email trực tiếp trong Worker.
- PHP Worker xử lý Outbox nội bộ trên cùng MySQL; không tiếp tục Apps Script Web App/Google Sheets làm nguồn tích hợp khi vận hành PHP.
- Snapshot PL3 trên ISSUE bất biến sau khi tạo; mọi thay đổi Checklist chỉ ảnh hưởng Inspection mới.
- ADR này đã được phê duyệt. Chỉ chuyển sang Migration/PHP sau khi ERD, Data Dictionary, ERD vật lý và Workflow Specification được rà soát đồng bộ và có xác nhận triển khai.
