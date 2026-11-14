# SRM – Final Schema Decision Register (bản tổng hợp chờ xác nhận)

> Mục tiêu: gom các điểm còn mở từ Schema Review Batch 1, 2, 3 thành một danh sách quyết định duy nhất. Cột “Đề xuất” là khuyến nghị kỹ thuật/nghiệp vụ để chủ dự án xem xét, KHÔNG phải quyết định đã được phê duyệt. Không tạo migration trong giai đoạn này.

## 1. Nguyên tắc phê duyệt

- Chỉ những nội dung được chủ dự án xác nhận mới chuyển trạng thái thành **APPROVED**.
- Các đề xuất trong tài liệu này là phương án mặc định để giảm rủi ro tenant leakage, mất lịch sử và sai lệch trạng thái.
- Nếu cần thay đổi đề xuất, ghi rõ phương án thay thế và tác động tới schema/API/UI/test.
- Một quyết định ảnh hưởng nhiều bảng phải cập nhật đồng bộ Data Dictionary, ERD, Column Schema, State Machine và Migration Blueprint.
- Không tự ý thêm bảng ngoài register 22 bảng nếu chưa có quyết định kiến trúc mới.

Trạng thái ban đầu của mọi mục: **OPEN – chờ chủ dự án xác nhận**, trừ các mục đã được xác nhận và ghi rõ **APPROVED**.

## 2. Nhóm A – Nền tảng, Location, Room, Asset, Checklist (Batch 1)

| ID | Nội dung cần quyết định | Đề xuất của thầy | Ảnh hưởng chính |
|---|---|---|---|
| A01 | Tái sử dụng code location/room/asset sau soft delete? | **APPROVED – Không cho phép tái sử dụng mã sau Soft Delete.** | Unique strategy, form tạo mới, báo cáo |
| A02 | location_type=branch và branch_id | **APPROVED – `branch` bắt buộc có `branch_id`; `head_office`, `data_center`, `independent` phải có `branch_id = NULL`. `branch_id` phải thuộc cùng `customer_id`.** | CHECK/service validation, composite FK |
| A03 | Primary contact trong một room | **APPROVED – Mỗi Server Room có tối đa một Contact đang hoạt động được đánh dấu `is_primary = 1`. Có thể có nhiều Contact khác; thay đổi Primary Contact phải thực hiện trong transaction; Contact cũ được giữ lịch sử, không xóa dữ liệu.** | Unique active strategy, transaction |
| A04 | Contact/access person user_id liên kết loại user nào? | **APPROVED – Cho phép Internal User được phân quyền phụ trách tenant và Customer User thuộc chính tenant đó. User phải có quan hệ hợp lệ với `customer_id` của Server Room; kiểm tra quyền truy cập bắt buộc tại Service/Policy.** | User ownership, policies, composite FK/test |
| A05 | Access level | **APPROVED – Chỉ có hai mức: `permanent` (được phép ra vào thường xuyên) và `supervised` (chỉ được vào khi có người giám sát). Với `supervised`, bắt buộc xác định người giám sát hoặc quy trình giám sát cụ thể; không cấp quyền nếu không có cơ chế giám sát thực hiện được.** | Validation, UI, access workflow |
| A06 | Access Person thu hồi | **APPROVED – Bổ sung `revoked_by`, `revoked_at`, `revoke_reason`. Không hard delete Access Person đã phát sinh lịch sử; khi thu hồi phải cập nhật trạng thái và ghi nhận người thực hiện, thời điểm, lý do để phục vụ kiểm tra/Audit.** | Column schema, audit, state transition |
| A07 | Checklist response_type có multi_select? | Phiên bản đầu KHÔNG hỗ trợ multi_select; chỉ bật khi có use case và cấu trúc result rõ. | Checklist items, inspection results |
| A08 | Clone checklist version | Clone toàn bộ item sang version row mới; item có ID mới; version đã approved bất biến. | Template service, FK, snapshot |
| A09 | Một version hiệu lực cho mỗi code? | Chỉ một version active/effective tại một thời điểm; không cho khoảng hiệu lực chồng lấn. | Effective date validation, unique strategy |
| A10 | Rack và U-position | Giữ Rack là asset category=rack; thiết bị có thể không gắn Rack; U collision kiểm tra trong transaction và khóa Rack. | Self-FK, asset validation, concurrency test |
| A11 | Phân biệt archived/retired/inactive/deleted_at | Status là vòng đời nghiệp vụ; deleted_at chỉ là soft delete kỹ thuật. Định nghĩa status riêng cho từng bảng, không dùng thay thế nhau. | State model, filters, retention |

