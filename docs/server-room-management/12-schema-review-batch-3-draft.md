# SRM – Schema Review Batch 3 (8 bảng cuối)

> Review đối chiếu Data Dictionary, ERD, Data Integrity & State Machines và Column Schema Batch 3. Đây là review thiết kế, chưa phải phê duyệt migration.

## 1. Kết luận tổng quan

Batch 3 hoàn tất rà soát 8 bảng còn lại trong register 22 bảng: Environment Logs, Approvals, Attachments, Audit Logs, hai pivot và hai bảng customer access-control.

Các nguyên tắc chính đã rõ: log môi trường thuộc tenant/room; ba bảng approval/attachment/audit dùng polymorphic allowlist và service-layer tenant validation; hai pivot dùng composite FK tenant; customer user dùng quyền chức năng riêng và room assignment theo nguyên tắc deny-by-default.

C01–C06 đã được chủ dự án APPROVED. Approval áp dụng tuần tự theo cấp, ALL approvers tại từng cấp, snapshot người duyệt khi cấp được kích hoạt, và hỗ trợ delegation/reassignment có audit. Các điểm còn mở tập trung vào bảo mật file, audit immutability, lịch sử thu hồi permission và unique active room assignment.

## 2. Review từng bảng

### 2.1 server_room_environment_logs

**Đã phù hợp**
- customer_id và server_room_id bắt buộc; composite FK (server_room_id, customer_id) → server_rooms(id, customer_id).
- Ghi nhận recorded_at, nhiệt độ, độ ẩm, trạng thái điều hòa/UPS/nguồn, source_type và source_reference.
- humidity_percent phải trong khoảng 0–100; ít nhất một trường đo/trạng thái có dữ liệu.
- Ngưỡng cảnh báo cấu hình riêng, không hard-code vào log.

**Đã chốt C01–C03**
1. `source_type` allowlist `manual/device/import`, default `manual`.
2. Device/import phải xác thực nguồn; dùng idempotency key chống ghi trùng khi retry và cơ chế chống replay. `source_reference` không thay thế xác thực.
3. `recorded_at` lưu UTC. Giao diện, tìm kiếm và lọc dùng timezone customer; backend quy đổi điều kiện tìm kiếm sang UTC trước truy vấn.
4. Retention theo loại dữ liệu và tần suất; archive khi cần. Không tự xóa trước khi retention policy được duyệt.
5. `recorded_by` có thể NULL với device/import; cần phân biệt actor/service identity với user account.
6. Nhiệt độ và trạng thái thiết bị vẫn cần domain/allowlist theo sensor hoặc policy; không tự đặt ngưỡng khi chưa có căn cứ.

### 2.2 server_room_approvals

**Đã phù hợp**
- Mỗi bước duyệt gắn một hồ sơ qua approvable_type/approvable_id; status pending/approved/rejected/skipped.
- Type phải dùng alias allowlist; service phải xác thực hồ sơ tồn tại và customer_id trùng.
- approver phải active, có quyền, đúng tenant và đúng step tại thời điểm quyết định.
- Tách quyền submit khỏi quyền approve; mặc định cấm self-approval.
- Reassign và quyết định phải được audit; quyết định lưu lịch sử, không ghi đè.

**Đã chốt C04–C06**
1. Approval chạy tuần tự theo cấp; trong mỗi cấp, tất cả approver phải APPROVE mới được chuyển cấp; một REJECT dừng workflow.
2. Group/role được resolve thành danh sách user cụ thể và snapshot khi cấp được kích hoạt; cấp sau snapshot khi đến lượt.
3. Delegation và Reassignment đều được hỗ trợ với kiểm soát quyền, thời hạn/phạm vi phù hợp và Audit Log đầy đủ. Reassignment lưu old/new approver, actor, time, reason; delegation lưu principal và acting approver.
4. Quy tắc ALL không bị giảm khi ủy quyền/thay thế.

**Còn cần hoàn thiện kỹ thuật**
1. Xác định cấu trúc cột/metadata để lưu level, step, principal approver, acting approver, assigned_by và lịch sử delegation/reassignment; không tự thêm bảng ngoài register 22 nếu chưa chứng minh cần thiết.
2. Chống tạo trùng approver trong cùng step/level bằng unique constraint hoặc transaction.
3. `comments` bắt buộc khi rejected; skipped chỉ được dùng khi workflow cho phép và phải có lý do.
4. Polymorphic parent phải qua allowlist; mọi thao tác kiểm tra tenant, trạng thái hồ sơ và chống double-submit.
5. Chưa quyết định có cần workflow definition/approval events riêng; ưu tiên dùng approval + audit hiện hữu nếu đáp ứng đầy đủ lịch sử.

