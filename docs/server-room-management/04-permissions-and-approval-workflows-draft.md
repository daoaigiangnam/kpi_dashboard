# SRM – Ma trận phân quyền và luồng phê duyệt (bản đề xuất)

> Trạng thái: DRAFT. Tài liệu dựa trên cấu trúc RBAC hiện hữu của nhánh feature/admin-foundation. Chưa thêm permission, migration hoặc code. Các quyết định phân quyền nền tảng đã được chủ dự án chốt.

## 1. Hiện trạng RBAC đã kiểm tra trong repository

Các thành phần hiện hữu:
- `user_groups`: nhóm người dùng, có `is_system`.
- `permissions`: danh mục quyền gồm `module`, `name`, `code`, `description`.
- `group_permissions`: liên kết nhóm và quyền.
- `users.user_group_id`: nhóm của tài khoản.
- `users.account_type`: phân biệt internal/customer.
- `users.service_customer_id`: tenant của tài khoản khách hàng.
- `users.hasPermission()`: tài khoản customer hiện chỉ được cấp một số quyền qua cờ riêng; không kế thừa quyền quản trị nội bộ.
- `UserGroup::serviceCustomers()` và `service_customer_group`: liên kết nhóm với khách hàng hiện hữu.

Do đó, SRM không tạo một hệ thống RBAC độc lập. Quyền nội bộ nên tái sử dụng `permissions` + `group_permissions` + `users.user_group_id`. Tài khoản khách hàng phải tiếp tục bị cô lập khỏi quyền nội bộ như logic hiện tại.

## 2. Nguyên tắc phân quyền

1. Quyền chức năng (có được xem/tạo/sửa/duyệt hay không) và phạm vi dữ liệu (được thao tác khách hàng/phòng nào) là hai lớp riêng.
2. Mỗi request phải kiểm tra cả permission và tenant scope.
3. Không tin `customer_id`, `server_room_id`, `approver_id` do trình duyệt gửi lên nếu chưa kiểm tra quyền.
4. Super Admin có quyền quản trị toàn hệ thống; tài khoản khách hàng tuyệt đối không được nhận quyền này.
5. Người tạo yêu cầu không tự phê duyệt chính yêu cầu đó nếu quy trình yêu cầu phân tách nhiệm vụ.
6. Mọi thay đổi quyền, phê duyệt, thu hồi quyền ra vào phải được audit.

## 3. Vai trò đề xuất

| Vai trò | Nhóm người dùng | Phạm vi dữ liệu | Trách nhiệm chính |
|---|---|---|---|
| Super Admin | Internal / Super Admin | Toàn hệ thống | Cấu hình, quản trị, hỗ trợ toàn tenant |
| SRM Platform Admin | Internal / nhóm được cấp quyền SRM platform | Toàn tenant theo quyền | Quản trị cấu hình SRM, danh mục dùng chung |
| IT Operations | Internal / nhóm IT được cấp quyền | Khách hàng được phân công hoặc được phép phụ trách | Quản lý địa điểm, phòng, thiết bị, kiểm tra, bảo trì |
| SRM Auditor/Inspector | Internal hoặc customer theo policy | Phòng được giao | Lập/ghi nhận checklist, inspection, bằng chứng |
| Customer IT Admin | Customer account | Chỉ `service_customer_id` của chính user | Quản trị SRM trong tenant khách hàng |
| Customer Approver | Customer account | Chỉ tenant khách hàng và hồ sơ được giao duyệt | Duyệt access/maintenance/inspection theo workflow |
| Customer Viewer | Customer account | Chỉ tenant khách hàng và phòng được cấp | Chỉ xem báo cáo/hồ sơ |
| Visitor/Contractor | Không nhất thiết có user account | Chỉ lượt vào được đăng ký | Được ghi nhận trong access log; không có login mặc định |

Tên vai trò là nhãn nghiệp vụ đề xuất, không bắt buộc tạo user_groups mới ngay. Có thể cấp quyền cho các group hiện hữu sau khi rà soát tên nhóm thực tế.

## 4. Ma trận quyền nội bộ đề xuất

Permission code đề xuất, lưu trong bảng `permissions` hiện hữu với `module='srm'`.

