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