### 2.3 server_room_attachments

**Đã phù hợp**
- DB chỉ lưu metadata; binary lưu trên disk/object storage.
- attachable_type/attachable_id dùng polymorphic allowlist.
- file_path là đường dẫn nội bộ, không public URL.
- Upload/download/delete đều phải kiểm tra quyền và tenant của parent; không cascade xóa file tự động.
- Có file_size, mime_type, checksum_sha256 và uploaded_by.

**Cần chốt**
1. MIME allowlist, dung lượng tối đa, số lượng file và quy tắc tên file.
2. MIME phải xác minh theo nội dung file, không tin extension hoặc client header.
3. Malware scan có bắt buộc trước khi cho phép download/preview không? Cần trạng thái quarantine/scan nếu scan bất đồng bộ; schema hiện chưa có.
4. Storage disk và đường dẫn cần tránh đoán được; private bucket/object ACL, signed URL ngắn hạn hoặc stream qua ứng dụng.
5. Retention, legal hold và quy trình xóa vật lý khi parent archived/deleted.
6. checksum_sha256 hữu ích kiểm tra integrity/deduplicate nhưng không thay thế malware scanning.
7. uploaded_by nullable cần cho phép upload hệ thống/import; phải có audit actor nguồn tương ứng.
8. Polymorphic type cần alias allowlist, không lưu PHP class tùy ý.

### 2.4 server_room_audit_logs

**Đã phù hợp**
- customer_id, target polymorphic, action, old_values/new_values, actor, IP, user-agent, request_id và created_at.
- Append-only ở tầng ứng dụng; quyền đọc riêng; target được xác định bằng allowlist.
- Không ghi password, token, secret, session, private key hoặc dữ liệu nhạy cảm không cần thiết.
- Audit phải ghi được cả khi parent đã archived/soft-deleted.

**Cần chốt**
1. old_values/new_values cần whitelist field; loại bỏ secret, dữ liệu cá nhân không cần thiết và dữ liệu file nhạy cảm.
2. JSON snapshot có thể lớn; cần giới hạn kích thước và tránh ghi toàn bộ blob không cần thiết.
3. Retention theo loại hồ sơ, quyền xem log và quy trình export chưa được xác định.
4. “Append-only” ở ứng dụng không chống được DBA hoặc người có quyền DB sửa trực tiếp. Cần quyết định mức chống sửa: DB permissions, archive immutable/object lock, hash chaining hoặc giải pháp tương đương.
5. request_id cần correlation format/unique scope thống nhất; user_agent/IP có thể chứa dữ liệu cần kiểm soát truy cập.
6. Không có updated_at là phù hợp với append-only; không thêm soft delete cho audit log.
7. Actor NULL cần phân biệt system job/integration với anonymous action bằng convention rõ ràng.

### 2.5 server_room_incident_assets

**Đã phù hợp**
- Pivot nhiều-nhiều Incident ↔ Asset, dùng composite PK (incident_id, asset_id).
- customer_id dùng composite FK tới incident và asset, chống liên kết chéo tenant.
- Không lưu danh sách asset ID trong JSON; xóa bị RESTRICT với hồ sơ lịch sử.

**Cần chốt**
1. Data Dictionary ERD có impact_note; Batch 3 đổi tên thành relationship_note. Cần thống nhất một tên và ý nghĩa.
2. Có cần snapshot asset_tag/name tại thời điểm liên kết không? FK asset hiện tại có thể thay đổi tên/tag; lịch sử incident có thể cần snapshot.
3. Asset có bắt buộc thuộc cùng server_room_id của incident hay chỉ cùng customer? Review Batch 2 đã nêu nhưng chưa có quyết định.
4. Cần unique composite PK đủ chống duplicate; index phụ (customer_id, asset_id) phục vụ tra cứu.
5. Thêm/bỏ asset khỏi incident sau submit/close phải theo permission và audit; không hard delete lịch sử nếu hồ sơ đã đóng.

