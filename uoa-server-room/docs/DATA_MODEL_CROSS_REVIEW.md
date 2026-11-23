# DATA MODEL CROSS-REVIEW – PL1–PL5 & COMMON

**Nhánh:** feature/ITSMWEB  
**Trạng thái:** REVIEW FINDINGS – chưa phải Migration/DDL  
**Phạm vi:** Đối chiếu DATA_DICTIONARY.md, ERD_PHYSICAL.md, ERD_DESIGN.md và các ADR hiện hành.

## 1. Đã chốt và đồng bộ theo ADR-012

### PL1 – Form/API idempotency

ĐÃ PHÊ DUYỆT: `idempotency_keys` là cơ chế chuẩn duy nhất; `pl1_access_requests.source_response_id` đã được loại khỏi thiết kế. UNIQUE `(scope, idempotency_key)` và `request_hash` được dùng để chống trùng và phát hiện payload khác khi retry.

### PL5 – Execution Event và Assignment

ĐÃ PHÊ DUYỆT: `pl5_work_assignments` có UNIQUE `(id, work_request_id)`; `pl5_work_execution_events(assignment_id, work_request_id)` dùng Composite FK tham chiếu Assignment thuộc đúng Work Request. `assignment_id` vẫn nullable; Service xác thực loại Event/lifecycle khi NULL. Delete policy PL5 chưa được quyết định.

## 2. Đã chỉnh được bằng quyết định đã có

### PL4 Work Report – Assignment cardinality

Data Dictionary quy định `pl4_work_reports.assignment_id NOT NULL`, phù hợp ADR-006 yêu cầu Work Report phải gắn Assignment cụ thể. ERD_PHYSICAL trước đây dùng quan hệ tùy chọn từ Assignment sang Work Report, gây hiểu nhầm. Đã chỉnh thành quan hệ bắt buộc phía Work Report: `PL4_ASSIGNMENTS ||--o{ PL4_WORK_REPORTS`. Composite FK cùng Issue theo ADR-011 vẫn là ràng buộc vật lý bổ sung.

## 3. Các điểm chưa thống nhất / cần quyết định

### 2.4 PL4 ERD – Phần mở rộng chưa nằm trong sơ đồ lõi

ERD_PHYSICAL có các phụ lục PL4-R08 và ADR-011, trong khi sơ đồ ERD lõi ở §6 chưa liệt kê Claim Token và các bảng SLA. Cấu trúc đã có ở các phụ lục; trước Migration nên hợp nhất thành sơ đồ PL4 đầy đủ hoặc giữ rõ ràng đây là addendum. Không có thay đổi nghiệp vụ mới ở điểm này.

### 2.5 COMMON – Audit/Evidence/Email Queue

Data Dictionary quy định các FK chính nhưng một số bảng còn thiếu mô tả đầy đủ kiểu dữ liệu, ON DELETE và index theo từng FK. Cần chuẩn hóa bảng constraint matrix trước Migration. Quy tắc ADR-001/009 về Business Entity và ADR-008 về Email Queue vẫn là nguồn quyết định.

### 2.6 PL3/PL1/PL2 – Chính sách retention và delete

ADR-011 chỉ áp dụng chính sách RESTRICT đã duyệt cho PL4. Không tự suy rộng ON DELETE RESTRICT sang toàn bộ PL1–PL5 nếu chưa có quyết định chung. Data Dictionary có nguyên tắc không hard-delete hồ sơ, nhưng hành vi FK cho từng bảng con vẫn cần được xác định rõ.

## 4. Việc tiếp theo

1. Làm rõ liên kết Verification với Assignment/Execution Event nếu cần.
2. Hoàn thiện constraint matrix cho COMMON, PL1, PL2, PL3, PL5.
3. Xác định ON DELETE cho PL1/PL2/PL3/PL5; không suy rộng ADR-011 của PL4.
4. Hợp nhất ERD PL4 lõi và phụ lục nếu cần để Migration team dùng một sơ đồ đầy đủ.

**Giới hạn:** Tài liệu ghi nhận quyết định đã phê duyệt và các điểm còn chờ. Không tạo Migration/PHP và không thay đổi database.


## 4. Kết quả rà soát tiếp PL2, PL3 và COMMON

### 4.1 PL2 – OTP và Action Token

- Data Dictionary phân biệt Action Token theo Visit/Purpose và OTP Challenge theo Visit; OTP Attempt phụ thuộc Challenge. ERD thể hiện các quan hệ cha–con chính.
- `pl2_otp_challenges.expires_at` chỉ được gán sau khi email gửi thành công; đây là quy tắc vòng đời, không phải FK/UNIQUE. Cần giữ rõ khi thiết kế Worker.
- Các trường actor snapshot và actor_user_id nullable cần được phản ánh đầy đủ ở DDL; ERD hiện chỉ mô tả một phần cột, không đủ để kiểm tra toàn bộ NULLability.
- Chưa thấy quyết định delete policy theo từng FK PL2 trong các tài liệu được đối chiếu. Không suy rộng ADR-011 PL4.

**Kết luận PL2:** chưa phát hiện mâu thuẫn nghiệp vụ rõ giữa quan hệ lõi Data Dictionary và ERD; còn thiếu ma trận NULL/ON DELETE và constraint theo từng FK trước Migration.

