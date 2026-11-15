# SRM – Final Schema Decision Register (bản tổng hợp chờ xác nhận)

> Mục tiêu: gom các điểm còn mở từ Schema Review Batch 1, 2, 3 thành một danh sách quyết định duy nhất. Cột “Đề xuất” là khuyến nghị kỹ thuật/nghiệp vụ để chủ dự án xem xét, KHÔNG phải quyết định đã được phê duyệt. Không tạo migration trong giai đoạn này.

## 1. Nguyên tắc phê duyệt

- Chỉ những nội dung được chủ dự án xác nhận mới chuyển trạng thái thành **APPROVED**.
- Các đề xuất trong tài liệu này là phương án mặc định để giảm rủi ro tenant leakage, mất lịch sử và sai lệch trạng thái.
- Nếu cần thay đổi đề xuất, ghi rõ phương án thay thế và tác động tới schema/API/UI/test.
- Một quyết định ảnh hưởng nhiều bảng phải cập nhật đồng bộ Data Dictionary, ERD, Column Schema, State Machine và Migration Blueprint.
- Không tự ý thêm bảng ngoài register hiện hành (23 bảng) nếu chưa có quyết định kiến trúc mới.

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
| A07 | Checklist response_type có multi_select? | **APPROVED – Phiên bản đầu tiên KHÔNG hỗ trợ `multi_select`. Chỉ xem xét bổ sung khi có use case và cấu trúc lưu kết quả/đánh giá rõ ràng.** | Checklist items, inspection results |
| A08 | Clone checklist version | **APPROVED – Khi tạo version mới từ Checklist Template đã duyệt, hệ thống clone toàn bộ Checklist Items sang version mới; mỗi Item có ID mới; version đã `approved` bất biến, không được sửa trực tiếp. Mọi điều chỉnh phải tạo version mới.** | Template service, FK, snapshot |
| A09 | Một version hiệu lực cho mỗi code? | **APPROVED – Với cùng `customer_id` và `code`, chỉ có một Version đang hiệu lực tại một thời điểm; không cho phép các khoảng thời gian hiệu lực chồng lấn. Version cũ được giữ để tra cứu lịch sử; khi Version mới có hiệu lực, Version cũ kết thúc hiệu lực theo thời gian cấu hình.** | Effective date validation, unique strategy |
| A10 | Rack và U-position | **APPROVED – Giữ Rack là Asset có `category=rack`; thiết bị có thể không gắn Rack. Kiểm tra trùng U-Position phải thực hiện trong Database Transaction và khóa Rack để bảo đảm an toàn đồng thời.** | Self-FK, asset validation, concurrency test |
| A11 | Phân biệt archived/retired/inactive/deleted_at | **APPROVED – `archived`, `retired`, `inactive` là các trạng thái nghiệp vụ có ý nghĩa riêng; `deleted_at` chỉ biểu thị Soft Delete kỹ thuật. Mỗi bảng phải định nghĩa trạng thái phù hợp; không dùng các trạng thái này thay thế lẫn nhau.** | State model, filters, retention |

## 3. Nhóm B – Access Log, Inspection, Incident, Maintenance, Movement (Batch 2)

| ID | Nội dung cần quyết định | Đề xuất của thầy | Ảnh hưởng chính |
|---|---|---|---|
| B01 | Access Log entry_at | **APPROVED – `entry_at` là thời điểm check-in thực tế; bổ sung `scheduled_entry_at` để lưu thời điểm đăng ký trước (nếu có). Không dùng giờ đăng ký thay cho giờ thực tế; báo cáo có thể đối chiếu hai thời điểm.** | Access log columns, UI, report |
| B02 | Visitor không có access_person | **APPROVED – Cho phép `access_person_id = NULL`, nhưng bắt buộc có `visitor_name`, `purpose` và người phê duyệt hoặc quyền ngoại lệ hợp lệ. Nếu đi theo ngoại lệ, bắt buộc ghi `exception_reason`; toàn bộ thao tác phải được Audit.** | Validation, access policy, audit |
| B03 | Inspection review_return | **APPROVED – Khi Review yêu cầu bổ sung/chỉnh sửa, chuyển Inspection sang `correction_required`, không quay về `draft`. Giữ nguyên kết quả đã Submit; mọi chỉnh sửa tạo Correction Revision mới. Ghi nhận người yêu cầu, thời điểm và lý do; bảo toàn lịch sử Submit/Review để Audit.** | Status, revision behavior, UI |
| B04 | Inspection result multi_select/boolean | **APPROVED – Phiên bản đầu không hỗ trợ `multi_select` và không dùng `boolean_value`. Kết quả Yes/No lưu trong `result`; dữ liệu Numeric, Text, Date lưu ở cột tương ứng. Mỗi `response_type` phải có validation riêng và không chấp nhận dữ liệu sai kiểu.** | Result schema, validation |
| B05 | Incident status | **APPROVED – Incident dùng các trạng thái `open`, `in_progress`, `resolved`, `closed`, `cancelled`. Không dùng `investigating` hoặc `verified` làm status riêng. `verified_by` và `verified_at` ghi nhận bước xác minh; chỉ chuyển sang `closed` sau khi hoàn tất xác minh.** | Status allowlist, workflow, reports |
| B06 | Incident pivot asset scope | **APPROVED – Một Incident có thể liên kết nhiều Asset qua `server_room_incident_assets`. Các Asset phải cùng `customer_id` với Incident; được phép ở Server Room khác nhau trong cùng tenant khi sự cố có phạm vi liên phòng. Phải ghi rõ vai trò Asset trong sự cố (ví dụ `affected`, `related`, `cause`) và Service phải kiểm tra tenant cùng quyền truy cập trước khi liên kết.** | Pivot validation, UI |
| B07 | Incident Action verification | **APPROVED – Khi Action chuyển `completed`, bắt buộc có `completed_at` và `completion_note`. Khi chuyển `verified`, bắt buộc có `verified_by`, `verified_at`, `verification_note`. Người xác minh phải khác người hoàn tất (SoD); không cho `verified` nếu thiếu thông tin xác minh.** | Action validation, SoD policy |
| B08 | Emergency Maintenance | **APPROVED – Phiên bản SRM đầu tiên chưa triển khai Emergency Bypass; mọi Maintenance Request phải đi qua Approval Workflow tiêu chuẩn, không được tự ý bỏ qua bước phê duyệt. Có thể bổ sung Emergency Maintenance sau với quyền riêng, lý do khẩn cấp bắt buộc, ghi nhận người thực hiện/thời điểm và hậu kiểm/phê duyệt bổ sung.** | Maintenance status/columns, approval |
| B09 | Maintenance asset scope | **APPROVED – Maintenance Request liên kết Asset qua `server_room_maintenance_assets`. Asset phải cùng `customer_id` với Request. Nếu Request chỉ áp dụng cho một Server Room, Asset phải thuộc Room đó tại thời điểm thực hiện; nếu bảo trì nhiều Room, Request phải khai báo rõ phạm vi đa phòng. Khi thực hiện, Service phải kiểm tra lại vị trí thực tế của Asset.** | Pivot service validation |
| B10 | Maintenance personnel JSON | **APPROVED – Lưu danh sách người tham gia dưới dạng JSON snapshot, chỉ gồm thông tin tối thiểu: `name`, `organization` (đơn vị/vendor), `role` (vai trò). Không lưu CCCD, hộ chiếu hoặc dữ liệu cá nhân nhạy cảm nếu không có căn cứ nghiệp vụ. Snapshot giữ nguyên thông tin tại thời điểm bảo trì.** | JSON contract, privacy |
| B11 | Movement timestamps | **APPROVED – Tách `requested_at` (thời điểm tạo yêu cầu di chuyển) và `executed_at` (thời điểm thực tế hoàn tất di chuyển). Không dùng một trường `movement_at` cho cả hai ý nghĩa; báo cáo/SLA phải dựa trên đúng mốc thời gian.** | Column schema, SLA/report |
| B12 | Movement status/lifecycle | **APPROVED – Movement dùng các trạng thái `draft`, `pending_approval`, `approved`, `rejected`, `completed`, `cancelled`. Khi `approved` bắt buộc có `approved_by`, `approved_at`; khi `completed` bắt buộc có `performed_by`, `executed_at`. Không cho chuyển trực tiếp `draft` → `completed`; chỉ cập nhật vị trí Asset khi Movement thực hiện thành công. Mọi chuyển trạng thái phải được Audit.** | State machine, migration |
| B13 | Movement asset_id NULL | **APPROVED – Cho phép `asset_id = NULL` đối với Movement loại `check_in` và `check_out` cho thiết bị chưa có Asset Record. Bắt buộc lưu Snapshot gồm tên thiết bị, Serial Number và Asset Tag (nếu có). Sau `check_in`, quản trị viên quyết định có tạo Asset Record chính thức hay không; nếu tạo, liên kết bằng quy trình riêng. Không tự động tạo Asset Record khi check-in.** | Asset lifecycle, reconciliation |
| B14 | Movement Rack/U snapshot | **APPROVED – Lưu vị trí Rack/U nguồn và đích bằng các trường có cấu trúc: `from_rack_asset_id`, `from_rack_u_start`, `to_rack_asset_id`, `to_rack_u_start`. Rack nguồn/đích phải thuộc đúng Server Room tương ứng. Thiết bị không gắn Rack được phép để các trường Rack/U tương ứng `NULL`. Lưu Snapshot trước và sau di chuyển; trường Text chỉ dùng làm ghi chú, không thay thế dữ liệu vị trí kỹ thuật có cấu trúc.** | Movement schema, validation |
| B15 | Check-out asset state | **APPROVED – Không xóa Asset Record khi check-out. Cập nhật trạng thái Asset sang `checked_out` (hoặc trạng thái tương đương trong allowlist); tạo Movement `check_out` lưu thời gian, người thực hiện và snapshot thiết bị. Khi thiết bị quay lại, tạo Movement `check_in` mới để giữ nguyên lịch sử; không ghi đè Movement cũ.** | Asset status, movement transaction |
| B16 | Movement source validation | **APPROVED – Khi thực hiện `relocate` hoặc `check_out`, Asset phải đang thuộc `from_server_room_id`. Execute Movement phải cập nhật Movement và Asset trong cùng một Database Transaction; khóa Asset bằng row-level lock (`SELECT ... FOR UPDATE`) để ngăn hai yêu cầu đồng thời thực hiện trên cùng Asset. Nếu kiểm tra nguồn không hợp lệ hoặc có xung đột, rollback toàn bộ transaction; không cập nhật một phần.** | Service, locking, tests |

