# SRM – Quy tắc toàn vẹn dữ liệu và State Machine (bản thiết kế)

> Trạng thái: DRAFT. Đây là quy tắc thiết kế để chốt trước migration. Không có thay đổi database hoặc application code trong commit này.

## 1. Phạm vi và nguyên tắc nền tảng

SRM là multi-tenant. Tenant gốc là service_customers. Mọi bảng nghiệp vụ SRM có customer_id trực tiếp hoặc phải suy ra tenant qua quan hệ cha đã được kiểm tra.

Các lớp kiểm tra bắt buộc:
1. Authentication: user đang đăng nhập và active.
2. Functional authorization: user có permission tương ứng.
3. Tenant authorization: bản ghi thuộc tenant mà user được phép truy cập.
4. Resource scope: customer user chỉ truy cập server room được gán.
5. State authorization: hành động hợp lệ với trạng thái hiện tại.
6. Audit: ghi lại thay đổi có ý nghĩa nghiệp vụ.

Không coi việc ẩn nút trên giao diện là kiểm soát bảo mật. Tất cả phải được kiểm tra lại ở backend.

## 2. Quy tắc tenant integrity

### 2.1 Tenant root

- service_customers là tenant root.
- server_room_locations.customer_id bắt buộc tham chiếu service_customers.id.
- server_rooms.customer_id phải bằng server_room_locations.customer_id.
- customer_branches chỉ được tham chiếu nếu customer_branches.customer_id trùng server_room_locations.customer_id.
- Customer user chỉ có service_customer_id của chính mình.
- Internal user được truy cập tenant theo permission và quan hệ phân công; không tự động có toàn quyền chỉ vì account_type=internal.

### 2.2 Quy tắc quan hệ cha-con

Mọi thao tác tạo/cập nhật quan hệ phải kiểm tra tenant của tất cả các đầu quan hệ. Ví dụ:
- Asset và server_room phải cùng customer_id.
- Inspection và server_room phải cùng customer_id.
- Inspection.template_id phải là template thuộc cùng customer_id.
- Incident và asset trong server_room_incident_assets phải cùng customer_id; nếu nghiệp vụ yêu cầu asset cùng phòng thì phải kiểm tra thêm server_room_id.
- Maintenance và asset trong server_room_maintenance_assets phải cùng customer_id.
- Movement.from_server_room_id, to_server_room_id và asset_id phải cùng customer_id.
- Approval/attachment/audit phải trỏ đến đối tượng nằm trong cùng customer_id.
- customer_user_srm_permissions.customer_id phải bằng users.service_customer_id.
- customer_user_server_rooms.customer_id phải bằng users.service_customer_id và server_rooms.customer_id.

### 2.3 Composite FK và kiểm tra service layer

Khuyến nghị dùng composite FK ở các quan hệ trọng yếu khi schema MySQL hỗ trợ và migration không gây xung đột:
- Parent có UNIQUE (id, customer_id).
- Child có FK (parent_id, customer_id) → parent(id, customer_id).

Đối với branch mapping, có thể cần UNIQUE (id, customer_id) trên customer_branches trước khi tạo composite FK. Không tự ý sửa bảng hiện hữu; cần kiểm tra index/duplicate và đánh giá tác động.

Polymorphic relations (approval, attachment, audit) không thể có FK trực tiếp đến nhiều bảng. Bắt buộc dùng allowlist loại đối tượng và kiểm tra record + customer_id ở service layer.

Nếu một quan hệ không thể enforce bằng FK ghép, phải có:
- Form Request validation;
- Policy/authorization;
- Service-layer tenant check trong transaction;
- Test âm tính xác minh không thể tham chiếu record tenant khác.

### 2.4 Customer room assignment

Customer user chỉ được xem/thao tác phòng có assignment còn hiệu lực trong customer_user_server_rooms.
- Không assignment: deny.
- Assignment bị thu hồi: deny ngay.
- Room thuộc tenant khác: deny dù user có permission_code phù hợp.
- Customer IT Admin chỉ gán user và room cùng tenant.
- Permission srm.customer_users.manage chỉ cho phép quản trị user SRM trong tenant; không được tự cấp quyền này cho người khác.
- Không sử dụng can_access_all_branches để bỏ qua assignment phòng Server.

