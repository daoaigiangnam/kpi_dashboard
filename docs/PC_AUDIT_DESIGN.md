# PC Audit – Architecture & Export Specification

## Mục tiêu

PC Audit thu thập đầy đủ dữ liệu từ `Get_System_Info.ps1` trên máy khách hàng, gửi về Laravel API và lưu vào MySQL để có thể tìm kiếm, xem chi tiết và xuất Excel.

## Luồng nghiệp vụ

1. Admin dùng `ServiceCustomer` làm Customer master hiện có.
2. Admin quản lý Branch thuộc Customer.
3. Admin tạo một Audit Code gắn với Branch.
4. User tại máy khách hàng chỉ nhập:
   - Audit Code
   - Phòng ban (tự nhập)
   - Họ tên (tự nhập)
5. Tool thu thập dữ liệu máy.
6. API xác thực Audit Code và tự xác định Customer/Branch.
7. API lưu toàn bộ dữ liệu audit.
8. Web cho phép tìm kiếm/lọc theo Customer, Branch, Department, Employee, Computer Name, Serial, IP, MAC, Software.
9. Có thể xem đầy đủ một máy và xuất Excel.

## Nguyên tắc dữ liệu

- `ServiceCustomer` là Customer master dùng chung; không tạo Customer thứ hai cho PC Audit.
- Branch dùng bảng `customer_branches` và tham chiếu `service_customers`.
- Không tạo Audit Session và không có expiry.
- Không yêu cầu User nhập Customer/Branch.
- Không bỏ trường đang có trong collector.
- Dữ liệu lặp (RAM, Disk, Monitor, Network Adapter, Software...) phải được bảo toàn đầy đủ.
- `pc_audit_details` giữ payload theo nhóm để không mất field khi collector phát triển.
- `pc_audits.raw_payload` giữ bản payload gốc để có thể tái xử lý/export về sau.
- Khi ổn định Data Dictionary, các field cần tìm kiếm/report thường xuyên có thể được chuẩn hóa thành detail tables/index riêng; không được làm mất raw payload.

## Các nhóm dữ liệu phải bảo toàn

`pc_audit_details` hiện dành chỗ cho các nhóm:

- `hardware`
- `cpu`
- `memory`
- `storage`
- `monitors`
- `gpu`
- `battery`
- `windows`
- `network`
- `security`
- `licenses`
- `software`
- `other`

Các nhóm có thể chứa object hoặc array tùy output thực tế của collector. API không được tự ý cắt bớt dữ liệu.

## Excel export

### Export một máy

File gồm toàn bộ thông tin của máy, có thể chia sheet:

- Thông tin máy
- Hardware
- CPU
- RAM
- Disk/Storage
- Monitor
- GPU
- Battery
- Network
- Windows
- Security
- License
- Software

### Export nhiều máy

File phải chứa đầy đủ hồ sơ của **từng máy được chọn**, không chỉ một dòng tổng quan/máy.

Workbook nên có:

- Sheet `Danh sách máy`: tổng quan tất cả máy được chọn.
- Các sheet detail: toàn bộ chi tiết của tất cả máy được chọn.
- Mọi detail row phải có `audit_id` và/hoặc `computer_name` + `serial_number` để không trộn dữ liệu giữa các máy.

Ngoài ra có thể cung cấp export hồ sơ chi tiết riêng cho từng máy khi cần gửi cho khách hàng.

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
  "cpu": {},
  "memory": [],
  "storage": [],
  "monitors": [],
  "gpu": {},
  "battery": {},
  "windows": {},
  "network": [],
  "security": {},
  "licenses": {},
  "software": []
}
```

Customer/Branch không lấy từ client payload để quyết định quan hệ; server resolve từ Audit Code.

## Client

Collector hiện tại tiếp tục là nguồn dữ liệu. Excel không cần được tạo trên máy khách. Client nên gửi structured JSON qua HTTPS; về sau PowerShell collector có thể được đóng gói thành portable EXE để User không phải cài module `ImportExcel`.