## 4. Nhóm C – Environment, Approval, File, Audit, Pivot và Customer Access (Batch 3)

| ID | Nội dung cần quyết định | Đề xuất của thầy | Ảnh hưởng chính |
|---|---|---|---|
| C01 | Environment timezone | **APPROVED – Lưu timestamp theo UTC. Hiển thị, tìm kiếm và lọc theo timezone cấu hình của customer; backend quy đổi khoảng thời gian người dùng nhập từ timezone customer sang UTC trước khi truy vấn và quy đổi kết quả về timezone customer khi trả giao diện.** | App config, reports, search, ingestion |
| C02 | Environment source security | **APPROVED – Device/import phải xác thực nguồn gửi; có idempotency key để chống ghi trùng khi retry và cơ chế chống replay; `source_reference` chỉ định danh nguồn, không thay thế xác thực. Manual entry phải ghi nhận actor người dùng.** | API ingestion, security, indexes |
| C03 | Environment retention | **APPROVED – Chính sách retention được xác định theo loại dữ liệu và tần suất ghi nhận; hỗ trợ archive dữ liệu cũ khi khối lượng tăng. Không tự động xóa dữ liệu trước khi có retention policy được phê duyệt; chỉ quyết định partition khi có số liệu tải thực tế.** | Operations, storage, indexes |
| C04 | Approval hierarchy and quorum | **APPROVED – Phê duyệt tuần tự theo cấp; tại mỗi cấp tất cả approver phải APPROVE mới chuyển cấp. Một REJECT làm dừng workflow và từ chối yêu cầu.** | Approval engine, state machine, tests |
| C05 | Approver snapshot timing | **APPROVED – Resolve group/role thành danh sách user cụ thể và snapshot khi từng cấp được kích hoạt; thay đổi membership sau snapshot không tự thay đổi danh sách cấp đang xử lý. Cấp tiếp theo được snapshot khi đến lượt.** | Approval schema/service, audit |
| C06 | Delegation and reassignment | **APPROVED – Hỗ trợ cả ủy quyền và thay thế. Delegation cần được người có thẩm quyền chấp thuận, có phạm vi/thời hạn và lưu principal/acting approver. Reassignment do người có thẩm quyền workflow thực hiện, lưu old/new approver, actor, time, reason. Mọi thao tác phải Audit và không làm giảm điều kiện ALL.** | Approval, audit, authorization |
| C07 | Approval self-approval | **APPROVED – Người tạo yêu cầu không được tự phê duyệt yêu cầu đó. Nếu requester xuất hiện trong danh sách approver đã resolve, loại khỏi danh sách hiệu lực và ghi nhận lý do. Nếu cấp không còn approver hợp lệ, chặn kích hoạt và yêu cầu cấu hình lại; không tự bỏ qua cấp. Delegation không được dùng để vượt SoD; acting approver không hợp lệ phải được xử lý bằng reassignment có thẩm quyền, vẫn giữ ALL.** | Authorization, SoD tests, approval activation |
| C08 | Attachment security | **APPROVED – File lưu Private Storage; xác minh MIME bằng nội dung thực tế; áp dụng allowlist loại file và giới hạn dung lượng có cấu hình; file mới upload ở trạng thái quarantine/pending, chỉ cho preview/download khi malware scan trả kết quả clean; infected/error/pending đều bị chặn truy cập; không public `file_path`. Kiểm tra authorization và tenant ở mọi thao tác.** | Storage, upload/download service, scan state |
| C09 | Attachment retention | **APPROVED – Không tự động xóa file khi parent Archived/Soft Deleted; retention cấu hình theo loại hồ sơ/tài liệu và policy nghiệp vụ; hỗ trợ Legal Hold; Legal Hold còn hiệu lực thì không xóa dù đã hết hạn; chỉ xóa vật lý sau khi hết hạn, không còn Legal Hold, kiểm tra tham chiếu và ghi Audit đầy đủ actor/thời điểm/kết quả.** | Storage lifecycle, scheduler, legal hold, audit |
| C10 | Audit immutability | **APPROVED – 2026-11-15 –** Audit Log append-only; ứng dụng không cung cấp chức năng UPDATE/DELETE audit rows; tài khoản DB của ứng dụng không có quyền UPDATE/DELETE trên bảng Audit Log; hỗ trợ archive riêng với quyền sửa/xóa bị hạn chế khi yêu cầu compliance; ghi actor, UTC timestamp, action, target object, result và trace cần thiết. Không tuyên bố bất biến tuyệt đối trước DBA/quản trị hạ tầng chỉ bằng các kiểm soát này. | DB privilege, archive, audit service, security tests |
| C11 | Pivot column naming | **APPROVED – 2026-11-15 –** Giữ nguyên `server_room_incident_assets.impact_note` và `server_room_maintenance_assets.work_note`; không đổi thành tên chung `relationship_note`. | Column schema, API |
| C12 | Pivot asset snapshot | **APPROVED – 2026-11-15 –** Lưu cả `asset_tag` và `asset_name` snapshot trên pivot tại thời điểm Asset được liên kết với Incident/Maintenance để bảo toàn thông tin lịch sử. | Pivot columns, reports, audit |
| C13 | Pivot room scope | **APPROVED – 2026-11-15 –** Incident được liên kết Asset ở nhiều Server Room trong cùng tenant; Maintenance mặc định cùng một Room, đa phòng phải khai báo rõ; Service kiểm tra tenant và room scope trước khi liên kết. | Service validation, UI, tests |
| C14 | Customer permission revoke | **APPROVED – 2026-11-15 –** Thu hồi bằng `revoked_at`/`revoked_by`, không xóa bản ghi; cấp lại tạo row mới; chỉ một grant active cho cùng `(customer_id, user_id, permission_code)`. Cơ chế unique active phải chọn sau khi kiểm tra MySQL thực tế. | Permission schema, indexes, concurrency tests |
| C15 | Room assignment uniqueness | **APPROVED – 2026-11-15 –** Mỗi `(customer_id, user_id, server_room_id)` chỉ có một assignment active; revoke bằng `revoked_at`/`revoked_by`, cấp lại tạo row mới. Cơ chế unique active chốt sau khi xác minh MySQL thực tế. | Unique strategy, concurrency tests |
| C16 | Customer user tenant integrity | **APPROVED – 2026-11-15 –** Customer User bắt buộc `account_type=customer` và `users.service_customer_id=customer_id` của dữ liệu SRM; nếu schema users hiện hữu không hỗ trợ composite FK/candidate key phù hợp, bắt buộc Service tenant check và negative tests. | Existing schema, FK, policy, tenant tests |
| C17 | Customer IT Admin | **APPROVED – 2026-11-15 –** Chỉ cấp/revoke permission và room assignment cho Customer User/Server Room cùng tenant; permission grant phải nằm trong allowlist SRM; Audit đầy đủ. | Authorization, audit, tenant tests |
| C18 | User disable/tenant change | **APPROVED – 2026-11-15 –** Disabled/inactive user bị từ chối truy cập SRM; giữ lịch sử permission/assignment. Khi đổi tenant, revoke quyền/assignment tenant cũ, không tự chuyển quyền; transaction nhất quán và Audit đầy đủ. | User lifecycle integration, transaction, audit, negative tests |