### 2.5 Tính duy nhất và dữ liệu lịch sử

- Mã location: unique theo customer_id.
- Mã room: unique theo customer_id.
- Asset tag: unique theo customer_id.
- Checklist code + version: unique theo customer_id.
- Inspection code, incident code, maintenance request code, movement code: unique theo customer_id.
- Với bảng assignment có thu hồi và cấp lại, không hard-delete lịch sử. Cần unique active assignment; MySQL không có partial unique index theo cách phổ biến, nên cân nhắc generated column active_key hoặc tách bảng hiện trạng và bảng lịch sử. Chọn cách cuối cùng ở migration design.
- Unique phải xét soft delete: nếu cho phép tái sử dụng code sau soft delete thì cần chiến lược unique phù hợp; không mặc định unique hiện tại tự bỏ qua bản ghi soft-deleted.

## 3. Quy tắc Rack và U-position

Rack là một server_room_assets row có category=rack.
- Rack phải thuộc cùng customer_id và server_room_id với thiết bị.
- Rack không thể tự tham chiếu chính nó.
- Không cho phép Rack lồng trong Rack ở phiên bản đầu.
- rack_total_u chỉ áp dụng cho category=rack.
- Thiết bị gắn Rack cần rack_u_start và rack_u_height hợp lệ.
- Điều kiện giới hạn: rack_u_start >= 1; rack_u_height >= 1; rack_u_start + rack_u_height - 1 <= rack_total_u.
- Không được có hai thiết bị active chồng lấn U trong cùng Rack.
- Kiểm tra trùng U phải chạy trong DB transaction và khóa phù hợp để tránh hai request đồng thời cùng chiếm một U.
- Thiết bị không gắn Rack có rack_asset_id/rack_u_start/rack_u_height NULL.

Chưa cần Rack layout 2D/3D trong schema đầu tiên. Có thể bổ sung sau mà không thay đổi mô hình tài sản cơ bản.

## 4. Equipment Movement

### 4.1 Cấu trúc đích

Không dùng một server_room_id duy nhất cho movement relocate. Cấu trúc đích:
- customer_id
- asset_id nullable (thiết bị ngoài danh mục có thể null)
- from_server_room_id nullable
- to_server_room_id nullable
- movement_code
- movement_type: check_in / check_out / relocate
- Rack/U nguồn và đích bằng trường có cấu trúc (`from_rack_asset_id`, `from_rack_u_start`, `to_rack_asset_id`, `to_rack_u_start`); snapshot thiết bị, reason, `requested_at`/`executed_at` và actors.

### 4.2 Quy tắc theo movement_type

| Type | From room | To room | Ý nghĩa |
|---|---|---|---|
| check_in | NULL | Bắt buộc | Đưa thiết bị vào quản lý tại phòng |
| check_out | Bắt buộc | NULL | Đưa thiết bị ra khỏi phòng |
| relocate | Bắt buộc | Bắt buộc | Chuyển phòng hoặc đổi vị trí Rack/U |

- from và to phải cùng tenant.
- Với relocate giữa hai phòng, from != to.
- Nếu chỉ đổi Rack/U trong cùng phòng, from = to và vị trí Rack/U nguồn/đích phải được snapshot.
- Khi phiếu được approve/confirm, cập nhật vị trí hiện tại của asset trong cùng transaction với việc chuyển movement sang trạng thái hoàn tất.
- Không cập nhật asset location khi phiếu còn draft/pending_approval.
- Movement đã hoàn tất không được sửa/xóa; sai sót phải lập phiếu điều chỉnh có tham chiếu phiếu cũ.

## 5. State Machine – trạng thái và chuyển trạng thái

Các trạng thái dưới đây là đề xuất chuẩn hóa. Tên cuối cùng sẽ dùng thống nhất trong migration, model, validation và UI.

