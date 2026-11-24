# 04. API contract

## 1. Quy ước
- Base path: /api/v1
- JSON UTF-8; upload file dùng multipart/form-data.
- Session cho web; bearer token scoped cho connector/integration.
- Success: { "data": {}, "meta": { "request_id": "..." } }
- Error: { "error": { "code": "VALIDATION_ERROR", "message": "...", "details": [] }, "meta": { "request_id": "..." } }
- Pagination: page, per_page; meta có total/current_page/per_page/last_page.
- DateTime ISO-8601 UTC.
- Money trả decimal string và currency code, không dùng float.
- Mutation hỗ trợ Idempotency-Key khi retry được.
- Mọi API kiểm tra permission và customer access ở server.

## 2. HTTP status
| Status | Ý nghĩa |
|---|---|
| 200 | Thành công |
| 201 | Tạo mới |
| 202 | Đã nhận job nền |
| 204 | Thành công không body |
| 400 | Request sai cấu trúc |
| 401 | Chưa xác thực |
| 403 | Không có quyền |
| 404 | Không tìm thấy/không được phép truy cập |
| 409 | Xung đột trạng thái hoặc version |
| 422 | Validation thất bại |
| 429 | Vượt giới hạn |
| 500 | Lỗi hệ thống, kèm request_id |

## 3. Identity và reference data
- GET /me
- GET/POST /users
- PATCH /users/{userId}
- GET /roles
- GET /permissions
- GET /reference-data/asset-types
- GET /reference-data/industries
- GET /reference-data/service-categories
- GET /reference-data/workload-rules

## 4. Customer và tổ chức
- GET/POST /customers
- GET/PATCH /customers/{customerId}
- GET/POST /customers/{customerId}/branches
- GET/POST /customers/{customerId}/contacts
- GET/POST /customers/{customerId}/personnel
- GET/POST /customers/{customerId}/departments

## 5. Assessment
- GET/POST /customers/{customerId}/assessments
- GET/PATCH /assessments/{assessmentId}
- POST /assessments/{assessmentId}/start
- POST /assessments/{assessmentId}/submit-review
- POST /assessments/{assessmentId}/approve
- GET /assessments/{assessmentId}/sections
- PUT /assessments/{assessmentId}/sections/{sectionCode}
- GET /assessments/{assessmentId}/answers
- PUT /assessments/{assessmentId}/answers/{questionCode}
- POST /assessments/{assessmentId}/evidence
- GET /assessments/{assessmentId}/completion

Approve chỉ khi required sections và evidence bắt buộc đạt điều kiện.

## 6. Inventory và findings
- GET/POST /assessments/{assessmentId}/assets
- PATCH /assets/{assetId}
- POST /assets/{assetId}/verify
- GET/POST /assessments/{assessmentId}/asset-relationships
- GET/POST /assessments/{assessmentId}/findings
- PATCH /findings/{findingId}
- POST /findings/{findingId}/recommendations
- POST /findings/{findingId}/evidence

## 7. Import và connector
- POST /assessments/{assessmentId}/imports
- GET /imports/{importJobId}
- POST /imports/{importJobId}/map-columns
- POST /imports/{importJobId}/validate
- GET /imports/{importJobId}/preview
- POST /imports/{importJobId}/commit
- POST /imports/{importJobId}/cancel
- GET/POST /customers/{customerId}/connectors
- POST /connectors/{connectorId}/test
- POST /connectors/{connectorId}/sync
- GET /connectors/{connectorId}/runs

Import commit phải validate quyền, trạng thái và chống commit lần hai. Connector sync trả 202 + run/job ID.

## 8. Service Catalog và Scope
- GET /service-catalog/categories
- GET/POST /service-catalog/services
- GET/POST /service-catalog/services/{serviceId}/versions
- GET/POST /packages
- GET/POST /packages/{packageId}/versions
- GET /assessments/{assessmentId}/service-recommendations
- POST /quotes/{quoteId}/scenarios/{scenarioId}/scope
- PATCH /scenarios/{scenarioId}/scope-items/{scopeItemId}
- DELETE /scenarios/{scenarioId}/scope-items/{scopeItemId}
- PUT /scenarios/{scenarioId}/sla

Chỉ scenario draft được chỉnh scope; snapshot đã duyệt/phát hành không được sửa.

## 9. Workload và Pricing
- POST /scenarios/{scenarioId}/workload/preview
- POST /scenarios/{scenarioId}/pricing/calculate
- GET /scenarios/{scenarioId}/pricing/calculations
- GET /pricing/calculations/{calculationId}
- GET /pricing/calculations/{calculationId}/breakdown
- POST /quotes/{quoteId}/generate-standard-scenarios
- POST /scenarios/{scenarioId}/clone
- POST /scenarios/{scenarioId}/recalculate

### Request mẫu – tạo Assessment
~~~json
{
  "title": "Khảo sát hạ tầng và dịch vụ IT 2026",
  "assessment_type": "initial",
  "start_date": "2026-12-01",
  "owner_user_id": "USR-001"
}
~~~

### Request mẫu – tính giá
~~~json
{
  "recalculate_reason": "scope_updated",
  "expected_scope_version": 4,
  "expected_rate_set_version": "RATE-2026-03"
}
~~~

### Response mẫu
~~~json
{
  "data": {
    "calculation_id": "CAL-001",
    "scenario_id": "SCN-001",
    "calculation_version": 2,
    "currency": "VND",
    "monthly_cost": "20000000.0000",
    "suggested_monthly_price": "26666666.6667",
    "gross_margin": "0.250000",
    "one_time_cost": "5000000.0000",
    "validation_status": "ready_for_review",
    "warnings": []
  },
  "meta": { "request_id": "REQ-001" }
}
~~~

Giá trong response chỉ minh họa cấu trúc, không phải rate mặc định. Client không được gửi total_cost, margin hoặc approved_price có thẩm quyền; server tự tính.

## 10. Checklist
- POST /scenarios/{scenarioId}/checklists/generate
- GET /customers/{customerId}/checklist-tasks
- GET/POST /checklist-tasks
- GET/PATCH /checklist-tasks/{taskId}
- POST /checklist-tasks/{taskId}/assign
- POST /checklist-tasks/{taskId}/transition
- POST /checklist-tasks/{taskId}/evidence
- GET /checklist-tasks/{taskId}/history

Task transition kiểm tra state machine và quyền. Generator dùng generation key chống trùng.

## 11. Quote, approval và reporting
- GET /customers/{customerId}/quotes
- POST /assessments/{assessmentId}/quotes
- GET /quotes/{quoteId}
- POST /quotes/{quoteId}/versions
- POST /quotes/{quoteId}/submit-approval
- POST /quotes/{quoteId}/approve
- POST /quotes/{quoteId}/reject
- POST /quotes/{quoteId}/issue
- POST /quotes/{quoteId}/export
- GET /dashboard/summary
- GET /reports/assessment/{assessmentId}
- GET /reports/pricing/{quoteId}
- GET /reports/checklists/{customerId}

Chỉ approved current quote version mới được issue. Reject phải có decision_note. Approver kiểm tra version được gửi duyệt.

## 12. Security và compatibility
- Dùng public_id thay internal ID trên URL.
- Validate field allowlist; reject field đặc quyền do client tự gửi.
- Không log token, secret hoặc dữ liệu nhạy cảm.
- Connector token chỉ có scope và customer được cấp.
- Breaking change tạo /api/v2; v1 additive fields phải backward-compatible.
