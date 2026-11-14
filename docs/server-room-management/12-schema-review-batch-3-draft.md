# SRM – Schema Review Batch 3 (8 bảng cuối)

> Review đối chiếu Data Dictionary, ERD, Data Integrity & State Machines và Column Schema Batch 3. Đây là review thiết kế, chưa phải phê duyệt migration.

## 1. Kết luận tổng quan

Batch 3 hoàn tất rà soát 8 bảng còn lại trong register 22 bảng: Environment Logs, Approvals, Attachments, Audit Logs, hai pivot và hai bảng customer access-control.

Các nguyên tắc chính đã rõ: log môi trường thuộc tenant/room; ba bảng approval/attachment/audit dùng polymorphic allowlist và service-layer tenant validation; hai pivot dùng composite FK tenant; customer user dùng quyền chức năng riêng và room assignment theo nguyên tắc deny-by-default.

Các điểm còn mở tập trung vào timezone/retention, approval ANY/ALL và group snapshot, bảo mật file, audit immutability, lịch sử thu hồi permission và unique active room assignment.

## 2. Review từng bảng

### 2.1 server_room_environment_logs

**Đã phù hợp**
- customer_id và server_room_id bắt buộc; composite FK (server_room_id, customer_id) → server_rooms(id, customer_id).
- Ghi nhận recorded_at, nhiệt độ, độ ẩm, trạng thái điều hòa/UPS/nguồn, source_type và source_reference.
- humidity_percent phải trong khoảng 0–100; ít nhất một trường đo/trạng thái có dữ liệu.
- Ngưỡng cảnh báo cấu hình riêng, không hard-code vào log.

**Cần chốt**
1. Data Dictionary ghi source_type manual/device/import; Batch 3 default manual. Cần chốt allowlist và default này.
2. Device/import cần cơ chế xác thực nguồn gửi, chống giả mạo và idempotency để tránh ghi trùng khi retry; source_reference đơn lẻ chưa chắc là định danh duy nhất.
3. Cần chốt timezone: lưu UTC hay local tenant. Khuyến nghị lưu UTC, hiển thị theo timezone cấu hình của customer; đây là khuyến nghị thiết kế, chưa phải quyết định.
4. Cần retention và tần suất dữ liệu; bảng log có thể tăng nhanh. Chưa có partition/archive strategy.
5. recorded_by có thể NULL với device/import; actor/service identity cần được phân biệt khỏi user account.
6. Nhiệt độ và trạng thái thiết bị cần domain/allowlist theo sensor hoặc policy; tài liệu hiện chưa quy định ngưỡng.

### 2.2 server_room_approvals

**Đã phù hợp**
- Mỗi bước duyệt gắn một hồ sơ qua approvable_type/approvable_id; status pending/approved/rejected/skipped.
- Type phải dùng alias allowlist; service phải xác thực hồ sơ tồn tại và customer_id trùng.
- approver phải active, có quyền, đúng tenant và đúng step tại thời điểm quyết định.
- Tách quyền submit khỏi quyền approve; mặc định cấm self-approval.
- Reassign và quyết định phải được audit; quyết định lưu lịch sử, không ghi đè.

**Cần chốt**
1. ANY hay ALL khi một bước có nhiều approver; áp dụng theo từng workflow hay một quy tắc toàn cục?
2. approver_group là snapshot mã nhóm/role hay nhóm động? Nếu nhóm động, cần xác định lúc giao duyệt resolve thành user cụ thể và lưu snapshot thành viên.
3. Data Dictionary chưa có assigned_by; Batch 3 đề xuất thêm. Cần phê duyệt trường này để truy vết người giao/reassign.
4. Reassign cần lưu người cũ, người mới, actor, thời điểm và lý do. Một assigned_by đơn lẻ không đủ lưu nhiều lần reassign; audit event phải ghi đầy đủ hoặc thiết kế approval_events riêng.
5. UNIQUE theo approval_step/approver chưa chốt do phụ thuộc multi-approver. Cần chống tạo trùng step/approver bằng constraint hoặc transaction.
6. comments cần bắt buộc khi rejected; skipped phải có lý do và workflow cho phép.
7. Polymorphic parent không có FK trực tiếp. Mọi thao tác tạo/đọc/decide phải qua service kiểm tra allowlist, tenant, trạng thái hồ sơ và chống double-submit.
8. Cần xác định có cần bảng workflow definition/approval events không. Không tự thêm bảng ngoài register 22 nếu chưa quyết định.

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

1. Environment Logs: timezone, retention, source authentication/idempotency, default source_type.
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
- Chưa có phê duyệt final schema và chưa tạo Laravel migrations.