## 3. Nhóm B – Access Log, Inspection, Incident, Maintenance, Movement (Batch 2)

| ID | Nội dung cần quyết định | Đề xuất của thầy | Ảnh hưởng chính |
|---|---|---|---|
| B01 | Access Log entry_at | entry_at là giờ check-in thực tế; bổ sung scheduled_entry_at nếu có đăng ký trước. | Access log columns, UI, report |
| B02 | Visitor không có access_person | Cho phép access_person_id NULL nhưng bắt buộc visitor_name, purpose và approver/exception authorization; ghi exception_reason khi đi theo ngoại lệ. | Validation, access policy, audit |
| B03 | Inspection review_return | Dùng correction_required, không quay về draft; giữ nguyên kết quả đã submit và tạo correction revision/audit. | Status, revision behavior, UI |
| B04 | Inspection result multi_select/boolean | Không hỗ trợ multi_select ở phiên bản đầu; bỏ boolean_value, dùng result cho yes/no; numeric/text/date dùng cột tương ứng. | Result schema, validation |
| B05 | Incident status | Chốt open/in_progress/resolved/closed/cancelled; không dùng investigating hoặc verified như status riêng. verified_by/verified_at lưu bước xác minh trước close. | Status allowlist, workflow, reports |
| B06 | Incident pivot asset scope | Asset phải cùng customer; ưu tiên cho phép asset ở room khác trong cùng tenant nếu sự cố liên phòng, nhưng ghi rõ relationship/impact và xác thực tenant. | Pivot validation, UI |
| B07 | Incident Action verification | completed bắt buộc completed_at/completion_note; verified bắt buộc verified_by/verified_at/verification_note. Người xác minh phải khác người hoàn tất nếu workflow yêu cầu SoD; đề xuất mặc định khác người. | Action validation, SoD policy |
| B08 | Emergency Maintenance | Chưa triển khai emergency bypass ở phiên bản đầu; mọi công việc đi qua approval chuẩn. Có thể bổ sung sau với quyền riêng và hậu kiểm. | Maintenance status/columns, approval |
| B09 | Maintenance asset scope | Asset phải cùng customer; cho phép ở room khác chỉ khi request ghi rõ phạm vi đa phòng. Nếu request chỉ bảo trì một room, asset phải ở room đó tại thời điểm thực hiện. | Pivot service validation |
| B10 | Maintenance personnel JSON | Chỉ lưu snapshot tối thiểu: tên, đơn vị/vendor, vai trò; không lưu số giấy tờ/PII nhạy cảm nếu không có căn cứ nghiệp vụ. | JSON contract, privacy |
| B11 | Movement timestamps | Tách requested_at và executed_at; movement_at không dùng chung hai nghĩa. | Column schema, SLA/report |
| B12 | Movement status/lifecycle | Bắt buộc status draft/pending_approval/approved/rejected/completed/cancelled; approved_by/approved_at khi approved; performed_by/executed_at khi completed. | State machine, migration |
| B13 | Movement asset_id NULL | Cho phép thiết bị ngoài danh mục check_in/check_out; bắt buộc snapshot tên/serial/tag phù hợp. Sau check_in, người quản trị quyết định tạo asset record và liên kết bằng quy trình riêng. | Asset lifecycle, reconciliation |
| B14 | Movement Rack/U snapshot | Bổ sung source/destination rack asset ID và U position có cấu trúc; text location chỉ dùng ghi chú hiển thị. | Movement columns, FK/self-FK |
| B15 | Check-out asset state | Check-out đưa asset sang trạng thái ngoài phòng/checked_out, không xóa asset; check_in lại bằng phiếu mới. Cần thêm allowlist trạng thái asset tương ứng. | Asset status, movement transaction |
| B16 | Movement source validation | Khi relocate/check_out, asset phải ở from room; execute cập nhật asset và movement trong một transaction, khóa asset để chống double execution. | Service, locking, tests |

## 4. Nhóm C – Environment, Approval, File, Audit, Pivot và Customer Access (Batch 3)