### 5.1 Access Person – quyền được phép vào phòng

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| draft | submit | pending_approval | Người tạo có quyền |
| pending_approval | approve | approved | Người duyệt có quyền; không tự duyệt |
| pending_approval | reject | rejected | Lý do từ chối |
| approved | revoke | revoked | Người có quyền; lưu lý do/người/thời điểm |
| approved | expire | expired | valid_until đã qua |
| rejected | resubmit | pending_approval | Tạo lần gửi lại có audit |

Không đồng nhất access person (quyền được phép) với access log (lượt vào thực tế).

### 5.2 Access Log – lượt ra vào thực tế

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| registered | check_in | checked_in | Được phép vào hoặc có phê duyệt ngoại lệ |
| checked_in | check_out | completed | Ghi exit_at |
| registered | cancel | cancelled | Chưa check-in |
| checked_in | exception_close | completed | Chỉ người có quyền; bắt buộc reason |

Không cho check-in nếu access person đã expired/revoked, trừ khi có emergency approval được lưu vết.

### 5.3 Checklist Template

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| draft | submit | pending_approval | Có ít nhất một item hợp lệ |
| pending_approval | approve | approved | Người duyệt khác người tạo |
| pending_approval | reject | rejected | Bắt buộc comment |
| approved | supersede | inactive | Có version mới thay thế |

Version đã approved không sửa nội dung. Sửa checklist phải tạo version mới; inspection cũ giữ snapshot.

### 5.4 Inspection

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| draft | submit | submitted | Đủ item bắt buộc |
| submitted | review_pass | reviewed | Reviewer có quyền |
| submitted | review_return | draft/correction_required | Có lý do; giữ revision/audit |
| reviewed | close | closed | Kết luận và bằng chứng đủ |
| draft/submitted | cancel | cancelled | Theo quyền và lý do |

Inspection submitted không được update trực tiếp như bản nháp. Nếu cần chỉnh sửa, tạo correction/revision và giữ lịch sử kết quả trước đó.

### 5.5 Incident

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| open | assign | in_progress | Có người xử lý |
| in_progress | resolve | resolved | Có root cause/action/result |
| resolved | verify_close | closed | Người xác minh có quyền |
| resolved | reopen | in_progress | Có lý do |
| open/in_progress | cancel | cancelled | Chỉ khi ghi lý do và được phép |

Sự cố nghiêm trọng có thể cần escalation; severity và SLA sẽ được định nghĩa ở nghiệp vụ, không hard-code vào state transition.

### 5.6 Maintenance Request

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| draft | submit | pending_approval | Đủ mô tả, thời gian, impact, rollback |
| pending_approval | approve | approved | Approver hợp lệ; không self-approve |
| pending_approval | reject | rejected | Có lý do |
| approved | start | in_progress | Đến thời gian/được phép bắt đầu |
| in_progress | complete | completed | Có actual time và kết quả |
| completed | verify | verified | Người xác minh phù hợp |
| pending_approval/approved | cancel | cancelled | Theo quyền và lý do |

Emergency maintenance là nhánh riêng: cho phép bắt đầu trước approval khi có quyền emergency; phải ghi lý do, người kích hoạt, thời điểm và yêu cầu hậu kiểm. Không bỏ qua audit.

### 5.7 Equipment Movement

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| draft | submit | pending_approval | From/to và asset snapshot hợp lệ |
| pending_approval | approve | approved | Approver có quyền |
| pending_approval | reject | rejected | Có lý do |
| approved | execute | completed | Kiểm tra Asset ở đúng phòng nguồn; row lock; cập nhật Asset và Movement trong cùng transaction; set performed_by/executed_at |
| draft/pending_approval/approved | cancel | cancelled | Chưa execute |

Movement completed là hồ sơ bất biến; điều chỉnh bằng phiếu mới. Khi check_out, Asset chuyển sang `checked_out` (hoặc trạng thái tương đương trong allowlist), không xóa Asset Record; check_in lại bằng Movement mới. Execute kiểm tra source room và dùng transaction + row-level lock để chống double execution.

