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
- source/destination snapshots, equipment snapshot, reason, requested/approved/performed timestamps và actors.

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
| approved | execute | completed | Cập nhật vị trí asset trong transaction |
| draft/pending_approval/approved | cancel | cancelled | Chưa execute |

Movement completed là hồ sơ bất biến; điều chỉnh bằng phiếu mới.

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
- Mỗi approval step có approver user cụ thể hoặc group/role được resolve thành người nhận thực tế.
- Tại thời điểm approve phải kiểm tra approver còn active, còn quyền và còn thuộc tenant phù hợp.
- Mặc định cấm self-approval.
- Một bước có nhiều approver phải chốt rõ ANY (một người duyệt) hay ALL (tất cả duyệt); không suy đoán.
- Reassign phải lưu approver cũ, approver mới, người thực hiện và lý do.
- Approval records là lịch sử; không ghi đè quyết định đã có.
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

1. Approval multi-approver: ANY hay ALL ở từng loại hồ sơ.
2. Có cho phép emergency maintenance không; vai trò nào có quyền kích hoạt và thời hạn hậu kiểm.
3. Inspection review_return: trả về draft hay trạng thái correction_required riêng.
4. Có cho phép ngoại lệ self-approval trong tenant nhỏ không. Khuyến nghị tiếp tục cấm.
5. Chính sách retention theo loại hồ sơ và file.
6. Chọn cách enforce unique active room assignment khi cấp lại sau revoke.
7. Chọn cách lưu approval group: resolve group thành approver user tại thời điểm tạo hay lưu group_id và snapshot thành viên.

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