### 2.6 server_room_maintenance_assets

**Đã phù hợp**
- Pivot nhiều-nhiều Maintenance Request ↔ Asset.
- Composite PK (maintenance_request_id, asset_id), composite FK cùng customer.
- RESTRICT xóa với hồ sơ đã submit/approved/completed; thay đổi danh sách phải audit.

**Cần chốt**
1. Data Dictionary dùng work_note; Batch 3 dùng relationship_note. Cần thống nhất tên và semantics.
2. Asset có bắt buộc thuộc server_room_id của maintenance request hay có thể ở phòng khác cùng tenant?
3. Có cần snapshot asset tag/name tại thời điểm bảo trì không?
4. relationship_note/work_note là ghi chú quan hệ hay nội dung công việc chi tiết? Nếu mỗi asset có nhiều công việc, một dòng pivot có thể không đủ; cần xác định có cho nhiều dòng cùng asset hay không.
5. Thay đổi pivot sau submit/approval phải tạo audit; không xóa vật lý khi request đã hoàn tất.

### 2.7 customer_user_srm_permissions

**Đã phù hợp**
- Tách quyền chức năng customer user khỏi RBAC nội bộ; RBAC nội bộ tiếp tục dùng permissions/group_permissions/user_groups hiện có.
- Unique (customer_id, user_id, permission_code); permission_code phải thuộc allowlist SRM customer.
- user phải là account_type=customer và users.service_customer_id = customer_id.
- Không cấp internal/platform/Super Admin permissions.
- Customer IT Admin chỉ quản trị user trong tenant của mình.

**Cần chốt**
1. Data Dictionary/Batch 3 hiện có granted_by nhưng chưa có revoked_at/revoked_by. Khi thu hồi, xóa row + audit hay giữ row với revoked fields?
2. Nếu xóa row và cấp lại, unique hiện tại hoạt động nhưng lịch sử chỉ còn ở audit log; cần chắc chắn audit lưu grant/revoke/permission_code/user/customer/actor/time.
3. Nếu thêm revoked fields, unique (customer_id,user_id,permission_code) sẽ ngăn cấp lại sau revoke. Cần active unique strategy tương tự room assignment (generated column hoặc transaction/locking).
4. Cần FK (user_id, customer_id) chỉ khi users có candidate key tương ứng; FK user_id đơn không bảo đảm cùng tenant. Nếu không bổ sung unique vào users hiện hữu, bắt buộc service check + test âm tính.
5. granted_by phải được kiểm tra là Customer IT Admin có quyền và cùng tenant, không chỉ là user tồn tại.
6. Permission allowlist cần được định nghĩa thành registry rõ ràng; không nhận permission_code tùy ý từ request.

### 2.8 customer_user_server_rooms

**Đã phù hợp**
- Gán customer user vào room cụ thể; customer_id, user_id, server_room_id, granted_by, revoked_by, revoked_at.
- Room có composite FK (server_room_id, customer_id).
- Không assignment còn hiệu lực thì deny by default; revoke phải có hiệu lực ngay.
- can_access_all_branches không thay thế room assignment.
- Cấp/thu hồi phải audit; Customer IT Admin chỉ gán user/room cùng tenant.

**Cần chốt**
1. UNIQUE active assignment chưa được chọn. MySQL 8 không hỗ trợ partial unique index thông thường; Batch 3 đề xuất generated column hoặc transaction/service locking. Cần chốt giải pháp và test race condition.
2. user_id cần bảo đảm account_type=customer và service_customer_id=customer_id. FK đơn không đủ; cần composite key ở users hoặc service check.
3. granted_by/revoked_by phải được authorization theo tenant và permission.
4. Khi user bị disable, deleted hoặc chuyển tenant, assignment phải bị vô hiệu hóa/thu hồi theo quy tắc rõ ràng.
5. Cấp lại sau revoke tạo row mới để giữ lịch sử; không sửa row cũ thành active nếu cần audit lịch sử chính xác.
6. Cần index tối ưu cho truy vấn active assignment theo user và room; xác định query pattern trước khi chốt index.
7. Không dùng can_access_all_branches để mở rộng phạm vi room; room assignment là điều kiện bắt buộc.

## 3. Ma trận tenant và integrity