### 5.8 Approval record

| Current | Action | Next | Điều kiện |
|---|---|---|---|
| pending | approve | approved | Đúng approver và đúng step |
| pending | reject | rejected | Comment/lý do theo policy |
| pending | reassign | pending | Ghi người giao lại và lý do |
| pending | skip | skipped | Chỉ workflow cho phép |

Approval của một hồ sơ không được coi là hồ sơ nghiệp vụ đã hoàn tất nếu các bước tiếp theo còn pending. Chỉ cập nhật trạng thái cha khi workflow xác định đủ điều kiện.

## 6. Approval workflow – nguyên tắc thực thi

- Tách permission tạo/submit khỏi permission approve.
- Các cấp phê duyệt chạy tuần tự; cấp sau chỉ được kích hoạt khi cấp trước hoàn tất.
- Trong từng cấp, áp dụng ALL: tất cả approver thuộc danh sách hiệu lực phải APPROVE mới được chuyển cấp; một REJECT làm dừng workflow và từ chối yêu cầu.
- Khi một cấp được kích hoạt, resolve group/role thành user cụ thể và snapshot danh sách approver của cấp đó. Membership thay đổi sau snapshot không tự sửa danh sách đang xử lý.
- Cấp kế tiếp được resolve/snapshot khi đến lượt kích hoạt.
- Tại thời điểm quyết định phải kiểm tra người thực hiện active, có quyền phù hợp và thuộc tenant/scope hợp lệ; mặc định cấm self-approval.
- Delegation phải có thời hạn/phạm vi, được người có thẩm quyền chấp thuận và lưu cả approver gốc lẫn acting approver.
- Reassignment chỉ do người có thẩm quyền workflow thực hiện; lưu approver cũ/mới, actor, thời điểm và lý do. Người thay thế tiếp nhận nghĩa vụ của vị trí được thay, không làm giảm điều kiện ALL.
- Mọi quyết định, delegation, thu hồi delegation và reassignment phải được Audit; approval history không được ghi đè.
- Approval decision/reassignment phải chạy trong transaction và chống double-submit.
- Không cho request client truyền approvable_type tùy ý; dùng allowlist model.
- Các quyết định phải chạy trong transaction, khóa hồ sơ cần thiết và chống double-submit.

## 7. Delete, Retention và lịch sử

- Hồ sơ nghiệp vụ đã submit/approved/completed/closed không hard delete.
- Dùng trạng thái cancelled/revoked/expired/archived để kết thúc vòng đời.
- Draft chưa submit có thể cho phép xóa theo permission và policy, nhưng audit thao tác xóa.
- Access log, inspection result, approval, movement completed, audit log là dữ liệu lịch sử; ưu tiên RESTRICT/append-only.
- Attachment xóa file vật lý phải theo retention policy và phải kiểm tra tham chiếu trước.
- Soft delete không thay thế audit log và không tự giải quyết unique code.

## 8. Các quyết định còn chờ

2. Có cho phép emergency maintenance không; vai trò nào có quyền kích hoạt và thời hạn hậu kiểm.
3. Inspection review_return: trả về draft hay trạng thái correction_required riêng.
4. Có cho phép ngoại lệ self-approval trong tenant nhỏ không. Khuyến nghị tiếp tục cấm.
5. Chính sách retention theo loại hồ sơ và file.
6. Chọn cách enforce unique active room assignment khi cấp lại sau revoke.

## 9. Checklist kỹ thuật trước migration

- [ ] Chốt toàn bộ tên bảng/cột/status.
- [ ] Chốt FK và composite FK tenant.
- [ ] Kiểm tra các bảng hiện hữu trước khi thêm unique/index.
- [ ] Chốt delete/soft delete/retention.
- [ ] Chốt unique code khi soft delete.
- [ ] Chốt cơ chế assignment cấp lại sau revoke.
- [ ] Chốt workflow ANY/ALL và emergency.
- [ ] Viết test tenant isolation cho mọi endpoint.
- [ ] Viết test state transition và SoD.
- [ ] Viết test race condition cho Rack U và movement.


