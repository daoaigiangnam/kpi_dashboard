# PC Audit – Architecture & Export Specification

## Mục tiêu

PC Audit thu thập đầy đủ dữ liệu từ `Get_System_Info.ps1` trên máy khách hàng, gửi về Laravel API và lưu vào MySQL để có thể tìm kiếm, xem chi tiết và xuất Excel.

## Luồng nghiệp vụ

1. Admin dùng Customer/Branch hiện có trong hệ thống.
2. Admin tạo một Audit Code gắn với Branch.
3. User tại máy khách hàng chỉ nhập:
   - Audit Code
   - Phòng ban (tự nhập)
   - Họ tên (tự nhập)
4. Tool thu thập dữ liệu máy.
5. API xác thực Audit Code và tự xác định Customer/Branch.
6. API lưu toàn bộ dữ liệu audit.
7. Web cho phép tìm kiếm/lọc theo Customer, Branch, Department, Employee, Computer Name, Serial, IP, MAC, Software.
8. Có thể xem đầy đủ một máy và xuất Excel.

## Nguyên tắc dữ liệu

- Không tạo Customer mới nếu cấu trúc Customer hiện tại đáp ứng yêu cầu.
- Không tạo Audit Session và không có expiry.
- Không yêu cầu User nhập Customer/Branch.
- Không bỏ trường đang có trong collector.
- Dữ liệu lặp (RAM, Disk, Monitor, Network Adapter, Software...) phải lưu dạng detail rows.
- Export nhiều máy phải giữ đầy đủ chi tiết riêng của từng máy, không chỉ tổng hợp một dòng/máy.

## Excel export

### Export một máy

File gồm toàn bộ thông tin của máy, có thể chia sheet:

- Thông tin máy
- Hardware
- RAM
- Disk
- Monitor
- GPU
- Network
- Windows
- Security
- License
- Software

### Export nhiều máy

File vẫn phải chứa đầy đủ hồ sơ của từng máy. Các sheet detail phải luôn có Computer/Serial/Audit ID để phân biệt dữ liệu. Không gộp nhiều máy thành một record tổng quan.

Có thể tổ chức theo 2 lớp:

- Sheet `Danh sách máy`: tổng quan tất cả máy được chọn.
- Các sheet detail: toàn bộ chi tiết của tất cả máy được chọn, mỗi dòng có `audit_id`/`computer_name`/`serial_number`.

Ngoài ra có thể cung cấp export hồ sơ chi tiết theo từng máy khi cần gửi cho khách hàng.

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