### 4.2 PL3 – Checklist, Inspection, Escalation và Outbox

- Data Dictionary quy định `pl3_escalation_events.email_queue_id` nullable; ERD_PHYSICAL biểu diễn cột FK nhưng không ghi rõ nullable. Cần bổ sung ký hiệu/ghi chú để không bị hiểu thành NOT NULL.
- Quan hệ Email Queue ↔ Escalation Event cần được mô tả chính xác theo vòng đời: Event có thể chưa có queue item trước khi enqueue; khi đã enqueue thì tham chiếu queue item cụ thể. Theo ADR-002/008, retry thuộc cùng queue item, không tạo queue item mới cho mỗi lần thử.
- Data Dictionary dùng `pl3_integration_outbox.inspection_result_id` UNIQUE FK; cần ghi rõ NOT NULL vì Outbox luôn phải có Inspection Result nguồn.
- `pl3_checklist_versions.created_by_user_id` hiện được mô tả FK nhưng chưa ghi rõ NULLability nhất quán với `published_by_user_id`. Đây là hai actor khác nhau: cần quyết định liệu bản ghi Draft có bắt buộc người tạo là Admin có tài khoản hay cho phép actor hệ thống/import.
- Delete policy cho Checklist Version/Version Item đã được mô tả là không sửa/xóa bản đã publish/dùng; nhưng hành vi FK vật lý cho Inspection Result/Outbox/Inspection và Version Item cần được thể hiện tường minh.

**Kết luận PL3:** có một điểm cần sửa tài liệu chắc chắn là thể hiện `email_queue_id` nullable trên ERD. Các điểm còn lại cần hoàn thiện constraint/nullability matrix hoặc xác nhận chính sách actor/delete trước DDL.

### 4.3 COMMON – Email, Audit, Evidence, Idempotency và Export

- `email_queue.business_entity_id` nullable theo Data Dictionary; chỉ NULL với event hệ thống không gắn hồ sơ. ERD cần thể hiện optional FK, không mặc định NOT NULL.
- `system_audit_logs.business_entity_id` nullable theo ADR-001/009; ERD đã thể hiện quan hệ optional từ Business Entity.
- `evidence_files.uploaded_by_user_id` và `entity_evidence_links.linked_by_user_id` nullable theo Data Dictionary. ERD hiện có quan hệ uploader tùy chọn nhưng chưa thể hiện rõ quan hệ `linked_by_user_id` tới USERS.
- `data_exports.requested_by_user_id` đang được mô tả là FK users nhưng chưa có chỉ dẫn rõ delete policy. Người tạo export là dữ liệu truy vết; nên không xóa user vật lý hoặc phải snapshot actor. Đây là khuyến nghị, chưa phải quyết định.
- `idempotency_keys.entity_type/entity_id` và `integration_events.entity_type/entity_id` là tham chiếu metadata đa hình, không có FK theo mô hình hiện tại. Cần ghi rõ trong ERD để không nhầm là FK quan hệ.
- Data Dictionary có `scheduled_job_runs` và `integration_events`, nhưng sơ đồ ERD_PHYSICAL COMMON chưa mô tả đầy đủ tất cả cột/quan hệ của hai bảng; cần bổ sung vào constraint matrix.
- Delete policy cho COMMON phải phân biệt Queue/Attempt, Audit, Evidence, Idempotency, Integration, Job Run và Export. Chưa có ADR chung quyết định ON DELETE cho tất cả; không áp dụng một quy tắc duy nhất theo suy đoán.

**Kết luận COMMON:** cần bổ sung ERD các quan hệ actor còn thiếu và đánh dấu rõ FK nullable/non-FK metadata. Chính sách retention và delete của Audit/Evidence vẫn là điểm chặn production.

## 5. Các điểm cần chủ nghiệp vụ xác nhận / hoàn thiện tiếp

1. PL5 Verification có cần gắn Assignment hoặc Execution Event được xác minh, hay chỉ cần Work Request + verifier snapshot?
2. PL3 Checklist Version: `created_by_user_id` bắt buộc User Admin hay cho phép NULL với tác vụ hệ thống/import?
3. PL3 Escalation Event: xác nhận mỗi Event có tối đa một Email Queue item logic; retry luôn trên cùng queue item (không phát sinh queue item mới).
4. COMMON `data_exports.requested_by_user_id`: giữ FK RESTRICT bằng cách không hard-delete User, hay bổ sung actor snapshot để giữ lịch sử khi User bị xóa?
5. Chốt delete policy theo từng bảng PL1/PL2/PL3/PL5/COMMON; ADR-011 chỉ áp dụng PL4.
6. Chốt retention, archive và privacy cho Audit/Evidence/IP/User-Agent trước production.


### PL5 – Work Verification / Execution Event (ADR-013)

ĐÃ PHÊ DUYỆT: `pl5_work_verifications.execution_event_id` NOT NULL; Composite FK `(execution_event_id, work_request_id)` → `pl5_work_execution_events(id, work_request_id)`. Execution Event có UNIQUE `(id, work_request_id)`. Service xác thực Event Code `SUBMIT_RESULT`. Không đặt UNIQUE `execution_event_id`; không thêm FK trực tiếp đến Assignment. Câu hỏi liên kết Verification đã được giải quyết.