## 10. Nguyên tắc bổ sung đã được phê duyệt – Rack và xóa dữ liệu

### 10.1. Liên kết Asset với Rack

- Asset có `category=rack` là Rack; Rack không được gắn vào một Rack khác.
- Thiết bị có thể không thuộc Rack; khi không lắp trong Rack, `rack_asset_id = NULL`.
- Nếu thiết bị có `rack_asset_id`, Rack được tham chiếu phải thuộc cùng Server Room.
- Không cho phép Rack tự tham chiếu chính nó hoặc tạo vòng lặp Rack lồng nhau.
- Trước khi Soft Delete Rack, phải xử lý các thiết bị đang gắn Rack; không để lại liên kết mồ côi hoặc trạng thái không nhất quán.

### 10.2. Xóa Location, Server Room và Asset

- Location, Server Room và Asset sử dụng Soft Delete; không Hard Delete dữ liệu đã phát sinh nghiệp vụ.
- Không cho Soft Delete Location khi còn Server Room đang hoạt động bên trong.
- Không cho Soft Delete Server Room khi còn Asset đang hoạt động hoặc nghiệp vụ chưa hoàn tất.
- Asset đã có lịch sử kiểm tra, sự cố, bảo trì hoặc di chuyển phải được giữ lại phục vụ Audit.
- Trước khi xóa mềm phải kiểm tra quan hệ con và nghiệp vụ đang mở; thao tác phải được kiểm soát bằng Service/Policy và transaction phù hợp.


## 11. Approval decisions đã được phê duyệt – C04 đến C06

- **C04 – APPROVED (2026-11-15):** Phê duyệt tuần tự theo cấp; tại mỗi cấp tất cả approver phải APPROVE mới chuyển cấp. Một REJECT làm dừng workflow và từ chối yêu cầu.
- **C05 – APPROVED (2026-11-15):** Resolve group/role thành user cụ thể và snapshot danh sách approver khi từng cấp được kích hoạt; thay đổi membership sau đó không tự thay đổi cấp đang xử lý.
- **C06 – APPROVED (2026-11-15):** Hỗ trợ Delegation và Reassignment có kiểm soát. Delegation phải được người có thẩm quyền chấp thuận, có phạm vi/thời hạn và lưu principal/acting approver. Reassignment do người có thẩm quyền workflow thực hiện, lưu old/new approver, actor, time, reason. Tất cả thao tác phải Audit và không làm giảm quy tắc ALL.


## Bổ sung – State/Integrity cho Approval Delegation (C06)

- Delegation lifecycle: pending → approved/rejected; approved → revoked/expired. Không cho delegate thực hiện trước trạng thái approved hoặc ngoài valid_from/valid_until.
- Khi activate một approval level: resolve principal list theo C05; kiểm tra delegation theo tenant + principal + approvable_type + room scope + thời gian; snapshot acting_approver_id và delegation_id vào từng approval row.
- Một cấp đã kích hoạt giữ nguyên snapshot kể cả delegation bị thu hồi/hết hạn sau đó. Cấp chưa kích hoạt phải kiểm tra lại theo trạng thái và thời gian mới nhất.
- Không cho phép hai delegation đang hiệu lực cùng khớp một principal và cùng scope tại thời điểm activate; nếu xung đột, transaction không kích hoạt cấp và phát sinh lỗi nghiệp vụ cần xử lý.
- Khi quyết định: acted_by phải là acting_approver_id đã snapshot (hoặc principal nếu không delegation); approver_id luôn giữ principal gốc. Kiểm tra user active, tenant, permission và trạng thái pending trong transaction.
- Audit ghi delegation_id, principal_approver_id, acting_approver_id, action, actor, timestamp và reason phù hợp.


## 12. C07 – Integrity và SoD cho Approval (APPROVED – 2026-11-15)