| ID | Nội dung cần quyết định | Đề xuất của thầy | Ảnh hưởng chính |
|---|---|---|---|
| C01 | Environment timezone | Lưu timestamp theo UTC; hiển thị theo timezone cấu hình customer. | App config, reports, ingestion |
| C02 | Environment source security | Device/import phải xác thực nguồn, có idempotency key và chống replay; source_reference không thay thế xác thực. | API ingestion, security, indexes |
| C03 | Environment retention | Chốt retention theo loại dữ liệu và tần suất; hỗ trợ archive khi log tăng lớn. | Operations, storage, indexes |
| C04 | Approval ANY/ALL | Mặc định ANY cho nhiều approver cùng một step; ALL chỉ dùng khi workflow chỉ định rõ. | Approval engine, tests |
| C05 | Approval group | Resolve group thành danh sách approver cụ thể tại thời điểm giao; lưu snapshot người được giao để membership thay đổi sau này không làm đổi lịch sử. | Approval schema/service |
| C06 | Approval reassignment | assigned_by được bổ sung; mọi lần giao lại phải lưu old/new approver, actor, time, reason trong audit. Chưa thêm approval_events table ở bản đầu. | Approval, audit |
| C07 | Approval self-approval | Cấm self-approval ở mọi workflow trong phiên bản đầu. | Authorization, SoD tests |
| C08 | Attachment security | Private storage; MIME xác minh bằng nội dung; giới hạn dung lượng/loại; malware scan trước download; không public file_path. | Storage, upload/download service |
| C09 | Attachment retention | Chốt retention và legal hold trước triển khai xóa file; không cascade delete tự động. | Storage lifecycle, scheduler |
| C10 | Audit immutability | Append-only ở app; quyền DB hạn chế; archive bảo vệ chống sửa nếu yêu cầu compliance. Không tuyên bố bất biến tuyệt đối chỉ bằng UI. | DB privilege, archive, audit service |
| C11 | Pivot column naming | Giữ tên theo Data Dictionary: incident_assets.impact_note và maintenance_assets.work_note; không đổi thành relationship_note nếu không có lý do nghiệp vụ. | Column schema, API |
| C12 | Pivot asset snapshot | Lưu snapshot asset_tag và asset_name tại thời điểm liên kết nếu báo cáo lịch sử cần giữ nguyên tên/mã; đề xuất có snapshot tối thiểu. | Pivot columns, audit |
| C13 | Pivot room scope | Tenant bắt buộc giống nhau; room scope được kiểm tra theo từng nghiệp vụ: sự cố có thể đa phòng; maintenance một phòng mặc định cùng phòng, đa phòng phải khai báo rõ. | Service validation, UI |
| C14 | Customer permission revoke | Giữ lịch sử grant/revoke bằng revoked_at/revoked_by; cấp lại tạo bản ghi mới. Cần active unique strategy bằng generated column hoặc transaction/locking sau khi kiểm tra MySQL. | Permission schema, indexes |
| C15 | Room assignment uniqueness | Một assignment active duy nhất cho (customer,user,room); revoke giữ lịch sử; cấp lại tạo row mới. Ưu tiên generated active key nếu hỗ trợ đúng trên MySQL hiện hữu, nếu không dùng transaction/locking. | Unique strategy, concurrency tests |
| C16 | Customer user tenant integrity | Bắt buộc account_type=customer và service_customer_id=customer_id. Nếu không thể tạo candidate key trên users hiện hữu thì service check + negative tests. | Existing schema, FK, policy |
| C17 | Customer IT Admin | Chỉ cấp/revoke permission và room assignment cho user/room cùng tenant; permission grant phải nằm trong allowlist SRM. | Authorization, audit |
| C18 | User disable/tenant change | Khi customer user bị disable hoặc chuyển tenant, mọi quyền/assignment SRM phải bị vô hiệu hóa/thu hồi theo transaction và audit. | User lifecycle integration |

## 5. Nhóm D – Quyết định kỹ thuật áp dụng xuyên suốt 22 bảng

Các nguyên tắc dưới đây được đề xuất làm baseline kỹ thuật; vẫn phải kiểm tra schema thực tế trước khi thực thi.

1. **Tenant isolation:** mọi quan hệ parent-child có tenant phải dùng composite FK (id, customer_id) khi khả thi; nếu không, service-layer tenant check trong transaction và test âm tính.
2. **Candidate keys:** bổ sung UNIQUE (id, customer_id) hoặc (id, customer_id, server_room_id) ở bảng SRM cần được tham chiếu ghép. Với bảng hiện hữu, kiểm tra duplicate/index trước khi thêm.
3. **Actor authorization:** FK users.id chỉ xác nhận user tồn tại; phải kiểm tra account_type, tenant, active status, permission và assignment scope.
4. **Polymorphic allowlist:** approvals, attachments, audit chỉ nhận alias cố định; không nhận class/type tùy ý từ client.
5. **Delete policy:** hồ sơ đã submit/approved/completed/closed không hard delete; dùng cancel/revoke/archive và retention. Pivot history phải được audit.
6. **State machine:** status chỉ đổi qua action hợp lệ trong service; không cho client update status trực tiếp.
7. **Concurrency:** Rack U allocation, movement execute, active permission/assignment và approval decision cần transaction + row locking/unique constraint phù hợp.
8. **Snapshot:** dữ liệu mô tả tại thời điểm phát sinh (inspection item, visitor, asset, approver) phải được snapshot khi cần giữ lịch sử.
9. **Unique + soft delete:** unique code không tự bỏ qua deleted_at; chốt chiến lược không tái sử dụng code trước migration.
10. **Test baseline:** tenant mismatch, permission escalation, cross-room references, invalid transitions, duplicate active assignment, race condition, unauthorized file download, polymorphic spoofing.