## 5. Nhóm D – Quyết định kỹ thuật áp dụng xuyên suốt 23 bảng

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
- C11, C12, C14, C15, C16

### P1 – Ảnh hưởng service, security, retention và vận hành
- B02, B06, B07, B09, B10, B16
- C07, C08, C09, C10, C13, C17, C18
- A01, A05, A08, A11

## 7. Trạng thái phê duyệt

| Nhóm | Tổng mục | Đã xác nhận | Còn mở |
|---|---:|---:|---:|
| A – Batch 1 | 11 | 11 | 0 |
| B – Batch 2 | 16 | 16 | 0 |
| C – Batch 3 | 18 | 6 | 12 |
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

- **B14 – APPROVED (2026-11-15):** Movement lưu Rack/U nguồn và đích bằng trường có cấu trúc; Rack phải thuộc đúng Server Room; thiết bị không gắn Rack được phép NULL; lưu Snapshot trước/sau; Text chỉ là ghi chú.


- **B13 – APPROVED (2026-11-15):** Cho phép `asset_id = NULL` cho `check_in`/`check_out` thiết bị chưa có Asset Record; bắt buộc Snapshot tên, Serial Number, Asset Tag nếu có; không tự động tạo Asset khi check-in.


- **B12 – APPROVED (2026-11-15):** Movement có các trạng thái `draft`, `pending_approval`, `approved`, `rejected`, `completed`, `cancelled`; yêu cầu metadata khi approved/completed; cấm draft → completed; chỉ cập nhật Asset sau khi thực hiện thành công và Audit mọi chuyển trạng thái.


- **B11 – APPROVED (2026-11-15):** Tách `requested_at` (tạo yêu cầu) và `executed_at` (hoàn tất di chuyển thực tế); không dùng chung `movement_at` cho hai ý nghĩa.


- **B10 – APPROVED (2026-11-15):** Maintenance personnel lưu JSON snapshot gồm `name`, `organization`, `role`; không lưu dữ liệu cá nhân nhạy cảm nếu không có căn cứ; giữ nguyên snapshot tại thời điểm bảo trì.


- **B09 – APPROVED (2026-11-14):** Maintenance Request liên kết Asset cùng tenant; phạm vi một Room phải khớp vị trí Asset lúc thực hiện; phạm vi nhiều Room phải khai báo rõ; Service kiểm tra lại vị trí thực tế.


- **B08 – APPROVED (2026-11-14):** Phiên bản đầu không có Emergency Bypass; mọi Maintenance Request qua Approval Workflow tiêu chuẩn. Emergency Maintenance chỉ xem xét ở phiên bản sau với quyền riêng, lý do bắt buộc và hậu kiểm.


- **B07 – APPROVED (2026-11-14):** Action `completed` cần `completed_at`/`completion_note`; `verified` cần `verified_by`/`verified_at`/`verification_note`; người xác minh phải khác người hoàn tất.