| Bảng | Quan hệ/ràng buộc | Cơ chế đề xuất |
|---|---|---|
| environment_logs | room + customer | Composite FK |
| approvals | polymorphic parent + customer | Allowlist + service transaction |
| attachments | polymorphic parent + customer | Allowlist + authorization trước upload/download/delete |
| audit_logs | polymorphic target + customer | Allowlist; append-only; hỗ trợ parent archived |
| incident_assets | incident + customer; asset + customer | Composite FK |
| maintenance_assets | request + customer; asset + customer | Composite FK |
| customer_user_srm_permissions | user + customer + account_type | Composite FK nếu schema users hỗ trợ; nếu không service check |
| customer_user_server_rooms | room + customer; user + customer + account_type | Composite FK cho room; service check hoặc candidate key cho user |

Actor fields đều cần kiểm tra tenant, account type, active status và permission. FK users.id không thay thế authorization.

## 4. Quyết định còn mở trước Migration

1. Environment Logs: C01–C03 đã APPROVED; còn thiết kế chi tiết API authentication, idempotency/replay protection và retention policy vận hành.
2. Approvals: ANY/ALL; group resolution/snapshot; assigned_by; reassign history; có cần approval_events/workflow definition không.
3. Attachments: MIME/dung lượng, malware scanning/quarantine, private storage, retention/legal hold.
4. Audit Logs: retention, access policy, masking, mức immutability chống sửa trực tiếp.
5. Incident Assets: impact_note hay relationship_note; snapshot asset; bắt buộc cùng phòng hay chỉ cùng tenant.
6. Maintenance Assets: work_note hay relationship_note; snapshot asset; phạm vi room; một hay nhiều work item trên một asset.
7. Customer Permissions: xóa row + audit hay revoked_at/revoked_by; active unique strategy nếu giữ revoke fields.
8. Room Assignments: generated column hay transaction/locking cho unique active assignment; xử lý user disable/chuyển tenant.
9. Candidate key (id, customer_id) cho users có khả thi không; nếu không, chuẩn hóa service checks và negative tests.

## 5. Điều kiện kỹ thuật trước Migration

- [ ] Tạo candidate keys và composite FK cần thiết sau khi kiểm tra schema/index hiện hữu.
- [ ] Polymorphic aliases được định nghĩa allowlist cố định; không nhận class name từ client.
- [ ] Service layer kiểm tra target tồn tại, tenant đúng và quyền thao tác trong transaction.
- [ ] Approval chống self-approval, double-submit; quyết định/reassign append-only.
- [ ] Attachment private storage, MIME validation, malware scan policy và authorization.
- [ ] Audit append-only, masking và retention policy.
- [ ] Pivot integrity, unique pair và quy tắc cùng room/cùng tenant.
- [ ] Customer permission allowlist; deny by default; không cấp quyền nội bộ.
- [ ] Active room assignment chống duplicate kể cả khi có concurrent requests.
- [ ] Viết test tenant mismatch, permission escalation, revoked access, duplicate active assignment, polymorphic type spoofing và file download unauthorized.

## 6. Kết luận Batch 3

- Đã rà soát đủ 8 bảng còn lại.
- Column Schema Batch 3 có các điểm bổ sung/đổi tên so với Data Dictionary (assigned_by, revoked fields đang là đề xuất; impact_note/work_note đổi thành relationship_note); cần thống nhất trước migration.
- Tổng cộng đã review đủ 22/22 bảng ở ba batch.
- C01–C06 đã APPROVED; C07–C18 vẫn OPEN.
- Chưa có phê duyệt final schema và chưa tạo Laravel migrations.


## Bổ sung review – C06 Delegation và bảng thứ 23

**Trạng thái quyết định nghiệp vụ:** APPROVED – chủ dự án chọn delegation theo thời hạn/phạm vi và chọn chỉ áp dụng khi approval level được kích hoạt.

**Schema impact:** thêm `server_room_approval_delegations`; bổ sung `acting_approver_id`, `acted_by`, `delegation_id` vào `server_room_approvals`. Principal approver vẫn được snapshot ở `approver_id`; acting approver và delegation được snapshot tại thời điểm kích hoạt cấp. Không hồi tố thay đổi cấp đã kích hoạt khi delegation bị thu hồi/hết hạn.

**Review còn phải kiểm tra trước migration:** unique/conflict strategy cho các delegation overlap; composite FK tenant; index theo customer/principal/status/validity; quyền phê duyệt và thu hồi delegation; audit event khi áp dụng delegation. Tổng register điều chỉnh từ 22 lên 23 bảng.


