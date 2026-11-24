# 03. Database schema – logical specification

Tài liệu định nghĩa bảng, cột, kiểu dữ liệu, khóa và ràng buộc ở mức thiết kế; chưa phải migration SQL thực thi.

## 1. Quy ước
- Engine InnoDB; charset utf8mb4. MySQL 8.0 collation utf8mb4_0900_ai_ci; MariaDB compatibility phải được kiểm tra trước khi cam kết.
- PK BIGINT UNSIGNED; public_id UUID/ULID unique dùng trên URL/API.
- FK cùng kiểu BIGINT UNSIGNED; index mọi FK.
- created_at/updated_at DATETIME(3) UTC; deleted_at nullable cho entity cho phép soft delete.
- Money DECIMAL(19,4), không FLOAT/DOUBLE; currency CHAR(3).
- Ratio DECIMAL(9,6); quantity DECIMAL(18,4); hours DECIMAL(12,4).
- Status/category dùng VARCHAR + CHECK/reference table, tránh ENUM khó mở rộng.
- Entity thuộc khách hàng có customer_id. Catalog toàn hệ thống không gắn customer_id.
- Secret không lưu plaintext.

## 2. Identity và quyền
- users: id PK, public_id unique, name, email unique, password_hash, status, last_login_at, timestamps.
- roles: id PK, code unique, name, description, is_system, status.
- permissions: id PK, code unique, module, action, description.
- user_roles: id PK, user_id FK, role_id FK, unique(user_id, role_id).
- role_permissions: id PK, role_id FK, permission_id FK, unique(role_id, permission_id).
- user_customer_access: id PK, user_id FK, customer_id FK, access_level, unique(user_id, customer_id).

## 3. Customer và tổ chức
- customers: id, public_id, customer_code unique, legal_name, display_name, tax_code nullable, industry, website, address, country_code, timezone, status, notes.
- customer_branches: id, public_id, customer_id FK, branch_code, name, address, province, country_code, timezone, status; unique(customer_id, branch_code).
- customer_contacts: id, customer_id FK, branch_id nullable FK, full_name, title, email, phone, contact_type, is_primary, consent_notes.
- customer_departments: id, customer_id FK, branch_id nullable FK, parent_id nullable self FK, code, name, status.
- customer_personnel: id, customer_id FK, branch_id nullable FK, department_id nullable FK, employee_ref nullable, full_name, job_title, email nullable, phone nullable, employment_status, user_type, source_type, verified_at. Chỉ thu thập dữ liệu cá nhân cần thiết.

## 4. Assessment
- assessments: id, public_id, customer_id FK, assessment_code, title, assessment_type, revision_no, status, scope_summary, start_date, target_end_date, owner_user_id FK, approved_by nullable FK, approved_at, locked_at. Unique(customer_id, assessment_code, revision_no).
- assessment_sections: id, assessment_id FK, section_code, status, completion_percent, reviewer_user_id nullable FK, reviewed_at, notes; unique(assessment_id, section_code).
- assessment_answers: id, assessment_id FK, section_id FK, question_code, repeat_key nullable, answer_json JSON, answer_text nullable, result_status, source_type, source_reference, observed_at, verified_status, verified_by nullable FK, verified_at. Unique(assessment_id, question_code, repeat_key).
- assessment_evidence: id, assessment_id FK, section_id nullable FK, file_name, storage_key, mime_type, file_size, checksum_sha256, evidence_type, captured_at, uploaded_by FK, access_classification.
- assessment_notes: id, assessment_id FK, entity_type, entity_id, note, visibility, created_by FK.

## 5. Inventory và hạ tầng
- asset_types: id, code unique, name, category, default_unit, active.
- assets: id, public_id, customer_id FK, assessment_id FK, branch_id nullable FK, asset_type_id FK, parent_asset_id nullable self FK, asset_code, asset_name, manufacturer, model, serial_number nullable, hostname nullable, management_ip nullable, quantity DECIMAL(18,4), unit, environment, criticality, lifecycle_status, discovery_source, verification_status, verified_by nullable FK, verified_at.
- asset_observations: id, asset_id FK, assessment_id FK, observed_at, observed_by nullable FK, attributes_json JSON, source_type, source_reference, verification_status.
- network_segments: id, customer_id FK, assessment_id FK, branch_id nullable FK, name, vlan_id nullable, cidr nullable, gateway nullable, purpose, security_zone, notes.
- asset_relationships: id, assessment_id FK, from_asset_id FK, to_asset_id FK, relationship_type, notes; unique(assessment_id, from_asset_id, to_asset_id, relationship_type).