| Permission code | Ý nghĩa |
|---|---|
| `srm.dashboard.view` | Xem dashboard SRM |
| `srm.locations.view` | Xem địa điểm |
| `srm.locations.manage` | Tạo/sửa/ngừng địa điểm |
| `srm.rooms.view` | Xem phòng Server |
| `srm.rooms.manage` | Tạo/sửa/ngừng phòng |
| `srm.assets.view` | Xem thiết bị hạ tầng |
| `srm.assets.manage` | Tạo/sửa/ngừng thiết bị SRM |
| `srm.access_persons.view` | Xem danh sách người được phép vào |
| `srm.access_persons.manage` | Đề nghị/cập nhật danh sách người được phép |
| `srm.access_persons.approve` | Duyệt/thu hồi quyền ra vào |
| `srm.access_logs.view` | Xem nhật ký ra vào |
| `srm.access_logs.manage` | Ghi nhận check-in/check-out và thông tin lượt vào |
| `srm.checklists.view` | Xem Checklist |
| `srm.checklists.manage` | Tạo/sửa phiên bản Checklist |
| `srm.checklists.approve` | Duyệt và phát hành Checklist |
| `srm.inspections.view` | Xem phiếu kiểm tra |
| `srm.inspections.create` | Lập phiếu kiểm tra |
| `srm.inspections.update_draft` | Sửa phiếu còn nháp |
| `srm.inspections.review` | Rà soát kết quả kiểm tra |
| `srm.incidents.view` | Xem sự cố |
| `srm.incidents.manage` | Ghi nhận và cập nhật sự cố |
| `srm.incidents.close` | Đóng sự cố sau xác minh |
| `srm.maintenance.view` | Xem yêu cầu bảo trì |
| `srm.maintenance.create` | Tạo yêu cầu bảo trì |
| `srm.maintenance.manage` | Cập nhật công việc bảo trì |
| `srm.maintenance.approve` | Duyệt công việc bảo trì |
| `srm.movements.view` | Xem lịch sử di chuyển thiết bị |
| `srm.movements.manage` | Lập/ghi nhận phiếu di chuyển |
| `srm.environment.view` | Xem nhật ký môi trường |
| `srm.environment.manage` | Ghi nhận dữ liệu môi trường |
| `srm.reports.view` | Xem/xuất báo cáo |
| `srm.attachments.download` | Tải bằng chứng/tệp đính kèm |
| `srm.audit.view` | Xem audit log SRM |
| `srm.settings.manage` | Cấu hình SRM |

Không nên cấp mặc định tất cả các quyền này cho mọi nhóm IT. Mỗi group chỉ nhận các quyền cần thiết theo trách nhiệm.

## 5. Quyền tài khoản khách hàng – điểm thiết kế quan trọng

Hiện `User::hasPermission()` có nhánh riêng cho `account_type='customer'`, trả quyền dựa trên các cờ như `can_view_assets`, `can_create_assets`... và mặc định từ chối các quyền khác. Đây là cơ chế cô lập cần giữ.

Hai phương án thiết kế:

### Phương án A – Thêm cột boolean vào users

Ví dụ `can_view_srm`, `can_manage_srm`, `can_approve_srm`, `can_view_srm_audit`.

Ưu điểm: đơn giản, gần với cách cấp quyền khách hàng hiện tại. Nhược điểm: nhanh chóng phình bảng users; khó phân tách chi tiết theo module/action; không thuận tiện mở rộng.

### Phương án B – Bảng quyền riêng cho customer account (ĐÃ CHỌN)

Bảng đề xuất `customer_user_srm_permissions`:

| Cột | Kiểu dự kiến | Ghi chú |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| customer_id | BIGINT UNSIGNED | Tenant |
| user_id | BIGINT UNSIGNED | FK users |
| permission_code | VARCHAR(120) | Mã quyền SRM |
| granted_by | BIGINT UNSIGNED NULL | Người cấp |
| created_at | TIMESTAMP | Thời điểm cấp |
| updated_at | TIMESTAMP | Thời điểm cập nhật |

Unique: `(customer_id, user_id, permission_code)`. Index: `(user_id, customer_id)`, `(customer_id, permission_code)`.

Ràng buộc nghiệp vụ:
- User phải là `account_type='customer'`.
- `users.service_customer_id = customer_user_srm_permissions.customer_id`.
- Chỉ người có quyền quản trị tenant mới được cấp/thu hồi.
- Không cấp permission nội bộ qua bảng này.
- Không cấp `srm.platform.*` cho customer account.
- `hasPermission()` hoặc service authorization phải kiểm tra đúng tenant và trạng thái user.

