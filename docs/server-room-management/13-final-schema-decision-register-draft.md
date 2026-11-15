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