- Tại thời điểm kích hoạt từng approval level, đối chiếu requester_id của hồ sơ với danh sách principal approver đã resolve theo C05.
- Nếu trùng requester, loại khỏi active approver snapshot và ghi audit event/reason; không sửa membership nguồn.
- Nếu danh sách hiệu lực rỗng, rollback việc activate level và trả lỗi nghiệp vụ yêu cầu workflow configuration/reassignment; tuyệt đối không chuyển cấp hoặc skip ngầm.
- Khi áp dụng delegation, kiểm tra acting approver sau bước resolve delegation. Acting approver không được là requester; nếu vi phạm, không cho quyết định được ghi nhận.
- Reassignment chỉ do workflow manager có quyền thực hiện, phải lưu old/new approver, actor, timestamp, reason; sau reassignment vẫn phải thỏa ALL.
- Test bắt buộc: requester nằm trong approver list; requester bị loại khiến list rỗng; delegate là requester; reassignment hợp lệ; không thể approve bằng API giả mạo actor.

## 13. C08 – Attachment scan state và integrity (APPROVED – 2026-11-15)

| Current | Event | Next | Điều kiện |
|---|---|---|---|
| pending | scan_clean | clean | Scanner xác nhận file an toàn; ghi `scanned_at` |
| pending | scan_infected | infected | Scanner phát hiện mã độc; cách ly, cấm preview/download |
| pending | scan_error | error | Scanner lỗi/không xác định được kết quả; fail closed, cấm preview/download |
| error | rescan | pending | Chỉ service bảo mật được phép yêu cầu quét lại |

- File upload phải được giữ trong private quarantine cho tới khi scan hoàn tất.
- Chỉ `scan_status=clean` mới được preview/download. Không được coi pending, lỗi scanner hoặc timeout là clean.
- MIME phải xác minh từ nội dung; allowlist và dung lượng là cấu hình server-side.
- Mỗi lần scan cập nhật trạng thái/thời điểm và ghi audit; không cho client tự gửi `scan_status`.
- Upload/download/delete đều kiểm tra tenant, parent record và authorization; `file_path` không được trả ra API công khai.
- Không xóa file nhiễm mã độc tự động nếu chưa xác định retention/security handling; cách ly và xử lý theo quy trình được phê duyệt.

## 14. C09 – Attachment retention và Legal Hold (APPROVED – 2026-11-15)

- Không cascade delete và không xóa file tự động khi parent record Archived hoặc Soft Deleted.
- Retention phải cấu hình theo loại hồ sơ/tài liệu và policy đã phê duyệt; không hard-code thời hạn khi policy chưa xác định.
- Legal Hold được quản lý tại parent record; toàn bộ attachment của parent tự động kế thừa trạng thái bảo vệ. Không đặt Legal Hold độc lập trên từng file.
- Legal Hold tại parent chặn purge mọi attachment liên quan, kể cả khi retention của file đã hết hạn.
- Khi parent được gỡ Legal Hold, attachment quay lại quy trình xét retention; chỉ purge khi hết retention, không còn reference hợp lệ và đã ghi Audit actor/thời điểm/kết quả.
- Schema lưu Legal Hold tại parent và cơ chế scheduler xác định attachment theo parent sẽ được chốt trong Final Schema Review; chưa tự ý thêm schema.


## 15. C09-B – Legal Hold event history (APPROVED – 2026-11-15)

- Mỗi lần đặt/gỡ Legal Hold tạo một event lịch sử riêng gắn với parent record; không ghi đè lịch sử cũ.
- Lưu action, parent identity, actor, thời điểm UTC, lý do; release event phải liên kết lần đặt hold được gỡ.
- Không hard delete event; kiểm soát quyền và Audit mọi thao tác đặt/gỡ.
- Cấu trúc bảng/cột cụ thể sẽ được chốt trong Final Schema Review.


## 16. C10 – Audit Log append-only và kiểm soát sửa/xóa (APPROVED – 2026-11-15)