- **B06 – APPROVED (2026-11-14):** Incident liên kết nhiều Asset; Asset phải cùng tenant, có thể ở nhiều Room trong tenant nếu sự cố liên phòng; lưu vai trò Asset và kiểm tra tenant/quyền trước khi liên kết.


- **B05 – APPROVED (2026-11-14):** Incident dùng `open`, `in_progress`, `resolved`, `closed`, `cancelled`; xác minh lưu ở `verified_by`/`verified_at`; chỉ đóng sau xác minh.


- **B04 – APPROVED (2026-11-14):** Không hỗ trợ `multi_select` và không dùng `boolean_value`; Yes/No dùng `result`, Numeric/Text/Date dùng cột tương ứng; validation theo từng `response_type`.


- **B03 – APPROVED (2026-11-14):** Review yêu cầu chỉnh sửa thì chuyển `correction_required`, không quay về `draft`; giữ kết quả đã Submit, tạo Correction Revision mới và lưu lịch sử người yêu cầu, thời điểm, lý do.


- **B02 – APPROVED (2026-11-14):** Visitor có thể không có `access_person_id`; bắt buộc có tên, mục đích và phê duyệt hoặc quyền ngoại lệ hợp lệ; ngoại lệ phải có lý do và được Audit.


- **B01 – APPROVED (2026-11-14):** `entry_at` ghi nhận giờ check-in thực tế; `scheduled_entry_at` lưu giờ đăng ký trước nếu có; không dùng giờ đăng ký thay cho giờ thực tế.


- **A11 – APPROVED (2026-11-14):** `archived`, `retired`, `inactive` là trạng thái nghiệp vụ riêng; `deleted_at` chỉ là Soft Delete kỹ thuật. Mỗi bảng định nghĩa trạng thái phù hợp, không dùng thay thế nhau.


- **A10 – APPROVED (2026-11-14):** Rack là Asset `category=rack`; thiết bị có thể không gắn Rack; kiểm tra U-Position trong transaction và khóa Rack để tránh xung đột đồng thời.


- **A09 – APPROVED (2026-11-14):** Với cùng `customer_id` và `code`, chỉ một Version có hiệu lực tại một thời điểm; cấm khoảng hiệu lực chồng lấn; giữ lịch sử Version cũ.


- **A08 – APPROVED (2026-11-14):** Clone toàn bộ Checklist Items sang version mới với ID mới; version `approved` bất biến; mọi điều chỉnh phải tạo version mới.


- **A07 – APPROVED (2026-11-14):** Phiên bản đầu tiên không hỗ trợ `multi_select`; chỉ xem xét bổ sung khi có use case và cấu trúc lưu kết quả/đánh giá rõ ràng.


- **A06 – APPROVED (2026-11-14):** Bổ sung `revoked_by`, `revoked_at`, `revoke_reason`; không hard delete Access Person đã có lịch sử; thu hồi phải ghi trạng thái, người thực hiện, thời điểm và lý do.


- **A05 – APPROVED (2026-11-14):** Chỉ có `permanent` và `supervised`; mức `supervised` bắt buộc có người giám sát hoặc quy trình giám sát cụ thể.


- **A04 – APPROVED (2026-11-14):** Cho phép Internal User được phân quyền phụ trách tenant và Customer User thuộc chính tenant đó; phải xác minh quan hệ User–`customer_id` và kiểm tra quyền tại Service/Policy.


- **A03 – APPROVED (2026-11-14):** Mỗi Server Room có tối đa một Contact đang hoạt động được đánh dấu `is_primary = 1`; thay đổi Primary Contact phải thực hiện trong transaction; Contact cũ được giữ lịch sử.


- **A02 – APPROVED (2026-11-14):** `branch` bắt buộc có `branch_id`; `head_office`, `data_center`, `independent` phải có `branch_id = NULL`; `branch_id` phải thuộc cùng `customer_id`.


- **B15 – APPROVED (2026-11-15):** Không xóa Asset Record khi check-out; cập nhật trạng thái `checked_out` (hoặc trạng thái tương đương trong allowlist); tạo Movement `check_out` với thời gian, người thực hiện và snapshot thiết bị; khi quay lại tạo Movement `check_in` mới, không ghi đè lịch sử.

- **B16 – APPROVED (2026-11-15):** Khi `relocate`/`check_out`, Asset phải thuộc `from_server_room_id`; Execute cập nhật Asset và Movement trong cùng transaction, khóa Asset bằng row-level lock để chống double execution; nếu kiểm tra nguồn/xung đột thất bại thì rollback toàn bộ.


## 10. Lịch sử phê duyệt

- **2026-11-15 – Chủ dự án APPROVED C01, C02, C03.** C01: UTC là chuẩn lưu trữ; hiển thị/tìm kiếm/lọc theo timezone customer. C02: xác thực device/import, idempotency và chống replay; source_reference không phải credential. C03: retention theo loại dữ liệu/tần suất, archive khi cần; không xóa trước khi có policy được duyệt.


- **2026-11-15 – Chủ dự án APPROVED C04, C05, C06.** C04: phê duyệt tuần tự theo cấp, ALL approvers ở từng cấp; một REJECT dừng và từ chối yêu cầu. C05: resolve và snapshot danh sách approver khi cấp được kích hoạt. C06: hỗ trợ delegation và reassignment có kiểm soát, lưu principal/acting approver hoặc old/new approver cùng actor, thời điểm, lý do; Audit đầy đủ.


## Bổ sung quyết định C06 – Delegation scope và thời điểm áp dụng

**C06A – Delegation scope: APPROVED.** Chọn ủy quyền theo thời hạn và phạm vi, quản lý bằng bảng riêng `server_room_approval_delegations`; không chỉ lưu delegation trực tiếp trên từng approval request.

**C06B – Delegation activation: APPROVED.** Chọn phương án 2: chỉ kiểm tra và áp dụng delegation khi approval level được kích hoạt. Trước tiên snapshot principal approvers theo C05, sau đó kiểm tra delegation hợp lệ theo tenant, loại hồ sơ, room scope và thời điểm. Snapshot acting approver và delegation_id vào approval row. Các level đã kích hoạt không bị thay đổi hồi tố khi delegation hết hạn/thu hồi; level tiếp theo kiểm tra lại khi đến lượt.

**Schema decision:** bổ sung bảng `server_room_approval_delegations` (bảng 23); bổ sung `acting_approver_id`, `acted_by`, `delegation_id` vào `server_room_approvals`. ALL approvers vẫn bắt buộc. Nếu có nhiều delegation cùng khớp tại thời điểm kích hoạt, không tự chọn; chặn kích hoạt để xử lý xung đột.


## C07 – Quy tắc chống tự phê duyệt (APPROVED – 2026-11-15)