## 6. Danh sách quyết định ưu tiên phải chốt trước Final Schema

Để không phải duyệt 40+ mục cùng lúc, ưu tiên theo thứ tự ảnh hưởng cấu trúc:

### P0 – Ảnh hưởng trực tiếp số cột, FK, trạng thái và dữ liệu
- A02, A03, A04, A06, A07, A09, A10
- B01, B03, B04, B05, B08, B11, B12, B13, B14, B15
- C04, C05, C11, C12, C14, C15, C16

### P1 – Ảnh hưởng service, security, retention và vận hành
- B02, B06, B07, B09, B10, B16
- C01, C02, C03, C06, C07, C08, C09, C10, C13, C17, C18
- A01, A05, A08, A11

## 7. Trạng thái phê duyệt

| Nhóm | Tổng mục | Đã xác nhận | Còn mở |
|---|---:|---:|---:|
| A – Batch 1 | 11 | 6 | 5 |
| B – Batch 2 | 16 | 0 | 16 |
| C – Batch 3 | 18 | 0 | 18 |
| D – Baseline kỹ thuật | 10 | 0 | 10 |

> Số lượng mục trong bảng A/B/C là mã quyết định độc lập; nhóm D là nguyên tắc xuyên suốt, không phải thay thế các quyết định chi tiết.

## 8. Gate trước khi tạo Migration

- [ ] Chủ dự án xác nhận các quyết định P0.
- [ ] Chủ dự án xác nhận các quyết định P1 hoặc chấp nhận ghi nhận là policy triển khai.
- [ ] Cập nhật Data Dictionary, ERD, Column Schema và State Machine theo quyết định đã duyệt.
- [ ] Kiểm tra schema MySQL thực tế: engine, charset/collation, FK, unique/index, dữ liệu duplicate.
- [ ] Chốt migration order và rollback dependency.
- [ ] Chốt authorization/policy và test plan.
- [ ] Chỉ sau khi hoàn tất mới đánh dấu Final Schema Approved và bắt đầu Laravel migrations.

## 9. Tài liệu nguồn

- [10 – Schema Review Batch 1](./10-schema-review-batch-1-draft.md)
- [11 – Schema Review Batch 2](./11-schema-review-batch-2-draft.md)
- [12 – Schema Review Batch 3](./12-schema-review-batch-3-draft.md)
- [02 – Data Dictionary](./02-data-dictionary-draft.md)
- [03 – ERD and Relationships](./03-erd-and-relationships-draft.md)
- [05 – Data Integrity and State Machines](./05-data-integrity-and-state-machines-draft.md)
- [06 – Migration Blueprint](./06-migration-blueprint-draft.md)



## Lịch sử xác nhận

- **A06 – APPROVED (2026-11-14):** Bổ sung `revoked_by`, `revoked_at`, `revoke_reason`; không hard delete Access Person đã có lịch sử; thu hồi phải ghi trạng thái, người thực hiện, thời điểm và lý do.


- **A05 – APPROVED (2026-11-14):** Chỉ có `permanent` và `supervised`; mức `supervised` bắt buộc có người giám sát hoặc quy trình giám sát cụ thể.


- **A04 – APPROVED (2026-11-14):** Cho phép Internal User được phân quyền phụ trách tenant và Customer User thuộc chính tenant đó; phải xác minh quan hệ User–`customer_id` và kiểm tra quyền tại Service/Policy.


- **A03 – APPROVED (2026-11-14):** Mỗi Server Room có tối đa một Contact đang hoạt động được đánh dấu `is_primary = 1`; thay đổi Primary Contact phải thực hiện trong transaction; Contact cũ được giữ lịch sử.


- **A02 – APPROVED (2026-11-14):** `branch` bắt buộc có `branch_id`; `head_office`, `data_center`, `independent` phải có `branch_id = NULL`; `branch_id` phải thuộc cùng `customer_id`.
