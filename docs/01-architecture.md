# 01. Kiến trúc kỹ thuật

## 1. Mục tiêu và phạm vi
Ứng dụng nội bộ phục vụ đội IT/ITSM quản lý vòng đời tư vấn Outsourcing từ khảo sát đến báo giá và kế hoạch hoàn thiện. Một tổ chức vận hành hệ thống để phục vụ nhiều khách hàng; giai đoạn đầu không phải SaaS multi-tenant tự đăng ký.

Năng lực: khách hàng/chi nhánh/nhân sự; khảo sát và inventory; import/API; risk & findings; Service Catalog/Scope/SLA/KPI; workload/FTE/cost/pricing; checklist; quote/approval; reporting.

## 2. Công nghệ
| Lớp | Quyết định |
|---|---|
| Runtime | PHP 8.2+ |
| Backend | PHP thuần, không Laravel và không framework MVC bắt buộc |
| Database | MySQL 8.0+, InnoDB |
| Data access | PDO, prepared statements, transaction |
| Web UI | HTML server-rendered, CSS framework nhẹ, JavaScript module |
| API | REST JSON, prefix /api/v1 |
| Auth | Session cho web; bearer token scoped cho integration |
| File | Private storage ngoài web root |
| Queue | DB-backed jobs + CLI worker/cron ban đầu |
| Export | XLSX/PDF qua thư viện Composer được duyệt |
| Deploy | Linux, Nginx/Apache, PHP-FPM, MySQL |

Composer chỉ quản lý thư viện chuyên biệt; không thêm framework ứng dụng khi chưa có quyết định thay đổi kiến trúc.

## 3. Modular Monolith
Một ứng dụng deploy duy nhất, chia module theo domain. Module gọi nhau qua application services/use cases; không truy cập bảng module khác tùy tiện.

~~~text
Browser / Mobile Browser / Importer / External API
                    |
                HTTPS / WAF
                    |
             Web Entry Point
                    |
       Authentication + Authorization
                    |
       Request Validation + Rate Limit
                    |
              Application Layer
                    |
  +-----------------+--------------------------+
  | Customer & Org  | Assessment & Inventory   |
  | Risk & Findings | Service Catalog & Scope  |
  | Workload Engine | Pricing Engine            |
  | Checklist       | Quote & Approval          |
  | Import/Connector| Reporting & Audit         |
  +-----------------+--------------------------+
                    |
          Domain Services / Repositories
                    |
                  PDO
                    |
                 MySQL
                    |
        Private File Storage / Job Queue
~~~

### Cấu trúc thư mục dự kiến
~~~text
/
├── app/
│   ├── Core/                  # bootstrap, router, request, response, auth
│   ├── Shared/                # exceptions, value objects, helpers
│   ├── Modules/
│   │   ├── Identity/
│   │   ├── Customers/
│   │   ├── Organization/
│   │   ├── Assessments/
│   │   ├── Inventory/
│   │   ├── Integrations/
│   │   ├── Risks/
│   │   ├── ServiceCatalog/
│   │   ├── Scope/
│   │   ├── Workload/
│   │   ├── Pricing/
│   │   ├── Checklists/
│   │   ├── Quotes/
│   │   ├── Approvals/
│   │   ├── Reporting/
│   │   └── Audit/
│   └── Infrastructure/
│       ├── Database/
│       ├── Files/
│       ├── Queue/
│       └── Mail/
├── config/
├── database/migrations/
├── database/seeders/
├── public/                    # document root; index.php, assets
├── resources/views/
├── resources/assets/
├── routes/
├── storage/private/
├── storage/imports/
├── storage/exports/
├── storage/logs/
├── tests/
├── bin/
├── docs/
├── composer.json
└── .env.example
~~~

Không đặt upload hoặc secrets trong public/. Chỉ public/ là document root.

## 4. Luồng dữ liệu

### Khảo sát
Assessment là một lần khảo sát có trạng thái/revision. Dữ liệu nhập thủ công, import, API đều qua validation và lưu provenance: source_type, source_reference, observed_at, verified_status, verified_by, verified_at.

Import Excel: upload → nhận diện template → mapping cột → preview → validate → xác nhận commit. Không ghi dữ liệu chưa kiểm tra vào inventory chính.

### Tích hợp
Connector chỉ đọc khi có API, quyền và kết nối phù hợp. Lưu cấu hình, secret mã hóa, test kết nối, lịch sync, run log, external ID mapping và quy tắc duplicate. Thiết bị không có API được khảo sát thủ công/import. Không mặc định cài agent hoặc chạy network scan.

### Pricing
Pricing Engine là domain service độc lập giao diện. Input là snapshot Assessment + Scope + SLA + Workload Rules + Labor Rates + Cost Rules. Output gồm workload, cost breakdown, margin, suggested price, warnings và validation gate. Mỗi lần tính tạo calculation version. Custom Scenario clone scope snapshot, không sửa Assessment gốc.

### Checklist
Sinh từ Finding/Risk, yêu cầu package/service, onboarding, recurring operation và improvement được duyệt. Lưu rule/template nguồn, source entity, category, effort, owner, due date, status và evidence. Generator phải chống sinh trùng, không reset task đã được người dùng cập nhật.

## 5. Bảo mật
- RBAC và customer-level access; mặc định deny, kiểm tra quyền ở server.
- Session cookie Secure, HttpOnly, SameSite; CSRF token cho thao tác ghi.
- Password hash bằng Argon2id hoặc PASSWORD_DEFAULT.
- PDO prepared statements; allowlist validation; output escaping chống XSS.
- Rate limit login/API; audit sự kiện bảo mật.
- Connector secrets mã hóa bằng khóa ngoài DB; không ghi secret vào log.
- Upload giới hạn loại/dung lượng, lưu ngoài web root, kiểm tra malware nếu có.
- Audit log append-only theo ứng dụng.
- Backup DB + file và kiểm tra restore định kỳ.
- Không lưu mật khẩu quản trị khách hàng plaintext; ưu tiên vault/password manager bên ngoài.

## 6. Phi chức năng
| Thuộc tính | Yêu cầu |
|---|---|
| Encoding | utf8mb4; timestamp UTC |
| Tiền | DECIMAL, mặc định VND; có currency code |
| Timezone | Lưu UTC, hiển thị Asia/Ho_Chi_Minh |
| Audit | actor, thời gian, action, entity, before/after có kiểm soát |
| Idempotency | import, sync, checklist generation chống lặp |
| Concurrency | transaction và optimistic version/row lock |
| Performance | index customer_id, assessment_id, status, created_at |
| Retention | cấu hình thời hạn dữ liệu cá nhân, file và log |

## 7. Ngoài phạm vi mặc định
Không tự động quét port/vulnerability; không thay đổi cấu hình thiết bị, tạo account hay cài agent; không tự gửi báo giá trước duyệt; không tự cam kết SLA chỉ từ dữ liệu khảo sát. Tích hợp ngoài phải có credential, quyền và phạm vi khách hàng cho phép.