## 6. Risk và finding
- findings: id, public_id, customer_id FK, assessment_id FK, finding_code, title, description, domain, severity, likelihood, impact, risk_score nullable, status, source_answer_id nullable FK, owner_user_id nullable FK, due_date nullable, accepted_risk_reason nullable; unique(assessment_id, finding_code).
- finding_recommendations: id, finding_id FK, recommendation, remediation_type, priority, estimated_hours nullable, estimated_cost nullable, target_date nullable, status.
- finding_evidence: id, finding_id FK, evidence_id FK.

## 7. Service Catalog, scope và SLA
- service_categories: id, code unique, name, description, sort_order, active.
- services: id, service_code unique, category_id FK, name, short_description, owner_role_code, active.
- service_versions: id, service_id FK, version_no, description, prerequisites_json JSON, deliverables_json JSON, exclusions_json JSON, effective_from, effective_to nullable, status; unique(service_id, version_no).
- work_items: id, work_item_code unique, name, description, work_category, measurement_unit, default_hours DECIMAL(12,4), default_frequency, default_role_code, active.
- service_work_items: id, service_version_id FK, work_item_id FK, quantity_source, base_hours_override nullable, frequency_factor, required_flag, notes; unique(service_version_id, work_item_id).
- packages: id, package_code unique, name, description, package_level, active.
- package_versions: id, package_id FK, version_no, effective_from, effective_to nullable, target_margin DECIMAL(9,6), minimum_margin DECIMAL(9,6), status; unique(package_id, version_no).
- package_services: id, package_version_id FK, service_version_id FK, inclusion_mode, default_quantity nullable, quantity_limit nullable, default_sla_tier nullable, pricing_mode, sort_order; unique(package_version_id, service_version_id).
- sla_templates: id, sla_code unique, name, support_window, coverage_days, response_target_json JSON, resolution_target_json JSON, availability_target nullable, active.
- scenario_sla: id, scenario_id FK, sla_template_id FK, override_json JSON, notes.

## 8. Workload và costing
- workload_rule_sets: id, rule_set_code, version_no, effective_from, effective_to nullable, status, description; unique(rule_set_code, version_no).
- workload_rules: id, rule_set_id FK, rule_code, applies_to_json JSON, formula_type, parameters_json JSON, priority, active.
- labor_roles: id, role_code unique, name, description, active.
- labor_rates: id, labor_role_id FK, rate_type, cost_per_hour DECIMAL(19,4), currency CHAR(3), effective_from, effective_to nullable, version_no, active.
- cost_rules: id, rule_set_id FK, cost_code, cost_type, calculation_method, parameters_json JSON, active.
- workload_calculations: id, scenario_id FK, calculation_version, input_snapshot_json JSON, rule_set_id FK, total_hours DECIMAL(12,4), total_fte DECIMAL(12,4), status, calculated_by FK, calculated_at; unique(scenario_id, calculation_version).
- workload_line_items: id, workload_calculation_id FK, work_item_id FK, service_id FK, quantity DECIMAL(18,4), unit, base_hours DECIMAL(12,4), factor_breakdown_json JSON, final_hours DECIMAL(12,4), role_code, source_reference_json JSON.
- labor_allocations: id, workload_calculation_id FK, labor_role_id FK, hours DECIMAL(12,4), fte DECIMAL(12,4), hourly_cost DECIMAL(19,4), total_cost DECIMAL(19,4), allocation_percent DECIMAL(9,6).