1. Requester (người tạo yêu cầu) không được tự approve chính yêu cầu đó trong mọi SRM workflow của phiên bản đầu.
2. Khi resolve approver theo C05, nếu requester xuất hiện trong danh sách approver của cấp, hệ thống loại requester khỏi danh sách hiệu lực và ghi audit/reason cho việc loại trừ.
3. Nếu sau khi loại requester, cấp không còn approver hợp lệ, không kích hoạt cấp và không tự động skip; yêu cầu workflow manager có thẩm quyền sửa cấu hình hoặc reassignment hợp lệ.
4. Delegation không được bypass SoD. Nếu acting approver được snapshot là requester hoặc không đủ điều kiện độc lập, chặn quyết định; xử lý bằng reassignment do người có thẩm quyền thực hiện và lưu đầy đủ lịch sử.
5. Quy tắc ALL vẫn giữ nguyên: mọi approver hợp lệ còn lại trong cấp phải APPROVE.

**Phạm vi:** C07 cấm requester tự duyệt chính yêu cầu do mình tạo; không tự mở rộng thành cấm mọi người từng tham gia xử lý nghiệp vụ được phê duyệt.

## C08 – Bảo mật tệp đính kèm (APPROVED – 2026-11-15)

1. **Private Storage:** binary chỉ lưu trên private disk/private bucket; không public object URL hoặc thư mục upload.
2. **MIME validation:** xác định MIME từ nội dung thực tế (file signature/magic bytes); không tin extension hoặc Content-Type do client gửi.
3. **Allowlist và giới hạn:** chỉ nhận loại file được cấu hình cho phép; giới hạn dung lượng được cấu hình tập trung. Không tự đặt con số khi chưa có chính sách vận hành.
4. **Quarantine và malware scan:** file mới upload ở trạng thái `pending`/quarantine. Chỉ chuyển sang `clean` sau khi scanner xác nhận an toàn. Trạng thái `pending`, `infected` hoặc `error` đều không được preview/download. File nhiễm mã độc phải bị cách ly và xử lý theo quy trình bảo mật; không trả file cho người dùng.
5. **Authorization:** kiểm tra quyền người dùng, tenant và quyền trên parent record trước upload, preview, download và delete. Download phải qua service có kiểm soát hoặc signed URL thời hạn rất ngắn sau authorization; không lộ `file_path` nội bộ.
6. **Audit:** ghi nhận upload, scan result, access/download và delete theo khả năng audit hiện có; không ghi nội dung bí mật của file vào log.
7. **Schema:** bổ sung trạng thái scan và thời điểm scan vào metadata của `server_room_attachments`; binary không lưu trong MySQL.
8. **Giới hạn quyết định:** MIME allowlist cụ thể, dung lượng tối đa, số lượng file và scanner/vendor sẽ cấu hình ở bước triển khai; không tự giả định trong schema review.

## C09 – Attachment Retention (APPROVED – 2026-11-15)

1. Không xóa file tự động khi hồ sơ cha chuyển `archived` hoặc Soft Delete; trạng thái hồ sơ cha không tự động kích hoạt xóa vật lý file.
2. Thời hạn lưu được cấu hình theo loại hồ sơ/tài liệu và chính sách nghiệp vụ đã phê duyệt; không hard-code số năm khi chưa có policy.
3. Hỗ trợ Legal Hold cho nhu cầu kiểm toán, tranh chấp hoặc yêu cầu pháp lý.
4. Khi Legal Hold còn hiệu lực, file không được xóa vật lý kể cả khi đã hết hạn retention.
5. Chỉ cho phép xóa vật lý sau khi retention hết hạn, Legal Hold không còn hiệu lực, đã kiểm tra tham chiếu và ghi Audit gồm actor, thời điểm và kết quả.

**Schema note:** Chủ dự án chọn Legal Hold ở cấp hồ sơ cha (parent record). Khi parent được đặt Legal Hold, toàn bộ attachment gắn với parent đó tự động được bảo vệ; không đặt Legal Hold độc lập cho từng file. Cách lưu trạng thái/metadata Legal Hold tại parent và cách scheduler tra cứu các attachment liên quan sẽ được chốt trong Final Schema Review; chưa tự ý thêm bảng/cột và chưa tạo migration.


### C09-A – Phạm vi Legal Hold (APPROVED – 2026-11-15)

- Legal Hold được quản lý tại cấp hồ sơ cha (parent record), không quản lý độc lập trên từng attachment.
- Khi parent có Legal Hold còn hiệu lực, tất cả file đính kèm thuộc parent đó đều không được purge.
- Khi parent được gỡ Legal Hold, các file đính kèm trở lại quy trình xét retention thông thường; chỉ purge nếu đã hết retention, không còn reference hợp lệ và đã ghi Audit.
- Schema cụ thể để lưu Legal Hold ở parent và truy vấn attachment liên quan vẫn phải được thiết kế trong Final Schema Review.


### C09-B – Lịch sử Legal Hold (APPROVED – 2026-11-15)

- Bắt buộc lưu lịch sử riêng cho mỗi lần đặt và gỡ Legal Hold; không chỉ ghi đè trạng thái hiện tại.
- Mỗi sự kiện phải lưu tối thiểu: parent record được áp dụng, hành động (`placed`/`released`), actor thực hiện, thời điểm UTC và lý do.
- Sự kiện gỡ hold phải liên kết được với lần đặt hold tương ứng; giữ nguyên lịch sử các chu kỳ hold trước đó.
- Chỉ người có quyền phù hợp mới được đặt/gỡ hold; thao tác phải được Audit và không hard delete lịch sử.
- Cấu trúc lưu trữ (bảng lịch sử riêng hay cơ chế tương đương) sẽ được quyết định trong Final Schema Review sau khi rà soát các loại parent polymorphic; chưa tạo migration.


## C10 – Audit Log Immutability (APPROVED – 2026-11-15)

1. **Append-only ở ứng dụng:** Audit Log chỉ được thêm mới; các chức năng thông thường không cho UPDATE/DELETE bản ghi audit.
2. **Bảo vệ ở Database:** tài khoản DB runtime của ứng dụng không được cấp quyền UPDATE/DELETE trên bảng Audit Log. Quyền migration/DBA phải tách biệt và được kiểm soát theo quy trình quản trị.
3. **Archive protection:** hỗ trợ lưu trữ archive riêng với quyền sửa/xóa bị hạn chế khi yêu cầu compliance; mức bảo vệ cụ thể được xác định theo chính sách compliance và năng lực hạ tầng.
4. **Audit fields:** ghi nhận tối thiểu actor, UTC timestamp, action, target object, result và thông tin trace cần thiết (ví dụ request/correlation ID khi có).
5. **Giới hạn bảo đảm:** append-only và DB privilege giảm nguy cơ sửa/xóa nhưng không tạo ra tính bất biến tuyệt đối trước DBA hoặc quản trị hạ tầng có đặc quyền. Không tuyên bố immutable tuyệt đối chỉ dựa vào UI.
6. **Implementation gate:** kiểm tra quyền DB thực tế, archive access control, retention, audit access policy và test âm tính UPDATE/DELETE bằng tài khoản ứng dụng trước khi triển khai.

