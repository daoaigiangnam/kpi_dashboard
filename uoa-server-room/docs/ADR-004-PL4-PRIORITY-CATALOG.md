# ADR-004 – Danh mục Priority cho PL4

**Phiên bản:** 0.1  
**Trạng thái:** PROPOSED – CHƯA ĐƯỢC PHÊ DUYỆT  
**Ngày lập:** 22/11/2026  
**Phạm vi:** Danh mục mức độ ưu tiên của PL4; không thay đổi state machine PL4 đã chốt.

## 1. Bằng chứng từ mã nguồn PL4

Trong `Code PL4.txt` được cung cấp:
- Form thủ công có bốn mức Priority: LOW - Thấp, MEDIUM - Trung bình, HIGH - Cao, CRITICAL - Khẩn cấp.
- ISSUE tạo tự động từ PL3 hiện được gán cứng `HIGH - Cao`.
- Trường Priority được lưu trực tiếp trong PL4 Issues.
- Mã Apps Script còn dùng trạng thái PENDING_APPROVAL/REJECTED; đây là luồng cũ và không được dùng thay cho workflow PL4 đã được chủ nghiệp vụ chốt trong WORKFLOW_SPECIFICATION.

## 2. Nguyên tắc thiết kế

1. Priority là danh mục nghiệp vụ có thể quản lý động bởi Super Admin.
2. Mỗi Priority có mã ổn định không đổi sau khi đã được sử dụng; tên hiển thị, mô tả, màu hiển thị và thứ tự có thể chỉnh cho các bản ghi mới.
3. Không xóa Priority đã được sử dụng; chỉ được ngừng áp dụng cho ISSUE mới.
4. ISSUE đã tạo phải lưu snapshot mã, tên và mức Priority tại thời điểm tạo để lịch sử không đổi khi danh mục được cập nhật.
5. Không tự gắn SLA, thời hạn xử lý hoặc escalation vào Priority khi chưa có quyết định SLA riêng.
6. Trạng thái ISSUE và quy tắc chuyển trạng thái không phải danh mục cấu hình tự do; phải được kiểm soát bởi Workflow Engine theo state machine đã chốt.

## 3. Phương án đề xuất

### D1 – Danh mục Priority động

Tạo `pl4_priorities` gồm tối thiểu:
- `id`, `code` UNIQUE (LOW/MEDIUM/HIGH/CRITICAL hoặc mã ổn định tương đương).
- `name_vi`, `description` nullable.
- `sort_order`, `is_active`.
- `created_at`, `updated_at`, actor quản trị nếu cần audit.

Khởi tạo dữ liệu ban đầu theo đúng mã nguồn: LOW, MEDIUM, HIGH, CRITICAL.

### D2 – Snapshot Priority trên ISSUE

Trong `pl4_issues`, lưu `priority_id` FK nullable/required theo quy tắc tạo Issue và snapshot `priority_code_snapshot`, `priority_name_snapshot`. Giá trị snapshot là nguồn hiển thị lịch sử; FK phục vụ tra cứu danh mục.

### D3 – Priority mặc định cho ISSUE từ PL3

Mã nguồn hiện tại gán HIGH cho mọi Issue PL3. Có hai cách:
- A: Giữ mặc định HIGH nhưng đưa cấu hình Priority mặc định PL3 vào Business Settings để Super Admin thay đổi mà không sửa code.
- B: Giữ cố định HIGH như hành vi hiện tại; chỉ Priority của ISSUE thủ công được chọn từ danh mục.

Không tự động suy ra Priority từ checklist item hoặc mức độ FAIL nếu chưa có ma trận nghiệp vụ được phê duyệt.

## 4. Trạng thái và Workflow không được cấu hình tùy ý

Các trạng thái theo quyết định nghiệp vụ hiện hành gồm:
- OPEN
- IN_PROGRESS
- PENDING_UOA_CONFIRMATION
- CLOSED

UOA không xác nhận thì ISSUE quay về IN_PROGRESS và bắt buộc lý do. Không dùng PENDING_APPROVAL/REJECTED của Apps Script cũ. Thêm/sửa trạng thái hoặc chuyển trạng thái phải có ADR và kiểm tra tác động state machine, báo cáo, audit, email và API.

## 5. Câu hỏi chờ xác nhận

- **D1:** Có đồng ý quản lý Priority bằng danh mục động, khởi tạo bốn mức LOW/MEDIUM/HIGH/CRITICAL không?
- **D2:** Có đồng ý lưu snapshot Priority trên từng ISSUE không?
- **D3:** Với ISSUE tự động từ PL3, chọn A (Priority mặc định HIGH được cấu hình động) hay B (giữ cố định HIGH)?

## 6. Điều kiện trước Migration

Chưa tạo bảng hoặc cột Migration cho đến khi D1–D3 được xác nhận. Chưa thiết kế SLA theo Priority. Chưa cho phép quản trị viên sửa state machine từ màn hình danh mục.
