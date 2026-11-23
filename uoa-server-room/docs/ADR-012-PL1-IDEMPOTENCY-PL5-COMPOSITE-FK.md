# ADR-012 – PL1 Idempotency & PL5 Execution Referential Integrity

**Trạng thái:** APPROVED  
**Phiên bản:** 1.0  
**Ngày phê duyệt:** 23/11/2026  
**Phạm vi:** PL1 Access Request idempotency; PL5 Work Assignment và Work Execution Event.

## 1. PL1 – Form/API Idempotency

### Quyết định
- `idempotency_keys` là cơ chế chuẩn duy nhất để chống xử lý trùng Form/API/action callback của PL1.
- Không dùng `pl1_access_requests.source_response_id` làm khóa UNIQUE; loại bỏ cột này khỏi thiết kế vật lý PL1 Access Request.
- Khóa chống trùng dùng UNIQUE `(scope, idempotency_key)`; scope phải định danh rõ phân hệ/nguồn/ngữ cảnh Form để response key ở hai Form/nguồn khác nhau không xung đột.
- `request_hash` phát hiện cùng idempotency key nhưng payload khác. Không trả kết quả cũ nếu hash khác; ghi nhận conflict theo Audit/Workflow.
- `entity_type` / `entity_id` trong idempotency_keys là tham chiếu metadata đa hình, không phải FK. Service ghi tham chiếu kết quả và response snapshot an toàn trong transaction nghiệp vụ.
- Retry cùng scope/key và cùng request_hash trả kết quả đã lưu; request đang xử lý phải được khóa/điều phối an toàn để không tạo hai Access Request.

ADR này không thay đổi quy tắc NEW/CHANGE/REVOKE, Access Record identity hoặc approval workflow. Không mở rộng thành UNIQUE trên email + room.

## 2. PL5 – Execution Event Composite FK

### Quyết định
- Bổ sung UNIQUE `(id, work_request_id)` trên `pl5_work_assignments` làm đích cho FK ghép.
- Bổ sung Composite FK `pl5_work_execution_events(assignment_id, work_request_id)` → `pl5_work_assignments(id, work_request_id)`.
- `assignment_id` tiếp tục nullable theo thiết kế hiện hành. Khi khác NULL, Database bảo đảm Assignment thuộc đúng Work Request. Khi NULL, Event không gắn Assignment; Workflow Service phải kiểm tra Event Code/actor/lifecycle cho phép trường hợp đó.
- Giữ FK đơn `work_request_id` → `pl5_work_requests(id)`; Composite FK bổ sung chứ không thay thế FK gốc.
- Không thay đổi workflow PL5, quyền approve/execute/verify hoặc state transition.

### Delete policy
ADR-012 chỉ chốt toàn vẹn tham chiếu Composite FK cho PL5; không tự áp dụng ON DELETE RESTRICT cho toàn bộ PL5. Chính sách ON DELETE cụ thể của PL5 vẫn phải được chuẩn hóa riêng trước Migration. Không dùng CASCADE nếu có thể làm mất lịch sử nghiệp vụ; delete policy phải được thể hiện tường minh trong constraint matrix.

## 3. Tài liệu cần đồng bộ
- `DATA_DICTIONARY.md`: bỏ `source_response_id`; mô tả idempotency_keys là nguồn duy nhất; thêm UNIQUE và Composite FK PL5.
- `ERD_DESIGN.md`: cập nhật mô tả idempotency PL1 và quan hệ Assignment/Execution Event PL5.
- `ERD_PHYSICAL.md`: cập nhật quan hệ và mục ràng buộc vật lý PL5.
- `DATA_MODEL_CROSS_REVIEW.md`: chuyển hai mục đã quyết định sang APPROVED.

**Giới hạn:** Đây là quyết định thiết kế. Chưa tạo DDL, Migration hoặc PHP.
