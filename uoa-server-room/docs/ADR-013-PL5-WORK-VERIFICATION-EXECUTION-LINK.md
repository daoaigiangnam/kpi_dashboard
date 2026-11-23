# ADR-013 – PL5 Work Verification Linked to Execution Submission

**Trạng thái:** APPROVED  
**Phiên bản:** 1.0  
**Ngày phê duyệt:** 23/11/2026  
**Phạm vi:** PL5 Work Verification và Execution Event.

## 1. Quyết định

- Mỗi bản ghi `pl5_work_verifications` phải tham chiếu trực tiếp tới Execution Event `SUBMIT_RESULT` cụ thể mà UOA đang xác minh.
- Bổ sung `execution_event_id BIGINT UNSIGNED NOT NULL` vào `pl5_work_verifications`.
- Bổ sung UNIQUE `(id, work_request_id)` trên `pl5_work_execution_events` để làm đích Composite FK.
- Bổ sung Composite FK `pl5_work_verifications(execution_event_id, work_request_id)` → `pl5_work_execution_events(id, work_request_id)`.
- Giữ FK đơn `work_request_id` → `pl5_work_requests(id)`; Composite FK bảo đảm Execution Event được xác minh thuộc đúng Work Request.
- Workflow Service phải kiểm tra Event được tham chiếu có `event_code = SUBMIT_RESULT`; FK chỉ bảo đảm quan hệ và không tự kiểm tra giá trị event_code.
- Giữ nguyên `decision (PASS/FAIL)`, verifier snapshot và quy tắc FAIL đưa Work Request về IN_PROGRESS, phát sinh REWORK_REQUIRED event.

## 2. Không tự quyết định thêm

- ADR này không đặt UNIQUE `execution_event_id` trên Verification; số lần xác minh cho một lần Submit Result chưa được giới hạn bởi quyết định này.
- Không thêm FK trực tiếp từ Verification tới Assignment. Có thể truy ngược Assignment qua Execution Event nếu Event có assignment_id.
- Không thay đổi workflow, quyền UOA, hay chính sách ON DELETE của PL5. Delete policy PL5 vẫn phải được chuẩn hóa riêng trước Migration.

## 3. Tài liệu đồng bộ

- `DATA_DICTIONARY.md`: thêm execution_event_id NOT NULL, UNIQUE parent key và Composite FK.
- `ERD_PHYSICAL.md`, `ERD_DESIGN.md`: thể hiện Verification gắn đúng Execution Event/Work Request.
- `DATA_MODEL_CROSS_REVIEW.md`: đánh dấu câu hỏi liên kết Verification đã được giải quyết.

**Giới hạn:** Đây là quyết định thiết kế; chưa tạo DDL, Migration hoặc PHP.