## 6. C07 – Approval self-approval (APPROVED – 2026-11-15)

**Quyết định:** Cấm requester tự phê duyệt yêu cầu do chính mình tạo trong mọi SRM workflow của phiên bản đầu.

- Loại requester khỏi approver snapshot nếu requester xuất hiện trong danh sách đã resolve; ghi nhận lý do/audit.
- Nếu không còn approver hợp lệ ở cấp, chặn kích hoạt; không tự skip cấp.
- Delegation không được bypass quy tắc này; acting approver là requester thì không được approve. Chỉ workflow manager có thẩm quyền mới được reassignment và phải giữ ALL.
- Đây là quy tắc authorization/service-level, không phát sinh bảng hoặc cột mới trong schema.

## 7. C08 – Attachment Security (APPROVED – 2026-11-15)

- Binary chỉ lưu Private Storage; metadata lưu trong MySQL; không public `file_path`.
- MIME xác minh bằng nội dung thực tế; không tin extension hoặc client header. Chỉ nhận MIME allowlist và dung lượng cấu hình server-side.
- File mới upload được cách ly ở trạng thái `pending`; chỉ `clean` mới cho preview/download. `infected` và `error` đều bị chặn (fail closed).
- Bổ sung `scan_status` (`pending`, `clean`, `infected`, `error`) và `scanned_at` vào `server_room_attachments`; không phát sinh bảng mới.
- Upload/preview/download/delete phải kiểm tra authorization, tenant và parent record. Không public file path; download qua service authorization hoặc signed URL rất ngắn sau khi kiểm tra quyền.
- MIME allowlist, giới hạn dung lượng/số lượng file và scanner/vendor cụ thể sẽ được cấu hình ở giai đoạn triển khai; chưa ấn định con số.

## 8. C09 – Attachment Retention (APPROVED – 2026-11-15)

- Không xóa tự động file khi parent Archived/Soft Deleted.
- Retention cấu hình theo loại hồ sơ/tài liệu và policy nghiệp vụ; chưa hard-code thời hạn.
- Legal Hold được quản lý ở parent record; tất cả attachment của parent tự động được bảo vệ, không có Legal Hold độc lập từng file.
- Parent đang Legal Hold chặn purge tất cả attachment liên quan dù retention đã hết.
- Khi gỡ Legal Hold, file chỉ đủ điều kiện purge nếu retention đã hết, không còn reference hợp lệ và có Audit actor/thời điểm/kết quả.
- Cách lưu Legal Hold ở parent và cơ chế resolve attachment sẽ được chốt trong Final Schema Review; không tự thêm bảng/cột ở bước này.


## 9. C09-B – Legal Hold event history (APPROVED – 2026-11-15)

- Mỗi lần đặt/gỡ Legal Hold phải có lịch sử riêng, không ghi đè trạng thái lịch sử.
- Lưu parent identity, action, actor, UTC timestamp, reason; release liên kết lần placement tương ứng.
- Không hard delete; quyền đặt/gỡ phải được kiểm soát và Audit.
- Cấu trúc bảng/cột chờ Final Schema Review.


## 10. C10 – Audit Log Immutability (APPROVED – 2026-11-15)

**Quyết định:**
- Audit Log append-only ở ứng dụng; không cho UPDATE/DELETE qua chức năng nghiệp vụ thông thường.
- Tài khoản DB runtime của ứng dụng không có quyền UPDATE/DELETE trên bảng Audit Log.
- Có thể archive riêng với quyền sửa/xóa hạn chế khi compliance yêu cầu; mức bảo vệ cụ thể chốt theo policy và hạ tầng thực tế.
- Ghi actor, UTC timestamp, action, target object, result và trace cần thiết.
- Không tuyên bố bất biến tuyệt đối trước DBA/quản trị hạ tầng đặc quyền.

**Schema/implementation impact:** không phát sinh bảng mới theo quyết định này. Cần kiểm tra DB grants, tách tài khoản runtime và migration/DBA, archive/retention/access policy và negative tests UPDATE/DELETE trước release.

## 11. C11 – Pivot Column Naming (APPROVED – 2026-11-15)