## C11 – Pivot Column Naming (APPROVED – 2026-11-15)

- Giữ `server_room_incident_assets.impact_note` để mô tả ảnh hưởng của Asset trong Incident.
- Giữ `server_room_maintenance_assets.work_note` để ghi chú công việc bảo trì liên quan đến Asset.
- Không đổi thành tên chung `relationship_note`; hai trường phản ánh hai ngữ nghĩa nghiệp vụ khác nhau.
- Không phát sinh bảng hoặc cột mới; đây là xác nhận tên cột đã có trong Data Dictionary.


## C12 – Pivot Asset Snapshot (APPROVED – 2026-11-15)

- Lưu cả `asset_tag` và `asset_name` snapshot trên các pivot liên kết Asset với Incident và Maintenance.
- Snapshot được chụp tại thời điểm tạo liên kết; không tự cập nhật khi Asset gốc đổi tên hoặc đổi mã.
- Khi cần hiển thị lịch sử, ưu tiên snapshot; thông tin Asset hiện tại vẫn có thể được truy vấn riêng để đối chiếu.
- Kiểu dữ liệu, độ dài và nullability cụ thể được rà soát trong Column Schema/Final Schema Review; chưa tạo migration.


## C13 – Pivot Room Scope (APPROVED – 2026-11-15)

1. Incident được phép liên kết Asset thuộc nhiều Server Room khác nhau nếu tất cả cùng `customer_id` với Incident.
2. Maintenance mặc định chỉ liên kết Asset thuộc cùng Server Room với phạm vi yêu cầu.
3. Maintenance nhiều phòng chỉ hợp lệ khi yêu cầu khai báo rõ phạm vi đa phòng.
4. Service phải kiểm tra tenant và room scope trước khi tạo/cập nhật liên kết; không chỉ dựa vào ID do client gửi.
5. Không phát sinh bảng mới; quy tắc được thực thi ở service/validation và kiểm thử tenant/room scope.


## C14 – Customer Permission Revoke (APPROVED – 2026-11-15)

1. Thu hồi quyền bằng cách cập nhật `revoked_at` và `revoked_by`; không hard-delete grant đã phát sinh.
2. Khi cấp lại quyền sau thu hồi, tạo bản ghi grant mới để giữ lịch sử từng chu kỳ cấp/thu hồi.
3. Với cùng `(customer_id, user_id, permission_code)`, chỉ được có một grant đang hoạt động tại một thời điểm.
4. Chiến lược enforce active uniqueness (generated column/unique index hoặc transaction + locking) chỉ được chốt sau khi kiểm tra phiên bản MySQL, schema/index hiện hữu và khả năng hỗ trợ thực tế; không giả định trước.
5. Cấp/thu hồi phải kiểm tra tenant, quyền của người thực hiện và ghi Audit.


## C15 – Customer User Room Assignment Uniqueness (APPROVED – 2026-11-15)

1. Với mỗi `(customer_id, user_id, server_room_id)`, chỉ có một assignment đang hoạt động tại một thời điểm.
2. Thu hồi assignment bằng `revoked_at` và `revoked_by`; không hard-delete lịch sử.
3. Cấp lại quyền truy cập phòng sau khi thu hồi phải tạo row assignment mới.
4. Cơ chế enforce unique active (generated column/unique index hoặc transaction + locking) chỉ được chốt sau khi xác minh phiên bản MySQL, schema/index hiện hữu và khả năng hỗ trợ thực tế.
5. Grant/revoke phải kiểm tra tenant, quyền người thực hiện và ghi Audit; kiểm thử concurrency bắt buộc.


## C16 – Customer User Tenant Integrity (APPROVED – 2026-11-15)

1. Customer User phải có `users.account_type = 'customer'`.
2. `users.service_customer_id` phải bằng `customer_id` của bản ghi SRM được truy cập/thao tác.
3. User và mọi dữ liệu SRM liên quan phải thuộc cùng một tenant; không chấp nhận chỉ dựa vào user ID hoặc record ID do client gửi.
4. Trước khi chọn composite FK/candidate key, phải kiểm tra schema `users` hiện hữu. Nếu không thể enforce bằng FK ghép phù hợp, Service/Policy phải kiểm tra tenant ở mọi thao tác và có negative tests chống truy cập chéo.
5. Không tạo migration trước khi xác minh các cột, unique/index và quan hệ hiện hữu của `users`.


## C17 – Customer IT Admin Scope (APPROVED – 2026-11-15)

1. Customer IT Admin chỉ được cấp hoặc thu hồi quyền SRM cho Customer User thuộc cùng `customer_id` với chính mình.
2. Customer IT Admin chỉ được gán hoặc thu hồi quyền truy cập các Server Room thuộc tenant của mình.
3. Chỉ được cấp các `permission_code` nằm trong allowlist quyền SRM dành cho Customer Admin; không thể tự cấp quyền Internal/Platform hoặc nâng quyền vượt phạm vi được phép.
4. Mọi thao tác grant/revoke permission và room assignment đều phải được authorization theo tenant, kiểm tra trạng thái user/room, và ghi Audit actor, target, action, timestamp UTC, result.
5. Server-side phải resolve tenant từ user đã xác thực; không tin `customer_id`, `user_id` hoặc `server_room_id` do client gửi nếu chưa xác minh ownership.
6. Cần negative tests: Customer IT Admin tenant A không thể grant/revoke user tenant B, gán/revoke room tenant B, hoặc cấp permission ngoài allowlist.


## C18 – Customer User Disable / Tenant Change (APPROVED – 2026-11-15)

1. Khi Customer User bị `disabled`/`inactive`, hệ thống lập tức từ chối mọi truy cập SRM; permission và room assignment hiện hữu được giữ lại làm lịch sử/trạng thái, nhưng không được sử dụng để cấp quyền truy cập khi user bị khóa.
2. Khi Customer User chuyển `customer_id` sang tenant khác, toàn bộ permission và room assignment của tenant cũ phải được revoke; không tự động chuyển quyền sang tenant mới.
3. Giữ nguyên lịch sử grant/revoke và assignment; không hard-delete dữ liệu lịch sử.
4. Quy trình cập nhật trạng thái user, thu hồi permission và room assignment phải được thực hiện nhất quán trong transaction ở mức ứng dụng/DB phù hợp; nếu thất bại phải rollback, không để trạng thái một phần.
5. Khi user được kích hoạt lại hoặc chuyển tenant hoàn tất, quyền truy cập chỉ được khôi phục/cấp mới theo quy trình cấp quyền của tenant tương ứng; không tự khôi phục quyền tenant cũ.
6. Ghi Audit cho disable/enable, tenant change, các thao tác revoke và các lần truy cập SRM bị từ chối; ghi actor, target, UTC timestamp, action, result và correlation ID nếu có.
7. Negative tests bắt buộc: disabled user không truy cập được; user sau tenant change không còn quyền/room assignment tenant cũ; user không được tự động nhận quyền tenant mới; lỗi giữa quy trình không để lại permission/assignment ở trạng thái dở dang.


