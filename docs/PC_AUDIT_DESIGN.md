# PC Audit – Architecture & Export Specification

## Mục tiêu
PC Audit thu thập đầy đủ dữ liệu từ `Get_System_Info.ps1` trên máy khách hàng, gửi về Laravel API và lưu vào MySQL để có thể tìm kiếm, xem chi tiết và xuất Excel.

## Master data
- Tận dụng Customer hiện có trong KPI Dashboard (`ServiceCustomer`); không tạo Customer riêng cho PC Audit.
- Chi nhánh là dữ liệu thuộc Customer và được dùng để xác định Audit Code.
- Admin tạo Audit Code gắn với Chi nhánh.

## Luồng nghiệp vụ
1. Admin dùng Customer hiện có trong hệ thống.
2. Admin quản lý Chi nhánh thuộc Customer.
3. Admin tạo Audit Code gắn với Branch.
4. User tại máy khách hàng chỉ nhập Audit Code, Phòng ban (tự nhập), Họ tên (tự nhập).
5. Tool thu thập toàn bộ dữ liệu máy từ Get_System_Info.ps1.
6. API xác thực Audit Code và tự xác định Customer/Branch.
7. API lưu toàn bộ dữ liệu audit.
8. Web cho phép tìm kiếm/lọc theo Customer, Branch, Department, Employee, Computer Name, Serial, IP, MAC, Software.
9. Có thể xem đầy đủ một máy và xuất Excel.

## Nguyên tắc dữ liệu
- Không tạo Customer mới nếu Customer hiện tại đáp ứng yêu cầu.
- Không tạo Audit Session và không có expiry.
- Không yêu cầu User nhập Customer/Branch.
- Phòng ban và Họ tên do User nhập.
- Không bỏ trường đang có trong collector.
- Dữ liệu lặp (RAM, Disk, Monitor, Network Adapter, Software...) phải lưu dạng detail rows.
- Raw payload phải được bảo toàn để không mất dữ liệu khi collector phát sinh field mới.
- Export nhiều máy phải giữ đầy đủ chi tiết riêng của từng máy, không chỉ tổng hợp một dòng/máy.

## Excel export
### Export một máy
File gồm toàn bộ thông tin của máy, có thể chia sheet: Thông tin máy, Hardware, RAM, Disk, Monitor, GPU, Network, Windows, Security, License, Software.

### Export nhiều máy
File phải chứa đầy đủ hồ sơ của từng máy. Có Sheet `Danh sách máy` tổng quan và các sheet detail chứa toàn bộ dữ liệu; mọi detail row phải có `audit_id`/`computer_name`/`serial_number` để phân biệt máy. Có thể xuất hồ sơ riêng theo từng máy khi cần gửi khách hàng.

## API dự kiến
- `POST /api/pc-audit/validate-code`
- `POST /api/pc-audit/submit`

Payload tối thiểu:
```json
{
  "code": "ABC-HCM-001",
  "department": "Kế toán",
  "employee_name": "Nguyễn Văn A",
  "computer": {},
  "hardware": {},
  "windows": {},
  "network": {},
  "security": {},
  "licenses": {},
  "software": []
}
```
Customer/Branch không lấy từ client payload để quyết định quan hệ; server resolve từ Audit Code.
