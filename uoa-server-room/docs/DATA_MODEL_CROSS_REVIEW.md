# DATA MODEL CROSS-REVIEW – PL1–PL5 & COMMON

**Nhánh:** feature/ITSMWEB  
**Trạng thái:** REVIEW FINDINGS – chưa phải Migration/DDL  
**Phạm vi:** Đối chiếu DATA_DICTIONARY.md, ERD_PHYSICAL.md, ERD_DESIGN.md và các ADR hiện hành.

## 1. Đã chỉnh được bằng quyết định đã có

### PL4 Work Report – Assignment cardinality

Data Dictionary quy định `pl4_work_reports.assignment_id NOT NULL`, phù hợp ADR-006 yêu cầu Work Report phải gắn Assignment cụ thể. ERD_PHYSICAL trước đây dùng quan hệ tùy chọn từ Assignment sang Work Report, gây hiểu nhầm. Đã chỉnh thành quan hệ bắt buộc phía Work Report: `PL4_ASSIGNMENTS ||--o{ PL4_WORK_REPORTS`. Composite FK cùng Issue theo ADR-011 vẫn là ràng buộc vật lý bổ sung.

## 2. Các điểm chưa thống nhất / cần quyết định

### 2.1 PL1 – Form response idempotency

DATA_DICTIONARY §3.1 mô tả `source_response_id` là UNIQUE theo nguồn Form, nullable; nhưng phần diễn giải ngay sau đó nói không đặt UNIQUE trực tiếp trên `source_response_id`, mà dùng `idempotency_keys` với `source_system + response_id`. Cần chọn một mô hình nhất quán:
- Khuyến nghị: bỏ `source_response_id` khỏi `pl1_access_requests` nếu idempotency_keys là nguồn chống trùng duy nhất; hoặc giữ cột snapshot nhưng không UNIQUE và dùng unique key chuẩn hóa ở idempotency_keys.
- Không tự áp dụng cho đến khi chủ nghiệp vụ xác nhận.

### 2.2 PL5 – Execution Event và Assignment

`pl5_work_execution_events` có cả `work_request_id` và `assignment_id`; Data Dictionary cho phép `assignment_id NULL`. Nếu chỉ dùng hai FK đơn lẻ, một Execution Event có thể trỏ đến Assignment thuộc Work Request khác.

Khuyến nghị bổ sung:
- UNIQUE `(id, work_request_id)` trên `pl5_work_assignments`.
- Composite FK `pl5_work_execution_events(assignment_id, work_request_id)` → `pl5_work_assignments(id, work_request_id)`.
- Giữ `assignment_id NULL` nếu vẫn cho phép Event hệ thống/Work Request không gắn Assignment; Service xác thực loại Event nào được phép NULL.

Đây là đề xuất mới, chưa được phê duyệt. Cần xác nhận trước khi thêm ADR và cập nhật ERD/Data Dictionary.

### 2.3 PL5 – Work Verification và Assignment/Executor

Hiện `pl5_work_verifications` tham chiếu Work Request và lưu snapshot verifier, không tham chiếu Assignment. Nguồn hiện tại chưa đủ để kết luận Verification bắt buộc phải gắn Assignment cụ thể. Không tự thêm FK hoặc thay đổi nghiệp vụ. Cần quyết định liệu người xác minh độc lập (UOA) chỉ cần liên kết Work Request hay cần lưu Assignment/Execution Event được xác minh.

### 2.4 PL4 ERD – Phần mở rộng chưa nằm trong sơ đồ lõi

ERD_PHYSICAL có các phụ lục PL4-R08 và ADR-011, trong khi sơ đồ ERD lõi ở §6 chưa liệt kê Claim Token và các bảng SLA. Cấu trúc đã có ở các phụ lục; trước Migration nên hợp nhất thành sơ đồ PL4 đầy đủ hoặc giữ rõ ràng đây là addendum. Không có thay đổi nghiệp vụ mới ở điểm này.

### 2.5 COMMON – Audit/Evidence/Email Queue

Data Dictionary quy định các FK chính nhưng một số bảng còn thiếu mô tả đầy đủ kiểu dữ liệu, ON DELETE và index theo từng FK. Cần chuẩn hóa bảng constraint matrix trước Migration. Quy tắc ADR-001/009 về Business Entity và ADR-008 về Email Queue vẫn là nguồn quyết định.

### 2.6 PL3/PL1/PL2 – Chính sách retention và delete

ADR-011 chỉ áp dụng chính sách RESTRICT đã duyệt cho PL4. Không tự suy rộng ON DELETE RESTRICT sang toàn bộ PL1–PL5 nếu chưa có quyết định chung. Data Dictionary có nguyên tắc không hard-delete hồ sơ, nhưng hành vi FK cho từng bảng con vẫn cần được xác định rõ.

## 3. Việc tiếp theo

1. Chủ nghiệp vụ xác nhận mô hình PL1 idempotency.
2. Chủ nghiệp vụ quyết định có áp dụng Composite FK cho PL5 Execution Event hay không.
3. Làm rõ liên kết Verification với Assignment/Execution Event nếu cần.
4. Hoàn thiện constraint matrix cho COMMON, PL1, PL2, PL3, PL5.
5. Hợp nhất ERD PL4 lõi và phụ lục nếu cần để Migration team dùng một sơ đồ đầy đủ.

**Giới hạn:** Tài liệu này chỉ ghi nhận kết quả cross-review và các đề xuất đang chờ phê duyệt. Không tạo Migration/PHP và không thay đổi schema database.
