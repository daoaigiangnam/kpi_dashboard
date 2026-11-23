# DATA MODEL CROSS-REVIEW – PL1–PL5 & COMMON

**Nhánh:** feature/ITSMWEB  
**Trạng thái:** REVIEW FINDINGS – chưa phải Migration/DDL  
**Phạm vi:** Đối chiếu DATA_DICTIONARY.md, ERD_PHYSICAL.md, ERD_DESIGN.md và các ADR hiện hành.

## 1. Đã chốt và đồng bộ theo ADR-012

### PL1 – Form/API idempotency

ĐÃ PHÊ DUYỆT: `idempotency_keys` là cơ chế chuẩn duy nhất; `pl1_access_requests.source_response_id` đã được loại khỏi thiết kế. UNIQUE `(scope, idempotency_key)` và `request_hash` được dùng để chống trùng và phát hiện payload khác khi retry.

### PL5 – Execution Event và Assignment

ĐÃ PHÊ DUYỆT: `pl5_work_assignments` có UNIQUE `(id, work_request_id)`; `pl5_work_execution_events(assignment_id, work_request_id)` dùng Composite FK tham chiếu Assignment thuộc đúng Work Request. `assignment_id` vẫn nullable; Service xác thực loại Event/lifecycle khi NULL. Delete policy PL5 đã được chốt ON DELETE RESTRICT theo ADR-014.

## 2. Đã chỉnh được bằng quyết định đã có

### PL4 Work Report – Assignment cardinality

Data Dictionary quy định `pl4_work_reports.assignment_id NOT NULL`, phù hợp ADR-006 yêu cầu Work Report phải gắn Assignment cụ thể. ERD_PHYSICAL trước đây dùng quan hệ tùy chọn từ Assignment sang Work Report, gây hiểu nhầm. Đã chỉnh thành quan hệ bắt buộc phía Work Report: `PL4_ASSIGNMENTS ||--o{ PL4_WORK_REPORTS`. Composite FK cùng Issue theo ADR-011 vẫn là ràng buộc vật lý bổ sung.

## 3. Các điểm chưa thống nhất / cần quyết định

### 2.4 PL4 ERD – Phần mở rộng chưa nằm trong sơ đồ lõi

ERD_PHYSICAL có các phụ lục PL4-R08 và ADR-011, trong khi sơ đồ ERD lõi ở §6 chưa liệt kê Claim Token và các bảng SLA. Cấu trúc đã có ở các phụ lục; trước Migration nên hợp nhất thành sơ đồ PL4 đầy đủ hoặc giữ rõ ràng đây là addendum. Không có thay đổi nghiệp vụ mới ở điểm này.

### 2.5 COMMON – Audit/Evidence/Email Queue

Data Dictionary quy định các FK chính nhưng một số bảng còn thiếu mô tả đầy đủ kiểu dữ liệu, ON DELETE và index theo từng FK. Cần chuẩn hóa bảng constraint matrix trước Migration. Quy tắc ADR-001/009 về Business Entity và ADR-008 về Email Queue vẫn là nguồn quyết định.

### 2.6 PL3/PL1/PL2 – Chính sách retention và delete

ADR-014 đã phê duyệt ON DELETE RESTRICT cho mọi FK trong CORE, PL1–PL5 và COMMON. Retention cleanup phải thực hiện có kiểm soát theo thứ tự child-first; không dùng CASCADE/SET NULL. Data Dictionary có nguyên tắc không hard-delete hồ sơ, nhưng hành vi FK cho từng bảng con vẫn cần được xác định rõ.

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
- ADR-014 đã chốt ON DELETE RESTRICT cho mọi FK PL2; OTP Attempt phải được dọn trước OTP Challenge nếu đủ điều kiện retention.

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
- ADR-014 chốt ON DELETE RESTRICT cho mọi FK COMMON. Retention vẫn phải phân biệt Queue/Attempt, Audit, Evidence, Idempotency, Integration, Job Run và Export; purge thủ công theo dependency child-first, có xét retention/legal hold.