Phương án B đã được chủ dự án chọn. Không triển khai phương án A.

## 6. Phạm vi dữ liệu (scope)

### Internal user
- Super Admin: toàn tenant.
- IT Operations: chỉ các khách hàng được phân công hoặc được cấp quyền theo cơ chế phân công hiện hữu.
- Không suy diễn quyền chỉ từ tên group; phải kiểm tra quan hệ phân công khách hàng và permission.

### Customer user
- Bắt buộc `account_type='customer'`.
- Bắt buộc `service_customer_id` khác NULL.
- Mọi query SRM phải thêm điều kiện `customer_id = auth()->user()->service_customer_id`.
- Customer user chỉ truy cập các phòng được gán trong customer_user_server_rooms.
- Không lấy can_access_all_branches làm quyền truy cập tất cả phòng Server.
- Phòng được gán phải thuộc đúng service_customer_id của user.
- Không cho khách hàng đổi tenant bằng cách sửa request parameter.

### Visitor/Contractor
- Không cần tạo user đăng nhập chỉ để ghi nhật ký ra vào.
- Lưu thông tin snapshot người vào, tổ chức, mục đích, người bảo lãnh/giám sát và thời gian.

### Quyền quản trị user Customer (đã chốt)

Customer IT Admin được cấp/thu hồi quyền SRM và gán phòng Server cho user thuộc cùng tenant, với các giới hạn:
- User đích phải có account_type=customer và service_customer_id trùng tenant.
- Chỉ được gán permission_code thuộc allowlist quyền SRM customer.
- Không cấp quyền platform/internal hoặc Super Admin.
- Chỉ gán phòng thuộc cùng tenant.
- Ghi audit người cấp/thu hồi, thời điểm và thay đổi.
- Đề xuất permission quản trị: srm.customer_users.manage; không được tự cấp quyền này cho user khác.

Bảng customer_user_server_rooms lưu customer_id, user_id, server_room_id, granted_by, revoked_by, revoked_at và timestamps. Không có assignment thì deny by default.

## 7. Luồng phê duyệt đề xuất

### 7.1 Cấp quyền vào phòng Server
1. Người quản lý tạo đề nghị thêm người.
2. Người có quyền kiểm tra thông tin và thời hạn.
3. Yêu cầu đi qua workflow phê duyệt tuần tự theo cấp; tại mỗi cấp, tất cả approver thuộc snapshot hiệu lực phải APPROVE mới được chuyển cấp.
4. Chỉ khi toàn bộ cấp bắt buộc đã APPROVE, access person mới chuyển trạng thái approved và có hiệu lực theo valid_from/valid_until.
5. Nếu bất kỳ approver nào REJECT, workflow dừng và yêu cầu bị từ chối.
6. Thu hồi quyền phải lưu người thu hồi, thời điểm và lý do.

### 7.2 Yêu cầu bảo trì
1. Người yêu cầu tạo phiếu, mô tả phạm vi, thời gian, thiết bị và ảnh hưởng.
2. Workflow gửi yêu cầu tuần tự theo cấp; group/role được resolve thành danh sách user cụ thể khi cấp đó đến lượt.
3. Tại mỗi cấp, tất cả approver trong snapshot phải APPROVE; chỉ sau đó mới chuyển cấp tiếp theo. Một REJECT làm dừng workflow và từ chối yêu cầu.
4. Chỉ khi tất cả cấp bắt buộc approved mới chuyển sang in_progress. Emergency Bypass không thuộc phiên bản đầu.
4. Kết thúc công việc: ghi actual time, kết quả, bằng chứng và xác minh.
5. Đóng phiếu; lưu toàn bộ lịch sử duyệt.

### 7.3 Checklist và Inspection
1. Người có quyền tạo/sửa bản nháp template.
2. Người có quyền approve phát hành phiên bản.
3. Inspector lập phiếu dựa trên template/version đang hiệu lực.
4. Submit phiếu sẽ khóa nội dung kết quả; reviewer rà soát.
5. Nếu cần sửa phiếu đã submit, dùng quy trình reopen/correction và audit, không ghi đè lịch sử.

### 7.4 Incident
1. Nhân sự có quyền ghi nhận sự cố.
2. Phân loại severity và ảnh hưởng.
3. Giao người xử lý/action.
4. Xác minh kết quả khắc phục.
5. Đóng sự cố bởi người có quyền; không xóa hồ sơ.

