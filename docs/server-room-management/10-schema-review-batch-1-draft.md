# SRM – Schema Review Batch 1 (7 bảng nền tảng)

> Đối chiếu Architecture, Data Dictionary, ERD và Column Schema Batch 1. Đây là biên bản review thiết kế, chưa phải phê duyệt migration.

## 1. Kết luận tổng quan

Bảy bảng nền tảng phù hợp với kiến trúc đã thống nhất: service_customers là tenant gốc; Location → Room; Room chứa Contacts, Assets, Access Persons; Checklist Template → Items. SRM tiếp tục quản lý thiết bị hạ tầng riêng, không tạo liên kết ITAM giả định.

Batch 1 chưa đủ điều kiện chuyển thẳng thành migration. Cần chốt composite FK, branch_id tenant integrity, Rack/U-position, soft-delete và unique code, user/actor ownership, checklist versioning và miền giá trị.

## 2. Review theo từng bảng

### 2.1 server_room_locations

**Đã nhất quán**
- customer_id là tenant bắt buộc; branch_id nullable.
- location_type: branch/head_office/data_center/independent.
- Data Center là nhà cung cấp/địa điểm, không phải tenant.
- Unique (customer_id, code); soft delete.

**Cần chốt**
1. location_type=branch phải có branch_id. Đề xuất ngược lại: location_type khác branch thì branch_id phải NULL để tránh ngữ nghĩa mơ hồ.
2. FK branch_id đơn lẻ không chứng minh branch thuộc cùng customer_id. Ưu tiên candidate key (id, customer_id) trên customer_branches và composite FK (branch_id, customer_id); hoặc service validation có test âm tính. Không tự thêm unique vào bảng hiện hữu khi chưa kiểm tra schema/dữ liệu.
3. Unique (customer_id, code) cùng soft delete sẽ ngăn tái sử dụng code sau xóa mềm. Đây là đề xuất chưa được xác nhận.
4. status active/inactive/archived cần phân biệt với deleted_at; tránh hai cơ chế archive/delete không có quy tắc.
5. provider_name hiện là snapshot text; chưa phải vendor directory.

### 2.2 server_rooms

**Đã nhất quán**
- Room thuộc duy nhất một Location và một customer.
- Unique (customer_id, code); room không chuyển tenant.
- Soft delete; hạn chế xóa khi còn hồ sơ.

**Cần chốt**
1. Cần UNIQUE (id, customer_id) để bảng con tham chiếu composite FK.
2. Ưu tiên composite FK (location_id, customer_id) → server_room_locations(id, customer_id).
3. status active/inactive/maintenance/archived phải có định nghĩa; archived không mặc nhiên đồng nghĩa deleted_at.
4. area_sqm cần quy tắc >=0 hay >0.
5. created_by FK users chỉ chứng minh user tồn tại, không chứng minh user có quyền tạo room cho tenant đó.

### 2.3 server_room_contacts

**Đã nhất quán**
- Contact thuộc room cùng tenant.
- user_id nullable; thông tin liên hệ vẫn có thể là snapshot của người ngoài hệ thống.

**Cần chốt**
1. Composite FK (server_room_id, customer_id) → rooms(id, customer_id).
2. user_id được liên kết internal user, customer user cùng tenant, hay cả hai? FK users.id đơn lẻ không bảo đảm tenant.
3. Một hay nhiều primary contact trong một room chưa chốt. Đề xuất tối đa một primary contact đang active; cần generated column/transaction locking phù hợp, không dùng unique đơn giản trên (room,is_primary).
4. Khi user đổi tên/email/phone, contact giữ snapshot hay tự đồng bộ? Chưa có quy tắc.
5. role là text tự do hay allowlist danh mục?

### 2.4 server_room_assets

**Đã nhất quán**
- SRM quản lý hạ tầng độc lập ITAM hiện tại.
- Rack là asset category=rack; thiết bị tham chiếu rack_asset_id.
- Có rack U start/height/total U; management_ip hỗ trợ IPv4/IPv6.
- Unique (customer_id, asset_tag).

**Cần chốt**
1. Candidate key (id, customer_id, server_room_id) để enforce asset, room và tenant đồng thời.
2. Composite FK (server_room_id, customer_id) → rooms(id, customer_id).
3. rack_asset_id cần composite self-FK (rack_asset_id, customer_id, server_room_id) → assets(id, customer_id, server_room_id), không chỉ FK id đơn.
4. Rack không được tự tham chiếu hoặc làm rack con của Rack khác. Chỉ asset không phải Rack mới được có rack_asset_id.
5. U-position: rack_u_start >= 1; rack_u_height >= 1; rack_total_u >= 1; rack_u_start + rack_u_height - 1 <= rack_total_u. Trùng U phải khóa Rack trong transaction để chống race condition.
6. Cần xác nhận thiết bị không lắp Rack được phép rack_asset_id=NULL hay không; thiết kế hiện tại cho phép.
7. asset_tag unique cùng soft delete cấm tái sử dụng mã sau deleted_at; cần chốt cùng location/room.
8. serial_number hiện không unique; chấp nhận trùng/null.
9. retired nên là trạng thái nghiệp vụ; deleted_at là xóa mềm/ẩn logic, cần phân biệt.
10. Khi hoàn tất movement, cập nhật room/rack/U của asset cùng transaction với movement.

### 2.5 server_room_access_persons