## D01 – Database Engine / Version Baseline (APPROVED – 2026-11-15)

- User xác nhận dự án sử dụng MySQL 8; lấy MySQL 8.0 làm baseline cho thiết kế schema, index, constraints và migration.
- Không thiết kế tương thích ngược MySQL 5.7 hoặc MariaDB trừ khi có yêu cầu mới.
- Trước khi viết/chạy migration, phải kiểm tra phiên bản MySQL 8.x cụ thể, `version_comment`, `sql_mode`, `character_set_server`, `collation_server`, storage engine và schema thực tế.
- Không giả định các đặc tính vận hành hoặc cấu hình production chỉ từ thông tin MySQL 8.


### D01 – Runtime evidence (verified 2026-11-15)

Thông tin truy vấn qua kết nối Laravel đang hoạt động tới database `kpi_dashboard` trong container `kpi-dashboard-db-1`:

- `VERSION()`: `8.0.46`
- `version_comment`: `MySQL Community Server - GPL`
- `sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`
- `character_set_server`: `utf8mb4`
- `collation_server`: `utf8mb4_unicode_ci`
- `default_storage_engine`: `InnoDB`

Kết luận: runtime xác minh là MySQL Community Server 8.0.46, InnoDB, charset/collation server `utf8mb4`/`utf8mb4_unicode_ci`. Đây là bằng chứng môi trường tại thời điểm kiểm tra; trước production migration vẫn phải kiểm tra lại trên đúng môi trường triển khai.


## D02 – Storage Engine and Transaction Policy (APPROVED – 2026-11-15)

1. Toàn bộ 23 bảng SRM sử dụng InnoDB.
2. Các thao tác nghiệp vụ cập nhật nhiều bảng có quan hệ phải được thực hiện trong một Database Transaction phù hợp.
3. Nếu một bước bắt buộc trong transaction thất bại, rollback toàn bộ thay đổi thuộc transaction; không để lại trạng thái nghiệp vụ dở dang.
4. Không sử dụng MyISAM cho các bảng SRM.
5. Với thao tác đồng thời có nguy cơ tranh chấp dữ liệu, Service phải đánh giá và sử dụng cơ chế khóa phù hợp; `SELECT ... FOR UPDATE` được dùng khi cần khóa bản ghi để bảo vệ invariant, kết hợp transaction và thứ tự khóa nhất quán để hạn chế deadlock.
6. Các transaction phải ngắn, không giữ row lock trong lúc gọi dịch vụ mạng hoặc thực hiện tác vụ chậm; có xử lý lỗi deadlock/lock timeout phù hợp.
7. Kiểm thử phải bao gồm rollback khi lỗi giữa chừng và các tình huống concurrent update cho các invariant quan trọng.


## D03 – Lock Ordering, Deadlock and Lock Wait Handling (APPROVED – 2026-11-15)

1. Các transaction có thể khóa nhiều bản ghi phải lấy khóa theo thứ tự xác định, nhất quán giữa các luồng nghiệp vụ để giảm nguy cơ deadlock.
2. Khi gặp MySQL deadlock error `1213` hoặc lock wait timeout `1205`, ứng dụng phải xử lý lỗi có kiểm soát; transaction thất bại phải rollback đúng phạm vi.
3. Cho phép retry transaction với số lần giới hạn và backoff tăng dần; số lần, thời gian chờ và ngưỡng retry phải được cấu hình/kiểm chứng trong triển khai, không retry vô hạn.
4. Chỉ retry khi toàn bộ thao tác có thể chạy lại an toàn (idempotent hoặc được bảo vệ bằng idempotency key/unique constraint và không gây side effect lặp). Không retry mù các thao tác gửi thông báo, gọi API bên ngoài hoặc phát sinh side effect ngoài transaction.
5. Side effect ngoài database nên được phát hành sau khi transaction commit (ví dụ qua cơ chế after-commit/outbox nếu cần); không giữ row lock trong khi gọi network/API.
6. Ghi log có cấu trúc cho transaction lỗi/retry: operation/correlation ID, error code, số lần retry, thời gian và kết quả; không ghi dữ liệu bí mật.
7. Kiểm thử concurrency, deadlock, lock timeout, rollback và giới hạn retry đối với các nghiệp vụ có tranh chấp dữ liệu.


## D04 – Foreign Key and Referential Integrity Policy (APPROVED – 2026-11-15)

1. Dùng Foreign Key cho các quan hệ nội bộ SRM có đích tham chiếu và khóa được xác định rõ, tương thích với schema thực tế.
2. Quan hệ polymorphic như `approvable_type`/`approvable_id` và `attachable_type`/`attachable_id` không thể được bảo vệ bằng một FK SQL trực tiếp; Service phải kiểm tra loại đối tượng, sự tồn tại, tenant ownership và quyền truy cập trước khi thao tác.
3. Không dùng `ON DELETE CASCADE` cho dữ liệu nghiệp vụ, lịch sử và Audit SRM.
4. Ưu tiên `RESTRICT`/`NO ACTION` để ngăn xóa bản ghi cha khi còn dữ liệu con liên quan; việc xóa vật lý chỉ được xem xét sau khi đáp ứng retention, Legal Hold và quy trình kiểm soát đã duyệt.
5. Soft Delete không xóa bản ghi vật lý và không được làm mất tính toàn vẹn tham chiếu; Service phải xác định rõ cách xử lý record soft-deleted trong từng nghiệp vụ.
6. Trước khi tạo FK, phải kiểm tra dữ liệu orphan, kiểu dữ liệu/unsigned, index và thứ tự migration; không tạo constraint nếu dữ liệu hiện hữu chưa đạt điều kiện.
7. Kiểm thử bắt buộc: ngăn xóa cha còn con, ngăn tham chiếu chéo tenant, kiểm tra polymorphic reference hợp lệ và xử lý soft-deleted parent.


## D05 – Unique Index and Duplicate Prevention Policy (APPROVED – 2026-11-15)

1. Các mã định danh nghiệp vụ phải có uniqueness phù hợp với phạm vi áp dụng; với dữ liệu thuộc tenant, phạm vi unique phải bao gồm `customer_id` khi nghiệp vụ yêu cầu mã chỉ duy nhất trong tenant.
2. Những bản ghi chỉ được phép tồn tại một trạng thái active cho cùng một business key phải có cơ chế bảo đảm uniqueness ở database khi khả thi, không chỉ kiểm tra tại PHP.
3. Không dựa riêng vào application-level pre-check để bảo đảm uniqueness vì có thể xảy ra race condition khi nhiều request đồng thời.
4. Các thao tác tạo dữ liệu quan trọng cần có cơ chế idempotency phù hợp để request gửi lại không tạo bản ghi hoặc side effect trùng; thiết kế key/scope/retention phải theo từng operation.
5. Trước khi tạo Unique Index/constraint, phải rà soát dữ liệu trùng hiện hữu, xác định quy tắc nghiệp vụ, xử lý dữ liệu trùng có kiểm soát và kiểm tra ảnh hưởng tới các module đang dùng schema.
6. Không áp đặt một quy tắc unique toàn cục cho mọi mã; xác định rõ business key, tenant scope, trạng thái active/inactive và lịch sử cho từng bảng.
7. Kiểm thử bắt buộc: insert trùng, concurrent insert/update, regrant sau revoke, retry/idempotent request và tenant isolation.