### 7.5 Quy tắc Approval Workflow đã được chủ dự án phê duyệt (C04–C06)

**C04 – Sequential Approval + ALL Approvers per Level**
- Các cấp phê duyệt chạy tuần tự theo thứ tự cấu hình; cấp sau chỉ được kích hoạt khi cấp trước hoàn tất.
- Trong một cấp có nhiều approver, tất cả approver thuộc danh sách hiệu lực đều phải APPROVE mới được chuyển cấp.
- Chỉ cần một approver REJECT thì workflow dừng tại cấp đó và yêu cầu bị từ chối.
- Không tự động bỏ qua cấp hoặc coi im lặng/hết thời hạn là đồng ý.

**C05 – Approver Snapshot**
- Khi một cấp được kích hoạt/giao duyệt, hệ thống resolve group/role thành danh sách user cụ thể và snapshot danh sách đó cho cấp đang xử lý.
- Thay đổi membership hoặc cơ cấu nhân sự sau thời điểm snapshot không tự thay đổi danh sách của cấp đang xử lý.
- Cấp tiếp theo được snapshot tại thời điểm cấp đó được kích hoạt; không snapshot trước toàn bộ các cấp nếu cấu hình/người nhận có thể thay đổi trước khi đến lượt.

**C06 – Delegation và Reassignment**
- Delegation (ủy quyền): người phê duyệt gốc vẫn được lưu; người được ủy quyền thực hiện thay trong phạm vi và thời hạn được duyệt. Phải lưu cả principal approver và acting approver.
- Reassignment (thay thế/giao lại): người có thẩm quyền quản lý workflow có thể thay approver trong yêu cầu đang xử lý; bắt buộc ghi approver cũ, approver mới, actor thực hiện, thời điểm và lý do.
- Đề nghị ủy quyền phải được người có thẩm quyền chấp thuận; người được ủy quyền phải active, có quyền phù hợp và đúng tenant/scope.
- Thay thế không được làm giảm điều kiện ALL: người thay thế tiếp nhận nghĩa vụ duyệt của vị trí được thay; các approver còn lại vẫn phải duyệt.
- Mọi thao tác tạo, duyệt, thu hồi ủy quyền và reassignment đều phải ghi Audit Log. Không ghi đè lịch sử quyết định đã phát sinh.

## 8. Ma trận vai trò rút gọn

| Chức năng | Platform Admin | IT Ops | Customer IT Admin | Inspector | Customer Approver | Viewer |
|---|---:|---:|---:|---:|---:|---:|
| Quản lý location/room | Có | Theo scope | Tenant mình | Không | Không | Xem |
| Quản lý assets SRM | Có | Theo scope | Tenant mình | Xem | Xem | Xem |
| Quản lý danh sách access | Có | Theo scope | Tenant mình | Không | Duyệt | Xem |
| Ghi access log | Có | Theo scope | Tenant mình | Có thể | Xem | Xem |
| Quản lý checklist | Có | Theo scope | Tenant mình | Xem | Duyệt | Xem |
| Lập inspection | Có | Có | Theo quyền | Có | Xem | Xem |
| Review inspection | Có | Theo quyền | Theo quyền | Không mặc định | Có thể | Xem |
| Quản lý incident | Có | Theo scope | Tenant mình | Báo sự cố | Xem | Xem |
| Tạo maintenance | Có | Theo scope | Tenant mình | Không mặc định | Xem | Xem |
| Approve maintenance | Có | Theo quyền | Tenant mình | Không | Có | Không |
| Audit log | Theo quyền | Theo quyền | Theo quyền riêng | Không | Không | Không |

"Tenant mình" luôn có nghĩa là tenant của tài khoản customer, không phải mọi tenant.

## 9. Phân tách nhiệm vụ (SoD)

- Người tạo yêu cầu bảo trì không tự approve chính yêu cầu đó.
- Người lập inspection không tự review/close nếu quy trình yêu cầu kiểm tra độc lập.
- Người đề nghị cấp quyền access không tự approve.
- Người thực hiện thao tác không được tự xác minh khi chính sách yêu cầu người độc lập.
- Super Admin có thể xử lý ngoại lệ kỹ thuật nhưng phải ghi audit reason.

## 10. Bảng cần bổ sung hay không?

Tổng thiết kế sau quyết định: 18 bảng SRM core + 2 bảng pivot + customer_user_srm_permissions + customer_user_server_rooms = 22 bảng mới dự kiến.

