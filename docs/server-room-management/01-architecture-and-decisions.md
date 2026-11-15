# SRM – Kiến trúc và quyết định đã thống nhất

## 1. Mục tiêu

Xây dựng module Server Room Management (SRM) dùng chung trong KPI Dashboard để quản lý phòng máy chủ và hoạt động vận hành tại nhiều khách hàng.

## 2. Mô hình khách hàng – địa điểm – phòng Server

`service_customers` (hiện hữu)
→ 1:N `server_room_locations`
→ 1:N `server_rooms`

- Một khách hàng có thể có nhiều địa điểm.
- Một địa điểm có thể có nhiều phòng Server.
- Mỗi địa điểm luôn thuộc một khách hàng.
- Địa điểm có thể là chi nhánh, trụ sở chính, Data Center hoặc địa điểm độc lập.
- `branch_id` có thể NULL khi địa điểm không gắn với một bản ghi chi nhánh.
- Với địa điểm Data Center, khách hàng trong hệ thống là bên thuê/sử dụng dịch vụ; thông tin nhà cung cấp Data Center được lưu như thông tin đơn vị cung cấp địa điểm, không biến Data Center thành khách hàng sở hữu dữ liệu SRM.

## 3. Các bảng hiện hữu được tái sử dụng

- `service_customers`: chủ thể khách hàng/tenant.
- `customer_branches`: chi nhánh nếu địa điểm gắn với một chi nhánh.
- `users`: tài khoản nội bộ và tài khoản khách hàng theo mô hình hiện tại.

Không tạo bảng khách hàng hoặc người dùng trùng lặp.

## 4. Ranh giới ITAM và SRM

Hiện trạng ITAM mới quản lý máy tính của User (PC/Laptop).

Quyết định cho giai đoạn SRM hiện tại:

- ITAM tiếp tục quản lý PC/Laptop của User.
- SRM có danh mục thiết bị hạ tầng riêng: Server, Firewall, Switch, Router, Storage/NAS, UPS, PDU, Rack, hệ thống làm mát và thiết bị phụ trợ.
- Bảng `server_room_assets` là hồ sơ quản lý thiết bị hạ tầng của SRM.
- Chưa liên kết trực tiếp `server_room_assets` với ITAM hiện hữu.
- Khi ITAM được mở rộng sang hạ tầng, có thể thiết kế tích hợp sau; không tạo trước một quan hệ giả định.

## 5. Checklist theo khách hàng

Mỗi khách hàng tự tạo và quản lý các bộ Checklist. Template có phiên bản. Phiếu kiểm tra đã thực hiện phải giữ snapshot tên/mã/loại câu trả lời của hạng mục tại thời điểm thực hiện để thay đổi template về sau không làm sai lịch sử.

## 6. Danh sách 18 bảng dự kiến

1. `server_room_locations`
2. `server_rooms`
3. `server_room_contacts`
4. `server_room_assets`
5. `server_room_access_persons`
6. `srm_checklist_templates`
7. `srm_checklist_items`
8. `server_room_access_logs`
9. `server_room_inspections`
10. `server_room_inspection_results`
11. `server_room_incidents`
12. `server_room_incident_actions`
13. `server_room_maintenance_requests`
14. `server_room_equipment_movements`
15. `server_room_environment_logs`
16. `server_room_approvals`
17. `server_room_attachments`
18. `server_room_audit_logs`

## 7. Quy tắc kiến trúc bắt buộc phải hoàn thiện trước Migration

### 7.1 Tenant isolation

- Không tin `customer_id` do client tự gửi; phải xác định tenant từ quyền và ngữ cảnh người dùng.
- Mọi truy vấn phải scope theo khách hàng.
- Kiểm tra quan hệ cha/con cùng tenant khi tạo/cập nhật.
- Cân nhắc unique key ghép `(id, customer_id)` và composite foreign key để tăng tính toàn vẹn dữ liệu.
- `branch_id`, nếu có, phải thuộc cùng `customer_id`.

### 7.2 Foreign key và xóa dữ liệu

