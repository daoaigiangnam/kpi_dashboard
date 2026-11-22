# ADR-004 – Danh mục Priority cho PL4

**Phiên bản:** 1.0  
**Trạng thái:** APPROVED – ĐÃ PHÊ DUYỆT  
**Ngày phê duyệt:** 22/11/2026  
**Phạm vi:** Danh mục mức độ ưu tiên PL4; không thay đổi state machine PL4 đã chốt.

## 1. Bằng chứng từ mã nguồn PL4

Trong `Code PL4.txt` được cung cấp:
- Form thủ công có bốn mức Priority: LOW - Thấp, MEDIUM - Trung bình, HIGH - Cao, CRITICAL - Khẩn cấp.
- ISSUE tạo tự động từ PL3 hiện được gán cứng `HIGH - Cao`.
- Trường Priority hiện được lưu trực tiếp trong PL4 Issues.
- Mã Apps Script còn dùng trạng thái PENDING_APPROVAL/REJECTED; đây là luồng cũ và không được dùng thay cho workflow PL4 đã được chủ nghiệp vụ chốt.

## 2. Quyết định đã phê duyệt

### D1 – Danh mục Priority động: ĐỒNG Ý

Tạo danh mục `pl4_priorities`, Super Admin được quản lý:
- Mã Priority ổn định, duy nhất: khởi tạo LOW, MEDIUM, HIGH, CRITICAL.
- Tên tiếng Việt, mô tả, màu hiển thị và thứ tự.
- Trạng thái đang áp dụng/ngừng áp dụng.

Không xóa cứng Priority đã được sử dụng. Mã Priority đã phát sinh ISSUE không được đổi; nếu cần thay đổi ý nghĩa nghiệp vụ phải tạo mã mới. Priority ngừng áp dụng không được chọn cho ISSUE mới, nhưng vẫn hiển thị trong lịch sử.

### D2 – Snapshot Priority trên ISSUE: ĐỒNG Ý

Mỗi `pl4_issues` lưu:
- `priority_id`: FK tới danh mục Priority đã chọn.
- `priority_code_snapshot`: mã Priority tại thời điểm tạo ISSUE.
- `priority_name_snapshot`: tên hiển thị tại thời điểm tạo ISSUE.

Snapshot là nguồn hiển thị lịch sử. Việc đổi tên, mô tả, màu, thứ tự hoặc ngừng áp dụng trong danh mục không được làm thay đổi Priority đã ghi nhận trên ISSUE cũ. Không cho phép đổi mã Priority đã được sử dụng.

### D3 – Priority mặc định cho ISSUE từ PL3: CHỌN A

- Giá trị khởi tạo: HIGH.
- Lưu mã Priority mặc định PL3 trong `business_settings` theo key `PL4_PL3_DEFAULT_PRIORITY_CODE` (scope hệ thống/module PL4).
- Super Admin được thay đổi cấu hình sang một Priority đang active.
- Khi tạo ISSUE từ PL3, hệ thống đọc cấu hình tại thời điểm tạo, xác thực Priority còn active, sau đó lưu cả FK và snapshot mã/tên.
- Nếu cấu hình thiếu, trỏ tới mã không tồn tại hoặc Priority đã inactive, không tự âm thầm fallback; chặn tạo ISSUE, ghi Audit Log lỗi cấu hình và đưa outbox vào trạng thái xử lý lỗi có thể retry sau khi cấu hình được sửa.
- Thay đổi cấu hình chỉ ảnh hưởng ISSUE PL3 tạo sau thời điểm thay đổi; không cập nhật ISSUE đã tồn tại.

## 3. Nguyên tắc nghiệp vụ bổ sung

1. ISSUE MANUAL chọn Priority từ danh mục đang active.
2. ISSUE PL3 lấy Priority mặc định từ Business Settings; không suy diễn Priority từ checklist item hoặc mức độ FAIL.
3. Priority không tự quyết định SLA, thời hạn xử lý, escalation, người nhận hay state transition. Các quy tắc này cần quyết định riêng.
4. State machine được Workflow Engine kiểm soát, không cho sửa trạng thái hoặc chuyển trạng thái bằng màn hình danh mục.
5. Mọi thay đổi danh mục và Business Settings phải được Audit Log ghi nhận actor, thời điểm và before/after.

## 4. State machine PL4 không thay đổi

Các trạng thái:
- OPEN
- IN_PROGRESS
- PENDING_UOA_CONFIRMATION
- CLOSED

Assignee báo cáo COMPLETED hoặc NOT_COMPLETED đều chuyển PENDING_UOA_CONFIRMATION. UOA xác nhận thì CLOSED; UOA không xác nhận phải có lý do và trả về IN_PROGRESS. Không dùng PENDING_APPROVAL/REJECTED của Apps Script cũ.

## 5. Điều kiện trước Migration

ADR-004 đã phê duyệt về nghiệp vụ và thiết kế. Trước Migration vẫn phải hoàn tất rà soát PL4, ERD, Data Dictionary, ràng buộc FK/UNIQUE, tích hợp PL3 Checklist động và Email Queue. Việc phê duyệt ADR không đồng nghĩa đã tạo Migration hoặc code PHP.