**Kết luận COMMON:** cần bổ sung ERD các quan hệ actor còn thiếu và đánh dấu rõ FK nullable/non-FK metadata. Chính sách retention và delete của Audit/Evidence vẫn là điểm chặn production.

## 5. Các điểm cần chủ nghiệp vụ xác nhận / hoàn thiện tiếp

1. PL5 Verification có cần gắn Assignment hoặc Execution Event được xác minh, hay chỉ cần Work Request + verifier snapshot?
2. PL3 Checklist Version: `created_by_user_id` bắt buộc User Admin hay cho phép NULL với tác vụ hệ thống/import?
3. PL3 Escalation Event: xác nhận mỗi Event có tối đa một Email Queue item logic; retry luôn trên cùng queue item (không phát sinh queue item mới).
4. COMMON `data_exports.requested_by_user_id`: giữ FK RESTRICT bằng cách không hard-delete User, hay bổ sung actor snapshot để giữ lịch sử khi User bị xóa?
5. Delete policy toàn hệ thống đã chốt tại ADR-014: ON DELETE RESTRICT cho mọi FK CORE, PL1–PL5 và COMMON.
6. Tiếp tục chốt retention, archive và privacy cho Audit/Evidence/IP/User-Agent trước production; ADR-014 không tự đặt retention duration.


### PL5 – Work Verification / Execution Event (ADR-013)

ĐÃ PHÊ DUYỆT: `pl5_work_verifications.execution_event_id` NOT NULL; Composite FK `(execution_event_id, work_request_id)` → `pl5_work_execution_events(id, work_request_id)`. Execution Event có UNIQUE `(id, work_request_id)`. Service xác thực Event Code `SUBMIT_RESULT`. Không đặt UNIQUE `execution_event_id`; không thêm FK trực tiếp đến Assignment. Câu hỏi liên kết Verification đã được giải quyết.


## 6. Global ON DELETE policy – ADR-014

ĐÃ PHÊ DUYỆT: áp dụng `ON DELETE RESTRICT` cho mọi khóa ngoại thực tế thuộc CORE, PL1, PL2, PL3, PL4, PL5 và COMMON. Không dùng `ON DELETE CASCADE` hoặc `ON DELETE SET NULL`. Quyết định áp dụng cả FK nullable; NULLability vẫn do vòng đời nghiệp vụ quyết định.

- User có dữ liệu tham chiếu được DISABLED thay vì hard-delete trong vận hành thông thường.
- Business root, history, Audit, Evidence và dữ liệu cần truy vết không hard-delete trong luồng nghiệp vụ thông thường.
- Token/OTP, Email Queue/Attempts và dữ liệu vận hành có thể được purge khi đủ điều kiện retention/legal hold, nhưng phải xóa child trước parent và theo quy trình có kiểm soát.
- Evidence Link phải được xử lý trước Evidence File; không xóa file còn được hồ sơ retained tham chiếu.
- Các cặp `entity_type/entity_id` của idempotency/integration vẫn là metadata, không phải FK.
- ON UPDATE chưa được ADR-014 quyết định và tiếp tục là điểm mở riêng.

ADR-014 chỉ chốt hành vi FK khi xóa; không quy định thời hạn retention cụ thể, không tạo DDL/Migration/PHP và không thay đổi dữ liệu database.


## 7. Global ON UPDATE policy – ADR-015

ĐÃ PHÊ DUYỆT: áp dụng `ON UPDATE RESTRICT` cho mọi FK thực tế thuộc CORE, PL1–PL5 và COMMON; không dùng `ON UPDATE CASCADE` hoặc `ON UPDATE SET NULL`. Technical PK và khóa cha được FK tham chiếu là bất biến sau khi tạo. Các trường nghiệp vụ không phải khóa vẫn được cập nhật theo quyền/validation/Audit. Sửa sai liên kết phải qua correction/relink workflow được ủy quyền, không đổi PK cha.

Kết hợp ADR-014 và ADR-015, chính sách toàn hệ thống là `ON DELETE RESTRICT + ON UPDATE RESTRICT`. ADR-014 vẫn điều chỉnh ON DELETE; ADR-015 quyết định riêng ON UPDATE. Không tạo DDL/Migration/PHP.