Phải xác định rõ từng FK, index, unique và hành vi `RESTRICT` / `SET NULL` / `CASCADE`. Dữ liệu lịch sử (ra vào, kiểm tra, sự cố, audit) không được xóa dây chuyền ngoài ý muốn.

### 7.3 Snapshot lịch sử

`server_room_inspection_results` cần lưu snapshot hạng mục, kiểu trả lời và giá trị đã ghi nhận; không phụ thuộc vào nội dung Checklist hiện tại để hiển thị phiếu cũ.

### 7.4 Audit và tệp đính kèm

Attachment phải gắn đúng tenant và đối tượng nghiệp vụ. Audit log cần ghi nhận actor, hành động, đối tượng, thời gian và thay đổi trước/sau ở mức phù hợp.

## 8. Trạng thái triển khai

Tài liệu này ghi nhận quyết định kiến trúc và phạm vi. Đây chưa phải đặc tả kỹ thuật cuối cùng. Chưa tạo migration, model, controller, route, permission hoặc giao diện SRM.


## C18 – Customer User Disable / Tenant Change (APPROVED – 2026-11-15)

1. Khi Customer User bị `disabled`/`inactive`, hệ thống lập tức từ chối mọi truy cập SRM; permission và room assignment hiện hữu được giữ lại làm lịch sử/trạng thái, nhưng không được sử dụng để cấp quyền truy cập khi user bị khóa.
2. Khi Customer User chuyển `customer_id` sang tenant khác, toàn bộ permission và room assignment của tenant cũ phải được revoke; không tự động chuyển quyền sang tenant mới.
3. Giữ nguyên lịch sử grant/revoke và assignment; không hard-delete dữ liệu lịch sử.
4. Quy trình cập nhật trạng thái user, thu hồi permission và room assignment phải được thực hiện nhất quán trong transaction ở mức ứng dụng/DB phù hợp; nếu thất bại phải rollback, không để trạng thái một phần.
5. Khi user được kích hoạt lại hoặc chuyển tenant hoàn tất, quyền truy cập chỉ được khôi phục/cấp mới theo quy trình cấp quyền của tenant tương ứng; không tự khôi phục quyền tenant cũ.
6. Ghi Audit cho disable/enable, tenant change, các thao tác revoke và các lần truy cập SRM bị từ chối; ghi actor, target, UTC timestamp, action, result và correlation ID nếu có.
7. Negative tests bắt buộc: disabled user không truy cập được; user sau tenant change không còn quyền/room assignment tenant cũ; user không được tự động nhận quyền tenant mới; lỗi giữa quy trình không để lại permission/assignment ở trạng thái dở dang.


## D10 – Backup, Restore and Disaster Recovery Policy (APPROVED – 2026-11-15)

1. Database SRM thuộc phạm vi backup chung của KPI Dashboard; không xem SRM là một nguồn dữ liệu độc lập ngoài quy trình backup ứng dụng.
2. Backup phải có lịch thực hiện, retention policy và biện pháp mã hóa/bảo vệ phù hợp với phân loại dữ liệu và chính sách hạ tầng.
3. Phải kiểm tra khả năng restore định kỳ; việc chỉ xác nhận file backup tồn tại hoặc job backup báo thành công không đủ chứng minh khả năng khôi phục.
4. RPO và RTO phải được xác định theo yêu cầu nghiệp vụ và được phê duyệt; không tự đặt giá trị khi chưa có yêu cầu.
5. Database và File Storage phải có phương án backup/restore phối hợp, bảo đảm khôi phục nhất quán giữa metadata trong DB và file thực tế.
6. Có quy trình phát hiện, cảnh báo và xử lý backup thất bại; ghi log đầy đủ và Audit các thao tác restore/quản trị có ảnh hưởng dữ liệu khi phù hợp.
7. Không thực hiện thử nghiệm restore trực tiếp trên Production; dùng môi trường khôi phục riêng biệt, được kiểm soát và bảo vệ dữ liệu.
8. Trước nghiệm thu SRM, phải xác minh phạm vi backup DB, attachment/file storage, retention, encryption/access control, restore test evidence và quy trình DR hiện hữu của KPI Dashboard.