## 9. Quote và Pricing Engine
- quotes: id, public_id, quote_code unique, customer_id FK, assessment_id FK, title, currency, status, valid_until nullable, created_by FK, issued_at nullable, accepted_at nullable, current_version_no.
- quote_scenarios: id, public_id, quote_id FK, scenario_code, name, package_version_id nullable FK, scenario_type standard/custom, status, scope_snapshot_json JSON, sla_snapshot_json JSON, created_from_scenario_id nullable self FK, created_by FK; unique(quote_id, scenario_code).
- scenario_scope_items: id, scenario_id FK, service_version_id FK, inclusion_mode, quantity DECIMAL(18,4), unit, configuration_json JSON, sla_snapshot_json JSON, source_type, source_reference nullable; unique(scenario_id, service_version_id).
- pricing_calculations: id, scenario_id FK, calculation_version, workload_calculation_id FK, input_snapshot_json JSON, cost_subtotal DECIMAL(19,4), contingency_cost DECIMAL(19,4), overhead_cost DECIMAL(19,4), total_cost DECIMAL(19,4), target_margin DECIMAL(9,6), gross_margin DECIMAL(9,6), suggested_monthly_price DECIMAL(19,4), approved_monthly_price DECIMAL(19,4) nullable, one_time_cost DECIMAL(19,4), one_time_price DECIMAL(19,4), currency, validation_status, calculated_at, calculated_by FK; unique(scenario_id, calculation_version).
- pricing_line_items: id, pricing_calculation_id FK, line_type, source_type, source_id nullable, description, quantity DECIMAL(18,4), unit, unit_cost DECIMAL(19,4), total_cost DECIMAL(19,4), unit_price DECIMAL(19,4), total_price DECIMAL(19,4), cost_period, sort_order.
- quote_versions: id, quote_id FK, version_no, snapshot_json JSON, subtotal, discount_amount, tax_rate, tax_amount, total_amount, currency, status, created_by FK, created_at; unique(quote_id, version_no).
- quote_attachments: id, quote_id FK, quote_version_id FK, file_name, storage_key, checksum_sha256, document_type.

## 10. Checklist
- checklist_templates: id, template_code, version_no, name, category, description, active; unique(template_code, version_no).
- checklist_rules: id, template_id FK, rule_code, trigger_type, condition_json JSON, task_definition_json JSON, priority, active; unique(template_id, rule_code).
- checklist_tasks: id, public_id, customer_id FK, assessment_id nullable FK, quote_id nullable FK, scenario_id nullable FK, source_finding_id nullable FK, template_rule_id nullable FK, parent_task_id nullable self FK, task_code, title, description, category, priority, status, owner_user_id nullable FK, due_at nullable, estimated_hours DECIMAL(12,4), actual_hours DECIMAL(12,4), estimated_cost DECIMAL(19,4), recurrence_rule nullable, generation_key unique nullable, created_by FK, completed_at nullable.
- checklist_evidence: id, task_id FK, evidence_id FK, notes.
- checklist_task_history: id, task_id FK, actor_user_id FK, old_status, new_status, comment, created_at.

## 11. Import, connector, audit và approval
- import_jobs: id, public_id, customer_id FK, assessment_id FK, import_type, original_file_name, storage_key, status, row_count, valid_count, error_count, mapping_json JSON, uploaded_by FK, confirmed_by nullable FK, confirmed_at nullable.
- import_rows: id, import_job_id FK, row_number, raw_data_json JSON, normalized_data_json JSON, validation_status, errors_json JSON, committed_entity_type nullable, committed_entity_id nullable.
- connectors: id, customer_id FK, connector_type, name, config_json JSON, secret_ciphertext nullable, status, last_tested_at nullable, created_by FK.
- sync_runs: id, connector_id FK, started_at, finished_at nullable, status, records_seen, records_created, records_updated, records_skipped, errors_json JSON.
- external_record_maps: id, connector_id FK, external_type, external_id, local_entity_type, local_entity_id, last_seen_at; unique(connector_id, external_type, external_id).
- approvals: id, entity_type, entity_id, approval_stage, requested_by FK, approver_user_id nullable FK, status, decision_note, decided_at, version_no.
- audit_logs: id, actor_user_id nullable FK, action, entity_type, entity_id, customer_id nullable FK, request_id, ip_address, user_agent, before_json JSON, after_json JSON, created_at. Append-only policy.
- system_settings: id, setting_key unique, setting_value_json JSON, is_secret, updated_by FK, updated_at.

## 12. Transaction và constraint bắt buộc
- FK mặc định ON DELETE RESTRICT và ON UPDATE RESTRICT; cascade chỉ cho staging rows chưa commit.
- Tạo quote + standard scenarios + scope snapshot trong một transaction.
- Pricing calculation tạo workload, line items, labor allocations và pricing result trong transaction.
- Duyệt quote lock current version, kiểm tra gate, ghi approval và audit trong một transaction.
- Phát hành tạo immutable quote_version snapshot và chuyển status atomically.
- Checklist generation dùng unique generation_key và transaction chống trùng.
- Trước migration phải rà soát FK types, index length, JSON support và collation trên MySQL mục tiêu.