## D06 – JSON and Data Type Policy (APPROVED – 2026-11-15)

1. Sử dụng MySQL `JSON` cho dữ liệu có cấu trúc linh hoạt, không dùng JSON để thay thế các cột nghiệp vụ có cấu trúc ổn định, cần lọc/sắp xếp/join/index thường xuyên.
2. Các giá trị thời gian được lưu UTC; hiển thị và truy vấn theo timezone tenant theo quyết định C01.
3. Giá trị tiền tệ dùng `DECIMAL` với precision/scale được xác định theo nghiệp vụ; không dùng `FLOAT`/`DOUBLE` cho số tiền.
4. Số lượng dùng kiểu số nguyên phù hợp với miền giá trị và giới hạn nghiệp vụ; không chọn độ rộng tùy tiện.
5. Không lưu nội dung file nhị phân trong JSON hoặc bảng nghiệp vụ; file được lưu ở Storage riêng và DB chỉ lưu metadata/reference cần thiết.
6. Giá trị thuộc tập lựa chọn cố định phải được kiểm soát bằng validation phía ứng dụng và constraint phù hợp với MySQL 8.0.46/schema thực tế; không chỉ dựa vào giao diện.
7. Với JSON cần truy vấn theo thuộc tính cụ thể, phải đánh giá generated column/index hoặc giải pháp phù hợp; chỉ bổ sung khi có use case và kiểm thử hiệu năng.
8. Kiểu dữ liệu, precision/scale, nullability, default và constraint cụ thể của từng cột được chốt trong Final Schema Review sau khi đối chiếu data dictionary và schema hiện hữu.


## D07 – Indexing and Query Performance Policy (APPROVED – 2026-11-15)

1. Mọi Foreign Key phải có index phù hợp cho các thao tác join, kiểm tra constraint và truy vấn quan hệ; xác minh index hiện có trước khi tạo mới để tránh trùng lặp không cần thiết.
2. Thiết kế Composite Index dựa trên query pattern thực tế; cân nhắc tenant scope (`customer_id`), room/asset reference, trạng thái và cột thời gian theo điều kiện lọc/sắp xếp thực tế.
3. Không tạo index tràn lan; đánh giá chi phí INSERT/UPDATE/DELETE, dung lượng lưu trữ và khả năng trùng lặp/leftmost-prefix với index hiện hữu.
4. Các truy vấn danh sách, tìm kiếm, lọc và báo cáo quan trọng phải được kiểm tra bằng `EXPLAIN`; dùng `EXPLAIN ANALYZE` khi phù hợp và an toàn trên dữ liệu đại diện.
5. Chỉ bổ sung/thay đổi index khi có căn cứ từ query pattern, execution plan hoặc đo kiểm hiệu năng; không suy đoán index chỉ từ tên cột.
6. Trước migration index, rà soát index/constraint hiện hữu, dữ liệu và khả năng lock/ảnh hưởng vận hành trên MySQL 8.0.46; kế hoạch triển khai phải có bước xác minh sau thay đổi.
7. Kiểm thử phải bao gồm truy vấn theo tenant, room, status, thời gian, join FK và các truy vấn dashboard/báo cáo quan trọng.


## D08 – Pagination, Filtering and Sorting Policy (APPROVED – 2026-11-15)

1. Tất cả API trả danh sách phải hỗ trợ pagination; endpoint đặc thù chỉ được miễn nếu có lý do được ghi nhận và giới hạn kết quả rõ ràng.
2. Mặc định dùng `LIMIT`/`OFFSET`; chỉ cân nhắc cursor pagination khi quy mô dữ liệu hoặc yêu cầu hiệu năng/độ ổn định của tập kết quả chứng minh cần thiết.
3. Phải giới hạn `page_size` tối đa ở server; không tin giá trị do client gửi nếu vượt ngưỡng cấu hình.
4. Sort field và sort direction phải được kiểm tra bằng allowlist; không nối trực tiếp tên cột hoặc biểu thức sort tùy ý từ client vào SQL.
5. Filter phải được validate kiểu dữ liệu, miền giá trị và quyền truy cập; mọi truy vấn phải áp dụng tenant scope/authorization ở server.
6. Pagination phải có thứ tự sắp xếp ổn định, bổ sung unique tie-breaker (ví dụ primary key) khi sort field không duy nhất để hạn chế bản ghi trùng/bỏ sót giữa các trang.
7. Các truy vấn list/filter/sort quan trọng phải được kiểm tra bằng `EXPLAIN` và đối chiếu với index theo D07.
8. Giá trị mặc định và tối đa của `page_size`, danh sách sort/filter được phép sẽ được xác định theo từng API và kiểm thử tải; không áp một con số tùy tiện cho toàn hệ thống.


## D09 – Logging, Monitoring and Observability Policy (APPROVED – 2026-11-15)

1. Mỗi request/operation phải có `request_id` hoặc `correlation_id` để liên kết log giữa các lớp xử lý; giữ ID xuyên suốt các service/job liên quan khi có thể.
2. Log nghiệp vụ/kỹ thuật phải ghi các trường phù hợp như actor, tenant, action, target, result, timestamp UTC và correlation ID; chỉ ghi dữ liệu cần thiết.
3. Phân biệt Application Log, Security Log và Audit Log theo mục đích, quyền truy cập, retention và yêu cầu toàn vẹn. Audit Log tuân thủ chính sách append-only/immutability đã duyệt tại C10; không thay Audit bằng Application Log.
4. Tuyệt đối không ghi password, token, secret, credential, private key hoặc dữ liệu nhạy cảm không cần thiết vào log; áp dụng masking/redaction cho dữ liệu có nguy cơ lộ lọt.
5. Monitoring phải theo dõi lỗi ứng dụng, latency/response time, slow queries, deadlock (`1213`), lock wait timeout (`1205`) và các chỉ số vận hành liên quan.
6. Log phải có chính sách retention, archive, phân quyền truy cập và bảo vệ phù hợp; chỉ người/role được phép mới được truy cập log chứa thông tin vận hành hoặc bảo mật.
7. Cần cảnh báo cho lỗi nghiêm trọng, tỷ lệ lỗi tăng, truy vấn chậm bất thường và deadlock/lock timeout vượt ngưỡng được cấu hình; ngưỡng cụ thể xác định khi triển khai dựa trên baseline vận hành.
8. Kiểm thử xác minh correlation ID xuyên request/job, redaction secret, quyền truy cập log và khả năng truy vết sự cố.
