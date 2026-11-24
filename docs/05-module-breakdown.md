# 05. Phân rã module và kế hoạch triển khai

## 1. Module

| ID | Module | Trách nhiệm | Phụ thuộc |
|---|---|---|---|
| M01 | Core & Identity | Bootstrap, router, session, user, RBAC, audit context | - |
| M02 | Customer & Organization | Customer, branch, department, contact, personnel | M01 |
| M03 | Assessment | Lifecycle, sections, answers, evidence, completion | M01, M02 |
| M04 | Inventory | Asset, observations, network segments, relationships | M02, M03 |
| M05 | Import & Connector | Excel staging, mapping, validation, sync, external maps | M03, M04 |
| M06 | Risk & Findings | Risk, findings, recommendations, evidence | M03, M04 |
| M07 | Service Catalog | Service/version, Work Item, package/version, SLA | M01 |
| M08 | Scope Builder | Scenario scope, inclusion/exclusion, quantity, SLA | M03, M07 |
| M09 | Workload Engine | Workload rules, factors, hours, role allocation, FTE | M04, M06, M08 |
| M10 | Pricing Engine | Labor/non-labor, margin, price floor, scenarios | M09, M07 |
| M11 | Checklist Engine | Templates, rules, generator, task lifecycle, evidence | M03, M06, M08, M10 |
| M12 | Quote & Approval | Quote versions, approvals, issue/export, snapshots | M10, M11 |
| M13 | Reporting | Dashboard, assessment, workload, pricing, checklist reports | M02-M12 |
| M14 | Platform Operations | Settings, jobs, logs, backup, monitoring | M01 |

## 2. Vai trò
| Role | Quyền chính |
|---|---|
| System Admin | Cấu hình hệ thống, user, role |
| ITSM Manager | Catalog, định mức, rules, package |
| Assessment Lead | Phân công và duyệt khảo sát |
| IT Surveyor | Nhập khảo sát, tài sản, evidence |
| Service Designer | Scope, SLA, Work Item |
| Pricing Analyst | Scenario, tính giá, xem cost |
| Approver | Duyệt theo hạn mức |
| Operations Lead | Phân công/nghiệm thu checklist |
| Viewer/Auditor | Xem báo cáo và lịch sử theo quyền |

Tách quyền xem internal cost/margin và giá bán. Nhân viên khảo sát không mặc định được xem labor rate.

## 3. State machine
Assessment: draft → in_progress → submitted_for_review → approved → locked → closed. Reviewer có thể trả về in_progress; sửa dữ liệu sau approve phải tạo revision hoặc mở lại theo quyền đặc biệt.

Quote: draft → pricing_ready → pending_approval → approved → issued → accepted/rejected/expired. Rework tạo revision mới, không ghi đè phiên bản đã duyệt.

Checklist task: open → assigned → in_progress → blocked → completed → verified. Reopen cần quyền và ghi history.

## 4. Workload & Pricing rules

### Workload
Work Item có quantity source (asset/user/branch/ticket/fixed/manual), unit, base hours per unit, frequency, factors, role, recurring/one-time classification.

- base_hours = quantity × hours_per_unit × frequency
- adjusted_hours = base_hours × applicable_factor_product
- Có thể cấu hình floor/cap; không áp dụng trùng cùng factor.
- Onsite effort tính riêng theo ngày/giờ onsite, không cộng trùng remote effort.

### Role allocation
Tỷ lệ phân bổ theo từng Work Item; tổng phải bằng 100% hoặc có residual role được khai báo. Không chỉ dùng một tỷ lệ chung cho cả package.

### Pricing
- labor_cost = tổng(role_hours × effective_hourly_cost)
- non_labor_cost = license + tools + travel + telecom + vendor + other approved cost
- overhead và management allocation theo rule version
- total_cost = labor + non_labor + overhead + contingency
- suggested_price = total_cost / (1 - target_gross_margin)
- gross_margin = (price - cost) / price
- discount và tax áp dụng ở quote version, không thay đổi cost base
- Remediation/Onboarding one-time fee tách monthly recurring fee
- Giá dưới floor hoặc margin dưới ngưỡng phải block hoặc nâng cấp phê duyệt