Chưa tạo migration và chưa thêm permission seed. Trước tiên cần chốt phương án cấp quyền customer accounts và mapping phạm vi user ↔ phòng Server.

## 11. Quyết định đã chốt và điểm còn mở

Đã chốt: phương án B; Customer User assigned-only; Customer IT Admin được cấp/thu hồi quyền SRM và gán phòng cho user cùng tenant theo allowlist và audit.

Còn mở: ngoại lệ self-approval trong trường hợp khách hàng nhỏ. Mặc định vẫn cấm người tạo tự duyệt.


### 7.6 Chi tiết C06 – Delegation theo thời hạn/phạm vi và snapshot khi kích hoạt cấp

Chủ dự án chọn phương án ủy quyền theo thời hạn/phạm vi (bảng `server_room_approval_delegations`) và xác nhận **chỉ áp dụng khi cấp phê duyệt được kích hoạt**:
1. Principal approver tạo đề nghị delegation; người có thẩm quyền phê duyệt trước khi delegation có hiệu lực.
2. Khi một cấp được kích hoạt, hệ thống resolve group/role thành principal approvers (C05), sau đó kiểm tra delegation phù hợp theo tenant, principal, loại hồ sơ, room scope và thời điểm kích hoạt.
3. Nếu khớp đúng một delegation hợp lệ, snapshot acting approver và delegation_id vào approval row. Nếu không có delegation, principal tự thực hiện. Nếu có nhiều delegation cùng khớp, chặn kích hoạt để xử lý xung đột; không tự chọn.
4. Thu hồi hoặc hết hạn sau thời điểm kích hoạt không hồi tố thay đổi cấp đã snapshot. Các cấp tiếp theo kiểm tra delegation mới tại thời điểm cấp đó được kích hoạt.
5. Acting approver thực hiện nghĩa vụ của principal; không tạo thêm hoặc bỏ bớt approver. ALL approvers vẫn phải hoàn tất.
6. Ghi audit khi tạo, duyệt, từ chối, thu hồi delegation và khi delegation được áp dụng vào approval level.


## 7.7 C07 – Chống tự phê duyệt (APPROVED – 2026-11-15)

- Requester (người tạo yêu cầu) không được tự approve chính yêu cầu đó trong mọi SRM workflow của phiên bản đầu.
- Khi resolve danh sách approver của một cấp theo C05, nếu requester có mặt, hệ thống loại requester khỏi danh sách hiệu lực và ghi nhận lý do/audit.
- Nếu loại requester khiến cấp không còn approver hợp lệ, chặn kích hoạt cấp; không tự động skip. Workflow manager có thẩm quyền phải điều chỉnh cấu hình hoặc thực hiện reassignment hợp lệ.
- Delegation không được dùng để vượt SoD. Nếu acting approver được snapshot là requester hoặc không đủ điều kiện, chặn quyết định và yêu cầu reassignment có thẩm quyền.
- Mọi thay đổi vẫn phải giữ nguyên quy tắc ALL; không được giảm số nghĩa vụ phê duyệt còn lại.

## 12. C08 – Quyền và truy cập tệp đính kèm (APPROVED – 2026-11-15)

- Mọi thao tác upload, preview, download và delete phải kiểm tra quyền trên chức năng SRM, tenant và parent record; biết ID hoặc URL không đồng nghĩa có quyền truy cập.
- File ở trạng thái `pending`, `infected` hoặc `error` không được preview/download; chỉ `clean` mới được cấp nội dung.
- Không cung cấp public URL hoặc `file_path`; truy cập qua service authorization, hoặc signed URL thời hạn rất ngắn chỉ được tạo sau khi kiểm tra quyền.
- Download phải kiểm tra lại quyền tại thời điểm yêu cầu; không dựa vào quyền đã kiểm tra khi upload.

## C14 – Lịch sử cấp/thu hồi Customer SRM Permission (APPROVED – 2026-11-15)

- Permission grant không bị hard-delete khi thu hồi; ghi `revoked_at`, `revoked_by`.
- Cấp lại sau thu hồi tạo grant row mới, không tái sử dụng row cũ.
- Mỗi `(customer_id, user_id, permission_code)` chỉ có một grant active.
- Người cấp/thu hồi phải có quyền quản trị SRM trong đúng tenant; mọi thay đổi ghi Audit.