- Giữ `server_room_incident_assets.impact_note` cho ngữ cảnh ảnh hưởng Asset trong Incident.
- Giữ `server_room_maintenance_assets.work_note` cho ghi chú công việc bảo trì.
- Không đổi thành tên chung `relationship_note`; không phát sinh schema object mới.


## 12. C12 – Pivot Asset Snapshot (APPROVED – 2026-11-15)

- Lưu cả `asset_tag` và `asset_name` snapshot trên `server_room_incident_assets` và `server_room_maintenance_assets` tại thời điểm liên kết.
- Snapshot không bị ghi đè khi Asset gốc thay đổi tên/mã; dùng cho báo cáo lịch sử.
- Cần chốt kiểu dữ liệu/độ dài/nullability trong Final Schema Review và kiểm thử tenant + snapshot consistency.


## 13. C13 – Pivot Room Scope (APPROVED – 2026-11-15)

- Incident có thể liên kết Asset thuộc nhiều Server Room trong cùng tenant.
- Maintenance mặc định giới hạn Asset trong cùng Server Room; đa phòng phải khai báo phạm vi rõ ràng.
- Service phải xác minh tenant và room scope; bổ sung negative tests cho cross-tenant và room mismatch.
- Không phát sinh bảng mới từ quyết định này; cấu trúc khai báo scope đa phòng của Maintenance phải được đối chiếu trong Final Schema Review.


## 14. C14 – Customer Permission Revoke (APPROVED – 2026-11-15)

- Thu hồi bằng `revoked_at`/`revoked_by`; không hard-delete grant.
- Cấp lại tạo row mới; giữ nguyên lịch sử các lần grant/revoke.
- Chỉ một grant active cho `(customer_id, user_id, permission_code)`.
- Chốt cách enforce unique active sau khi kiểm tra MySQL version và schema/index hiện hữu; generated-column strategy chỉ là một lựa chọn, không mặc định.
- Kiểm thử concurrency để bảo đảm hai yêu cầu cấp quyền đồng thời không tạo hai active grant.


## 15. C15 – Customer User Room Assignment Uniqueness (APPROVED – 2026-11-15)

- Một assignment active duy nhất cho `(customer_id, user_id, server_room_id)`.
- Revoke bằng `revoked_at`/`revoked_by`, không hard-delete; cấp lại tạo row mới.
- Cơ chế unique active phải dựa trên kiểm tra MySQL version và schema/index thực tế; kiểm thử concurrency để chống cấp trùng.


## 16. C16 – Customer User Tenant Integrity (APPROVED – 2026-11-15)

- Bắt buộc `account_type='customer'` và `service_customer_id=customer_id`.
- Kiểm tra schema users hiện hữu trước khi quyết định composite FK/candidate key.
- Nếu FK ghép không khả thi, Service/Policy tenant check là bắt buộc, kèm negative tests cross-tenant cho mọi loại thao tác.
- Không tự ý thay đổi bảng users hoặc tạo migration trước khi rà soát schema thực tế.


## 17. C17 – Customer IT Admin Scope (APPROVED – 2026-11-15)

- Giới hạn grant/revoke Customer User và room assignment trong cùng tenant.
- Chỉ cấp permission SRM thuộc allowlist; không cho cấp quyền Internal/Platform hoặc tự nâng quyền.
- Audit đầy đủ grant/revoke và room assignment.
- Kiểm tra tenant ownership server-side; có negative tests cross-tenant và privilege escalation.


## 18. C18 – Customer User Disable / Tenant Change (APPROVED – 2026-11-15)

- Disabled/inactive Customer User: deny all SRM access immediately; preserve grant and room-assignment history.
- Tenant change: revoke all old-tenant SRM permissions and room assignments; never auto-transfer rights to new tenant.
- Reactivation/new tenant: grant only through the target tenant's normal authorization workflow.
- Lifecycle change and revocations must be consistent/transactional with rollback on failure; audit lifecycle, revoke and denied-access events.
- Inspect existing User lifecycle hooks and schema before deciding implementation; do not assume event infrastructure.


## D01 – Database Engine / Version Baseline (APPROVED – 2026-11-15)

- Baseline: MySQL 8.0.
- Xác minh runtime version 8.x cụ thể, SQL mode, charset/collation, storage engine và schema trước khi chốt chi tiết migration.
- Không giả định tương thích MariaDB/MySQL 5.7.


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