**Đã nhất quán**
- Đây là danh sách người được phép vào phòng, không phải nhật ký lượt vào.
- Người ngoài hệ thống lưu snapshot; user_id nullable.
- approval_status gồm draft/pending_approval/approved/rejected/revoked/expired.

**Cần chốt**
1. Composite FK (server_room_id, customer_id) → rooms(id, customer_id).
2. user_id có thể là internal user hoặc customer user? Nếu customer user thì phải cùng tenant; nếu internal user thì phải có quyền phụ trách tenant.
3. access_level permanent/supervised có đúng là hai giá trị duy nhất và ý nghĩa vận hành ra sao?
4. valid_from/valid_until có cho phép NULL cả hai? Nếu có valid_until thì phải >= valid_from.
5. expired có được cập nhật bằng scheduler hay chỉ suy ra từ valid_until khi kiểm tra quyền?
6. Khi approved, approved_by/approved_at có bắt buộc không? Khi revoked, revoked_at cần đi cùng status=revoked.
7. Có cần revoked_by/revoke_reason? Schema hiện chưa có.
8. Không hard delete bản ghi đã được dùng trong access logs.

### 2.6 srm_checklist_templates

**Đã nhất quán**
- Template thuộc một customer, có code/version và trạng thái.
- Unique (customer_id, code, version).
- Phiên bản approved không sửa nội dung; thay đổi tạo version mới.

**Cần chốt**
1. Thiết kế hiện tại hiểu mỗi version là một row riêng cùng code; thao tác clone tạo row mới.
2. Có cần chỉ một phiên bản hiệu lực cho cùng code tại một thời điểm? Nếu thời gian hiệu lực chồng lấn, phải định nghĩa cách chọn version khi tạo inspection.
3. Batch 1 có status rejected nhưng Data Dictionary cũ chưa liệt kê; cần đồng bộ Data Dictionary/State Machine.
4. effective_until phải >= effective_from.
5. created_by/approved_by cần authorization theo quyền và tenant; FK users.id không đủ.
6. Template đã được inspection sử dụng không hard delete; cần retention/archived policy.

### 2.7 srm_checklist_items

**Đã nhất quán**
- Có customer_id và template_id; composite FK (template_id, customer_id).
- Unique (template_id, item_code).
- Inspection Result lưu snapshot; item nguồn có thể archive.

**Cần chốt**
1. Candidate key (id, customer_id) nếu Inspection Results cần composite FK tới checklist item.
2. Mỗi item thuộc đúng một version; clone version phải clone toàn bộ item với ID mới.
3. Không sửa item thuộc template approved làm thay đổi lịch sử.
4. is_active=0 khác xóa item; inspection mới bỏ qua item inactive, inspection cũ giữ snapshot.
5. response_type chưa hỗ trợ multi_select; phải chốt trước migration vì ảnh hưởng result schema.
6. Chốt default: is_required=1, require_evidence=0, sort_order=0, is_active=1.

## 3. Ma trận quan hệ tenant

| Bảng con | Quan hệ | Ràng buộc mong muốn |
|---|---|---|
| locations | customer_id → service_customers | FK customer |
| locations | branch_id + customer_id → customer_branches | Composite FK nếu có candidate key |
| rooms | location_id + customer_id → locations | Composite FK |
| contacts | server_room_id + customer_id → rooms | Composite FK |
| assets | server_room_id + customer_id → rooms | Composite FK |
| assets | rack_asset_id + customer_id + server_room_id → assets | Composite self-FK |
| access_persons | server_room_id + customer_id → rooms | Composite FK |
| checklist_templates | customer_id → service_customers | FK customer |
| checklist_items | template_id + customer_id → templates | Composite FK |

Các user/actor fields còn cần authorization theo account type, tenant và permission; FK users.id chỉ kiểm tra user tồn tại.

## 4. Quyết định cần chủ dự án xác nhận

1. Code location/room/asset có được tái sử dụng sau soft delete không? Đề xuất: không.
2. Một phòng chỉ có một primary contact hay nhiều? Đề xuất: tối đa một primary contact đang active.
3. Contact/access person user_id được phép liên kết internal user, customer user cùng tenant, hay cả hai?
4. access_level có đúng permanent/supervised không?
5. Có cần revoked_by/revoke_reason cho access person không?
6. Checklist có cần response_type multi_select không?
7. Version mới của template có được tạo bằng clone toàn bộ item? Đề xuất: có.
8. Có yêu cầu chỉ một template version hiệu lực cho mỗi code tại một thời điểm không?
9. archived, retired, inactive và deleted_at được phân biệt thế nào ở từng bảng?

## 5. Điều kiện kỹ thuật trước migration

- [ ] Kiểm tra schema thật của customer_branches, users, service_customers và engine/collation.
- [ ] Xác minh candidate keys/unique indexes cần thiết trên bảng hiện hữu; không thêm unique khi chưa kiểm tra dữ liệu.
- [ ] Chốt composite FK và thứ tự cột/index.
- [ ] Chốt soft delete + unique code strategy.
- [ ] Chốt Rack composite self-FK và transaction lock cho U collision.
- [ ] Chốt quyền liên kết user/actor theo tenant.
- [ ] Chốt checklist version lifecycle và response types.
- [ ] Viết test tenant mismatch, cross-room rack, overlapping U, invalid status và soft-delete uniqueness.

## 6. Kết luận

Batch 1 đã được rà soát ở mức thiết kế nhưng chưa đủ điều kiện migration. Cần chủ dự án xác nhận mục 4 và kiểm tra schema database thực tế trước khi phê duyệt.