- Audit Log chỉ nhận bản ghi mới từ các luồng ứng dụng; không cung cấp chức năng UPDATE/DELETE trong nghiệp vụ thông thường.
- Tài khoản DB runtime của ứng dụng không có quyền UPDATE/DELETE trên bảng `server_room_audit_logs`; kiểm tra bằng quyền thực tế và negative test.
- Tách quyền runtime khỏi quyền migration/DBA; mọi quyền đặc biệt phải theo quy trình quản trị có kiểm soát.
- Archive riêng có thể được áp dụng khi compliance yêu cầu, với quyền sửa/xóa hạn chế theo policy/hạ tầng.
- Mỗi audit event cần actor, timestamp UTC, action, target object, result và trace/correlation ID khi có.
- Cơ chế này giảm nguy cơ sửa/xóa, không bảo đảm bất biến tuyệt đối trước DBA hoặc quản trị hạ tầng đặc quyền.

## D02 – Storage Engine and Transaction Policy (APPROVED – 2026-11-15)

1. Toàn bộ 23 bảng SRM sử dụng InnoDB.
2. Các thao tác nghiệp vụ cập nhật nhiều bảng có quan hệ phải được thực hiện trong một Database Transaction phù hợp.
3. Nếu một bước bắt buộc trong transaction thất bại, rollback toàn bộ thay đổi thuộc transaction; không để lại trạng thái nghiệp vụ dở dang.
4. Không sử dụng MyISAM cho các bảng SRM.
5. Với thao tác đồng thời có nguy cơ tranh chấp dữ liệu, Service phải đánh giá và sử dụng cơ chế khóa phù hợp; `SELECT ... FOR UPDATE` được dùng khi cần khóa bản ghi để bảo vệ invariant, kết hợp transaction và thứ tự khóa nhất quán để hạn chế deadlock.
6. Các transaction phải ngắn, không giữ row lock trong lúc gọi dịch vụ mạng hoặc thực hiện tác vụ chậm; có xử lý lỗi deadlock/lock timeout phù hợp.
7. Kiểm thử phải bao gồm rollback khi lỗi giữa chừng và các tình huống concurrent update cho các invariant quan trọng.

- Các thao tác cập nhật trạng thái, tạo approval/audit liên quan và cập nhật quan hệ nhiều bảng phải xác định transaction boundary rõ ràng.


## D03 – Lock Ordering, Deadlock and Lock Wait Handling (APPROVED – 2026-11-15)

1. Các transaction có thể khóa nhiều bản ghi phải lấy khóa theo thứ tự xác định, nhất quán giữa các luồng nghiệp vụ để giảm nguy cơ deadlock.
2. Khi gặp MySQL deadlock error `1213` hoặc lock wait timeout `1205`, ứng dụng phải xử lý lỗi có kiểm soát; transaction thất bại phải rollback đúng phạm vi.
3. Cho phép retry transaction với số lần giới hạn và backoff tăng dần; số lần, thời gian chờ và ngưỡng retry phải được cấu hình/kiểm chứng trong triển khai, không retry vô hạn.
4. Chỉ retry khi toàn bộ thao tác có thể chạy lại an toàn (idempotent hoặc được bảo vệ bằng idempotency key/unique constraint và không gây side effect lặp). Không retry mù các thao tác gửi thông báo, gọi API bên ngoài hoặc phát sinh side effect ngoài transaction.
5. Side effect ngoài database nên được phát hành sau khi transaction commit (ví dụ qua cơ chế after-commit/outbox nếu cần); không giữ row lock trong khi gọi network/API.
6. Ghi log có cấu trúc cho transaction lỗi/retry: operation/correlation ID, error code, số lần retry, thời gian và kết quả; không ghi dữ liệu bí mật.
7. Kiểm thử concurrency, deadlock, lock timeout, rollback và giới hạn retry đối với các nghiệp vụ có tranh chấp dữ liệu.

- Không phát sinh side effect bên ngoài trước commit nếu không có cơ chế bù trừ/idempotency được thiết kế rõ ràng.


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

- Unique active constraints phải tương thích lifecycle revoke/restore và không làm mất lịch sử; phương án cụ thể chốt theo schema MySQL 8.0.46 và từng bảng.


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