## 5. Standard và Custom Scenario
1. Tạo tối đa 4 standard scenarios theo package version đang hiệu lực.
2. Mỗi scenario nhận service scope và quantity limits của package.
3. Clone scenario chuẩn để tạo custom scenario, lưu source_scenario_id.
4. Cho phép chỉnh service, quantity, SLA, onsite, support window, responsibility.
5. Recalculate toàn bộ workload và pricing; không cộng/trừ giá thủ công.
6. So sánh monthly price, one-time fee, included/excluded services, SLA, staffing và margin.
7. Một Quote có thể có nhiều custom scenarios.

## 6. Checklist Generator
Trigger:
- Finding/risk vượt ngưỡng.
- Service đã chọn nhưng onboarding requirement chưa đạt.
- Package/SLA yêu cầu task bắt buộc.
- Import/connector phát hiện asset chưa xác minh.
- Recurring schedule.
- Improvement request được duyệt.

Rule gồm trigger_type, condition, task template, priority, owner role, estimated hours, due offset, evidence requirement và category.

Generation key gợi ý: hash(rule_id + assessment_id + scenario_id + source_entity_id + period_key). Chạy lại không tạo task trùng, không reset task đã chỉnh; chỉ tạo task cho source/period mới.

## 7. Kế hoạch và nghiệm thu

### P0 – Design freeze
Duyệt architecture, ERD, schema, API, lifecycle, pricing rules; chốt MySQL, hosting, role matrix, sample data và backup. Chưa code.

### P1 – Core & Customer
Đăng nhập, RBAC, customer, branch, contact, personnel. Nghiệm thu: không truy cập chéo dữ liệu khách hàng.

### P2 – Assessment & Inventory
Form theo section, asset, evidence, verification. Nghiệm thu: hoàn thành survey, quản lý revision và history.

### P3 – Import & Integration foundation
Excel template, mapping, preview, validation, commit; connector framework. Nghiệm thu: import lỗi không làm bẩn inventory; retry không nhân đôi.

### P4 – Risk & Service Catalog
Finding, recommendation, service/work item/package/SLA version. Nghiệm thu: assessment tạo được report hiện trạng/rủi ro.

### P5 – Scope & Workload
Scope builder, quantity sources, factors, role hours, FTE. Nghiệm thu: thay SLA/quantity thay đổi workload giải thích được.

### P6 – Pricing Engine
Cost breakdown, 4 packages, custom scenario, floor/margin gate. Nghiệm thu: tái lập kết quả theo calculation version; có regression tests.

### P7 – Checklist & Quote
Generate/edit/assign/verify checklist; quote, approval, issue/export. Nghiệm thu: task truy ngược source; issued snapshot bất biến.

### P8 – Reporting & Hardening
Dashboard, export, audit, backup/restore, security/performance test. Nghiệm thu: UAT, restore, role và pricing regression tests.

## 8. Test case nghiệp vụ bắt buộc
- Cùng endpoint count nhưng khác branch count/SLA cho workload khác.
- Bỏ service khỏi scope loại các Work Item phụ thuộc.
- Thêm Security Monitoring tăng workload, labor và onboarding checklist.
- Tăng onsite days tăng onsite hours/travel theo rule, không nhân đôi remote.
- Margin 25% dùng gross-margin formula đúng.
- Giá dưới floor block issue hoặc yêu cầu cấp duyệt cao hơn.
- Rate đổi sau issue không đổi quote snapshot.
- Import cùng file hai lần không tạo duplicate assets.
- Generator chạy lại không duplicate/reset tasks.
- User không có customer access không đọc được ID đoán trước.
- Tùy chọn segregation-of-duties ngăn người tạo tự duyệt quote.
