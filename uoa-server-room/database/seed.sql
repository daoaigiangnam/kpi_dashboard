-- Initial system role and permission catalog.
INSERT IGNORE INTO roles (code, name, description) VALUES
('SUPER_ADMIN', 'Quản trị hệ thống', 'Toàn quyền cấu hình và quản trị'),
('UOA_MANAGER', 'Quản lý UOA', 'Phê duyệt và quản lý hồ sơ thuộc UOA'),
('ROOM_APPROVER', 'Người phê duyệt phòng', 'Phê duyệt yêu cầu thuộc phòng được phân công'),
('INSPECTOR', 'Nhân viên kiểm tra', 'Tạo và cập nhật phiếu kiểm tra PL3'),
('OPERATOR', 'Nhân viên vận hành', 'Thực hiện nghiệp vụ ra vào và bảo trì'),
('REQUESTER', 'Người đăng ký', 'Tạo và theo dõi yêu cầu của chính mình');

INSERT IGNORE INTO permissions (code, name) VALUES
('dashboard.view', 'Xem Dashboard'),
('pl1.view', 'Xem PL1'), ('pl1.create', 'Tạo PL1'), ('pl1.update', 'Cập nhật PL1'), ('pl1.delete', 'Hủy PL1'), ('pl1.approve', 'Phê duyệt PL1'),
('pl2.view', 'Xem PL2'), ('pl2.create', 'Tạo PL2'), ('pl2.update', 'Cập nhật PL2'), ('pl2.cancel', 'Hủy PL2'), ('pl2.approve', 'Phê duyệt PL2'), ('pl2.checkin', 'Check-in'), ('pl2.checkout', 'Check-out'),
('pl3.view', 'Xem PL3'), ('pl3.create', 'Tạo PL3'), ('pl3.update', 'Cập nhật PL3'), ('pl3.confirm', 'Xác nhận PL3'),
('pl4.view', 'Xem PL4'), ('pl4.create', 'Tạo PL4'), ('pl4.update', 'Cập nhật PL4'), ('pl4.assign', 'Phân công PL4'), ('pl4.close', 'Đóng PL4'),
('pl5.view', 'Xem PL5'), ('pl5.create', 'Tạo PL5'), ('pl5.update', 'Cập nhật PL5'), ('pl5.approve', 'Phê duyệt PL5'), ('pl5.complete', 'Nghiệm thu PL5'),
('email_queue.view', 'Xem Email Queue'), ('email_queue.retry', 'Gửi lại email'), ('email_queue.cancel', 'Hủy email chờ'),
('users.manage', 'Quản lý người dùng'), ('roles.manage', 'Quản lý vai trò'), ('settings.manage', 'Cấu hình hệ thống'), ('audit.view', 'Xem Audit Log');

-- Super Admin receives every permission. Other roles are assigned explicitly
-- in the Admin role-management screen (least privilege).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'SUPER_ADMIN';
