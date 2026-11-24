# 02. ERD và luồng dữ liệu

## 1. Nguyên tắc
1. Customer là tổ chức khách hàng; Assessment là một lần khảo sát.
2. Customer có nhiều Branch, Contact, Assessment và Quote.
3. Assessment có revision và lifecycle riêng.
4. Dữ liệu khảo sát lưu nguồn, thời điểm quan sát và xác minh.
5. Quote có nhiều Scenario; Scenario có scope snapshot và pricing calculation độc lập.
6. Package là mẫu chuẩn; Scenario có thể standard hoặc custom.
7. Checklist task truy ngược rule, finding, service hoặc onboarding source.
8. Catalog, rate và rule có version/effective date để tái lập kết quả cũ.

## 2. ERD cốt lõi

~~~mermaid
erDiagram
    CUSTOMERS ||--o{ CUSTOMER_BRANCHES : has
    CUSTOMERS ||--o{ CUSTOMER_CONTACTS : has
    CUSTOMERS ||--o{ ASSESSMENTS : surveyed
    ASSESSMENTS ||--o{ ASSESSMENT_SECTIONS : contains
    ASSESSMENTS ||--o{ ASSETS : inventories
    ASSESSMENTS ||--o{ CUSTOMER_PERSONNEL : records
    ASSETS }o--|| ASSET_TYPES : classified
    ASSETS ||--o{ ASSET_OBSERVATIONS : observed
    ASSESSMENTS ||--o{ FINDINGS : discovers
    FINDINGS ||--o{ FINDING_RECOMMENDATIONS : treated

    SERVICE_CATEGORIES ||--o{ SERVICES : groups
    SERVICES ||--o{ SERVICE_VERSIONS : versioned
    SERVICE_VERSIONS ||--o{ SERVICE_WORK_ITEMS : decomposes
    WORK_ITEMS ||--o{ SERVICE_WORK_ITEMS : used
    PACKAGES ||--o{ PACKAGE_VERSIONS : versioned
    PACKAGE_VERSIONS ||--o{ PACKAGE_SERVICES : contains
    SERVICES ||--o{ PACKAGE_SERVICES : included

    ASSESSMENTS ||--o{ QUOTES : source
    QUOTES ||--o{ QUOTE_SCENARIOS : contains
    QUOTE_SCENARIOS ||--o{ SCENARIO_SCOPE_ITEMS : defines
    SERVICES ||--o{ SCENARIO_SCOPE_ITEMS : selected
    QUOTE_SCENARIOS ||--o{ WORKLOAD_CALCULATIONS : calculates
    WORKLOAD_CALCULATIONS ||--o{ WORKLOAD_LINE_ITEMS : details
    WORKLOAD_CALCULATIONS ||--o{ LABOR_ALLOCATIONS : allocates
    QUOTE_SCENARIOS ||--o{ PRICING_CALCULATIONS : prices
    PRICING_CALCULATIONS ||--o{ PRICING_LINE_ITEMS : details

    CHECKLIST_TEMPLATES ||--o{ CHECKLIST_RULES : has
    CHECKLIST_RULES ||--o{ CHECKLIST_TASKS : generates
    FINDINGS ||--o{ CHECKLIST_TASKS : remediation
    QUOTE_SCENARIOS ||--o{ CHECKLIST_TASKS : prepares
    CHECKLIST_TASKS ||--o{ CHECKLIST_EVIDENCE : proves
    QUOTES ||--o{ APPROVALS : requires
    USERS ||--o{ APPROVALS : acts
~~~

## 3. Import và connector

~~~mermaid
erDiagram
    ASSESSMENTS ||--o{ IMPORT_JOBS : receives
    IMPORT_JOBS ||--o{ IMPORT_ROWS : stages
    IMPORT_JOBS ||--o{ IMPORT_MAPPINGS : maps
    CUSTOMERS ||--o{ CONNECTORS : authorizes
    CONNECTORS ||--o{ SYNC_RUNS : executes
    SYNC_RUNS ||--o{ EXTERNAL_RECORD_MAPS : maps
    ASSETS ||--o{ EXTERNAL_RECORD_MAPS : linked
    USERS ||--o{ AUDIT_LOGS : performs
~~~

Import rows là staging; chỉ row hợp lệ được commit vào entity chính sau khi người dùng xác nhận.

## 4. Quan hệ nghiệp vụ
### Customer → Assessment → Inventory
- customers 1:N assessments.
- assessments 1:N assets; asset có thể gắn branch và asset_type.
- Theo dõi serial/hostname/configuration bằng asset record riêng.
- Cho phép quantity-based inventory khi không cần quản lý từng serial.
- asset_observations lưu snapshot tại thời điểm khảo sát, không ghi đè lịch sử.

### Assessment → Findings → Checklist
- Finding có severity, likelihood, impact, status và evidence.
- Finding có nhiều recommendation/treatment.
- Checklist remediation có source_finding_id.
- Generation key duy nhất ngăn task trùng khi generator chạy lại.

### Service Catalog → Scope → Workload
- Service và Work Item có version.
- Service version định nghĩa mô tả, prerequisite, deliverable, exclusion, Work Item.
- Package version xác định included/optional/excluded, quantity limit và SLA tier.
- Scenario scope item là snapshot dịch vụ và cấu hình riêng của báo giá.
- Workload lưu quantity, base hours, factors và final hours theo Work Item.

### Quote → Scenario → Pricing
- Quote là hồ sơ thương mại của Assessment.
- Quote có bốn standard scenarios và nhiều custom scenarios.
- Mỗi lần tính tạo calculation version.
- Thay scope/rate/rule tạo calculation mới; không sửa kết quả đã duyệt/phát hành.
- Quote issued snapshot lưu mô tả, số lượng, giá, thuế, điều khoản và version.

### Checklist lifecycle
Category: remediation, onboarding, recurring, improvement. Template là định nghĩa; task là instance theo khách hàng/kỳ. Recurring task cần period key để không sinh trùng.

## 5. Quy tắc khóa/xóa
- Soft delete có kiểm soát; không hard delete dữ liệu đã tham gia quote/approval/audit.
- Customer/Assessment/Quote đã tham chiếu không xóa vật lý.
- FK mặc định ON DELETE RESTRICT; cascade chỉ cho staging rows chưa commit.
- Thay đổi catalog/rate đang được sử dụng phải tạo version mới hoặc inactive.
- Snapshot đã phát hành bất biến về mặt nghiệp vụ.
