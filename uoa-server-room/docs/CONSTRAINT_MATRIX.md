# CONSTRAINT MATRIX – UOA SERVER ROOM MANAGEMENT

**Branch:** feature/ITSMWEB  
**Version:** 0.1 – Review draft  
**Date:** 23/11/2026  
**Status:** DESIGN REVIEW ONLY – NOT MIGRATION / NOT DDL  
**Scope:** CORE, PL1–PL5 and COMMON  
**Sources:** DATA_DICTIONARY.md, ERD_PHYSICAL.md, ERD_DESIGN.md, ADR-001…ADR-015

> This matrix records relationship and constraint requirements visible in the current design. ON DELETE is approved globally as RESTRICT under ADR-014. ON UPDATE is approved as RESTRICT under ADR-015.

## 1. Status and interpretation

| Status | Meaning |
|---|---|
| APPROVED | Explicitly supported by an approved ADR or approved design decision |
| DOCUMENTED | Present in the Data Dictionary / ERD, but not necessarily approved as a delete policy |
| ALIGNMENT REQUIRED | Nullability, key/index detail or ERD notation needs to be made explicit |
| TBD – APPROVAL | Business/architecture decision is open; do not implement by assumption |
| SERVICE RULE | A database FK/UNIQUE cannot enforce the complete business rule |

**Global ON DELETE policy – ADR-014:** every actual FK in CORE, PL1–PL5 and COMMON uses ON DELETE RESTRICT. No CASCADE or SET NULL. ON UPDATE remains a separate open decision; database engine defaults must not be mistaken for an approved policy.

## 2. CORE – users, roles, rooms and business entity registry

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| user_roles.user_id | users.id | NOT NULL | Composite PK (user_id, role_id) | RESTRICT | DOCUMENTED |
| user_roles.role_id | roles.id | NOT NULL | Composite PK (user_id, role_id) | RESTRICT | DOCUMENTED |
| user_roles.assigned_by_user_id | users.id | NULL | FK index | RESTRICT | Actor may be absent/system-originated |
| role_permissions.role_id | roles.id | NOT NULL | Composite PK (role_id, permission_id) | RESTRICT | DOCUMENTED |
| role_permissions.permission_id | permissions.id | NOT NULL | Composite PK (role_id, permission_id) | RESTRICT | DOCUMENTED |
| room_contacts.room_id | server_rooms.id | NOT NULL | INDEX (room_id, contact_type, is_active) | RESTRICT | DOCUMENTED |
| room_contacts.user_id | users.id | NULL | FK index | RESTRICT | External contact may have no account |
| setting_change_history.setting_id | business_settings.id | NOT NULL | FK index | RESTRICT | Confirm exact definition against ERD |
| setting_change_history.actor_user_id | users.id | NULL | FK index | RESTRICT | Actor semantics need consistency |
| Each root subtype.business_entity_id | business_entities.id | NOT NULL | UNIQUE per subtype table | RESTRICT | APPROVED relationship – ADR-001; preserve referenced identity |
| business_entities(module_code, entity_type, business_code) | — | NOT NULL components | UNIQUE composite | N/A | APPROVED – ADR-001 |
| room_contacts primary contact rule | — | — | Transaction/service validation; simple UNIQUE is insufficient for effective-dated history | N/A | SERVICE RULE |

Root subtype tables: pl1_access_requests, pl1_access_records, pl2_visits, pl3_inspections, pl4_issues, pl5_work_requests.

The supertype FK proves that a registry row exists. It does not prove that every registry row has exactly one subtype or that module_code/entity_type matches the subtype table. The service must validate those conditions.

## 3. PL1 – Access Authorization

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| pl1_access_requests.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | ADR-001 |
| pl1_access_requests.room_id | server_rooms.id | NOT NULL | INDEX (room_id, person_email, approval_status) | RESTRICT | DOCUMENTED |
| pl1_approval_decisions.access_request_id | pl1_access_requests.id | NOT NULL | INDEX (access_request_id, decided_at) | RESTRICT | History preservation |
| pl1_approval_decisions.approver_user_id | users.id | NULL | FK index | RESTRICT | External/email approver may lack account |
| pl1_access_records.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | ADR-001 |
| pl1_access_records.room_id | server_rooms.id | NOT NULL | INDEX (person_email, room_id, record_status) | RESTRICT | DOCUMENTED |
| pl1_access_records.created_from_request_id | pl1_access_requests.id | NOT NULL | FK index | RESTRICT | Required source request |
| pl1_access_records.last_changed_by_request_id | pl1_access_requests.id | NULL | FK index | RESTRICT | Optional later change request |
| pl1_access_record_history.access_record_id | pl1_access_records.id | NOT NULL | INDEX (access_record_id, changed_at) | RESTRICT | Append-only history |
| pl1_access_record_history.access_request_id | pl1_access_requests.id | NOT NULL | FK index | RESTRICT | Append-only history |
| pl1_access_record_history.actor_user_id | users.id | NULL | FK index | RESTRICT | Actor may be external |
| pl1_action_tokens.access_request_id | pl1_access_requests.id | NOT NULL | FK index; token_hash UNIQUE NOT NULL | RESTRICT | Security lifecycle; no raw token in logs |
| idempotency_keys(scope, idempotency_key) | — | NOT NULL | UNIQUE composite; request_hash required | N/A | APPROVED – ADR-012 |

**PL1 approved constraints – ADR-012**
- Do not add pl1_access_requests.source_response_id as a UNIQUE idempotency key; remove it from physical design.
- Same scope/key + same request hash returns the prior result; same key + different request hash is a conflict and must be audited.
- idempotency_keys.entity_type/entity_id are metadata, not polymorphic foreign keys.
- Do not add UNIQUE (person_email, room_id) across Access Record history; active-record uniqueness is a transaction/service rule.

**PL1 delete policy – ADR-014:** all actual PL1 FKs use ON DELETE RESTRICT. Request/Record/history/token lifecycle is status- and retention-driven; purge is explicit and child-first.

## 4. PL2 – Visit / Approval / OTP / Check-in / Check-out

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| pl2_visits.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | ADR-001 |
| pl2_visits.room_id | server_rooms.id | NOT NULL | INDEX (room_id, planned_entry_at) | RESTRICT | DOCUMENTED |
| pl2_approval_history.visit_id | pl2_visits.id | NOT NULL | FK index | RESTRICT | Preserve decisions/reminders/expiry |
| pl2_approval_history.actor_user_id | users.id | NULL | FK index | RESTRICT | External approver possible |
| pl2_action_tokens.visit_id | pl2_visits.id | NOT NULL | INDEX (visit_id, purpose, expiry); token_hash UNIQUE | RESTRICT | One token scoped to Visit + Purpose |
| pl2_otp_challenges.visit_id | pl2_visits.id | NOT NULL | FK index | RESTRICT | Challenge lifecycle |
| pl2_otp_attempts.otp_challenge_id | pl2_otp_challenges.id | NOT NULL | FK index | RESTRICT | Attempt history; do not store submitted OTP |
| pl2_visit_events.visit_id | pl2_visits.id | NOT NULL | FK index | RESTRICT | Append-only event history |
| pl2_visit_events.actor_user_id | users.id | NULL | FK index | RESTRICT | External actor possible |

**PL2 service/lifecycle rules**
- OTP expiry is assigned only after the email send succeeds; this is a lifecycle rule, not a foreign-key rule.
- Action token purpose and Visit binding must be validated by the service.
- Actor IDs are nullable where the actor may be external or have no Admin account.

**PL2 delete policy – ADR-014:** all actual PL2 FKs use ON DELETE RESTRICT. Retention cleanup must remove eligible OTP Attempts before OTP Challenges and preserve Visit/Audit history according to approved retention.

## 5. PL3 – Inspection / Checklist / Escalation / Outbox

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| pl3_checklist_versions.published_by_user_id | users.id | NULL | FK index | RESTRICT | Published actor may be system/import; confirm |
| pl3_checklist_versions.created_by_user_id | users.id | UNRESOLVED | FK index | RESTRICT | Dictionary currently says NOT NULL; actor semantics need approval |
| pl3_checklist_version_items.checklist_version_id | pl3_checklist_versions.id | NOT NULL | UNIQUE (checklist_version_id, checklist_item_id); UNIQUE (checklist_version_id, sort_order) | RESTRICT | Immutable after publish |
| pl3_checklist_version_items.checklist_item_id | pl3_checklist_items.id | NOT NULL | Composite UNIQUE; FK index as needed | RESTRICT | Stable source catalog item |
| pl3_inspections.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | ADR-001 |
| pl3_inspections.room_id | server_rooms.id | NOT NULL | INDEX (room_id, inspection_date) | RESTRICT | DOCUMENTED |
| pl3_inspections.checklist_version_id | pl3_checklist_versions.id | NOT NULL | FK index | RESTRICT | Inspection binds exact version |
| pl3_inspection_results.inspection_id | pl3_inspections.id | NOT NULL | UNIQUE (inspection_id, checklist_version_item_id) | RESTRICT | DOCUMENTED |
| pl3_inspection_results.checklist_version_item_id | pl3_checklist_version_items.id | NOT NULL | UNIQUE (inspection_id, checklist_version_item_id) | RESTRICT | Exact version item answered |
| pl3_acknowledgements.inspection_id | pl3_inspections.id | NOT NULL | FK index | RESTRICT | Receipt, not remediation approval |
| pl3_acknowledgements.actor_user_id | users.id | NULL | FK index | RESTRICT | External actor possible |
| pl3_escalation_runs.room_id | server_rooms.id | NOT NULL | UNIQUE (room_id, work_date, schedule_type) | RESTRICT | APPROVED – ADR-002; DAILY only |
| pl3_escalation_events.escalation_run_id | pl3_escalation_runs.id | NOT NULL | UNIQUE (escalation_run_id, level) | RESTRICT | One logical Event per level |
| pl3_escalation_events.email_queue_id | email_queue.id | NULL | FK index | RESTRICT | Optional before enqueue |
| pl3_integration_outbox.inspection_result_id | pl3_inspection_results.id | SHOULD BE NOT NULL | UNIQUE | RESTRICT | Make NOT NULL explicit; ADR-005 source key |
| pl3_integration_outbox.event_key | — | NOT NULL | UNIQUE | N/A | Stable idempotent event key |

**PL3 approved rules**
- DAILY inspection/escalation only; no Weekly/Monthly frequency field (ADR-002/003).
- Each escalation level is one logical Event. Retries use the same Email Queue item; do not create a new Event or queue item for each attempt (ADR-002/008).
- Valid Inspection receipt stops unsent escalation events while preserving processed history.
- Outbox is created in the same transaction as Inspection Result; PHP Worker retries the same Outbox row and source result (ADR-005).
- email_queue_id is nullable until the Event is enqueued.

**Open PL3 decisions**
- created_by_user_id: confirm whether a Draft Checklist Version must always be created by an authenticated Admin, or whether NULL is allowed for system/import. Current Data Dictionary says NOT NULL, while the general convention allows NULL for system-originated creation.
- ON DELETE for all actual PL3 FKs is RESTRICT under ADR-014; checklist/version/inspection history remains protected while referenced. Retention purge must be explicit and child-first.
- Make the Outbox source FK NOT NULL requirement explicit consistently before DDL.

## 6. PL4 – Issue / Assignment / Work Report / SLA

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| pl4_issues.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | APPROVED – ADR-001/011 |
| pl4_issues.source_inspection_result_id | pl3_inspection_results.id | NULL | UNIQUE nullable | RESTRICT | ADR-005/011; one Issue per PL3 result |
| pl4_issues.room_id | server_rooms.id | NOT NULL | FK index | RESTRICT | ADR-011 policy |
| pl4_issues.priority_id | pl4_priorities.id | NOT NULL | FK index | RESTRICT | ADR-004/011 |
| pl4_manual_submissions.issue_id | pl4_issues.id | NOT NULL | UNIQUE | RESTRICT | ADR-007/011 |
| pl4_manual_submissions(source_form_id, form_response_id) | — | NOT NULL | UNIQUE composite | N/A | ADR-007 idempotency |
| pl4_assignments.issue_id | pl4_issues.id | NOT NULL | UNIQUE (id, issue_id); active_issue_id generated UNIQUE nullable | RESTRICT | ADR-006/011 |
| pl4_assignments.assigned_by_user_id | users.id | Verify exact nullability | FK index | RESTRICT | Confirm final schema |
| pl4_assignment_claim_tokens.assignment_id | pl4_assignments.id | NOT NULL | NOT UNIQUE; token_hash NOT NULL UNIQUE | RESTRICT | ADR-006/011 |
| pl4_work_reports.issue_id | pl4_issues.id | NOT NULL | UNIQUE (id, issue_id) | RESTRICT | ADR-011 |
| pl4_work_reports.assignment_id | pl4_assignments.id | NOT NULL | Composite FK (assignment_id, issue_id) | RESTRICT | ADR-006/011 |
| pl4_uoa_confirmations.issue_id | pl4_issues.id | NOT NULL | FK index | RESTRICT | ADR-011 |
| pl4_uoa_confirmations.work_report_id | pl4_work_reports.id | NOT NULL | Composite FK (work_report_id, issue_id) | RESTRICT | ADR-011 |
| pl4_uoa_confirmations.uoa_user_id | users.id | NULL | FK index | RESTRICT | External actor may lack account |
| pl4_issue_status_history.issue_id | pl4_issues.id | NOT NULL | FK index | RESTRICT | Append-only history |
| pl4_issue_status_history.actor_user_id | users.id | NULL | FK index | RESTRICT | Actor snapshot retained |
| pl4_issue_sla_instances.issue_id | pl4_issues.id | NOT NULL | UNIQUE; UNIQUE (id, issue_id) | RESTRICT | ADR-010/011 |
| pl4_issue_sla_instances.policy_version_id | pl4_sla_policy_versions.id | NULL when NOT_CONFIGURED | FK index | RESTRICT | Policy selection snapshot |
| pl4_issue_sla_instances.calendar_version_id | pl4_sla_calendar_versions.id | NULL when NOT_CONFIGURED | FK index | RESTRICT | Calendar selection snapshot |
| pl4_issue_sla_instances.assignment_rule_id | pl4_sla_policy_assignment_rules.id | NULL when default/not configured | FK index | RESTRICT | Rule source snapshot |
| pl4_sla_policy_versions.policy_id | pl4_sla_policies.id | NOT NULL | UNIQUE (policy_id, version_no) | RESTRICT | ADR-010/011 |
| pl4_sla_policy_versions.calendar_version_id | pl4_sla_calendar_versions.id | NOT NULL | FK index | RESTRICT | Published Calendar required |
| pl4_sla_calendar_versions.calendar_id | pl4_sla_calendars.id | NOT NULL | UNIQUE (calendar_id, version_no) | RESTRICT | Published/used versions immutable |
| pl4_sla_calendar_periods.calendar_version_id | pl4_sla_calendar_versions.id | NOT NULL | FK index | RESTRICT | Calendar interval detail |
| pl4_sla_calendar_exceptions.calendar_version_id | pl4_sla_calendar_versions.id | NOT NULL | FK index | RESTRICT | Calendar exception detail |
| pl4_sla_policy_assignment_rules.policy_version_id | pl4_sla_policy_versions.id | NOT NULL | FK index | RESTRICT | Policy rule |
| pl4_sla_policy_assignment_rules.room_id | server_rooms.id | NULL | FK index | RESTRICT | NULL means all rooms |
| pl4_sla_cycles(issue_sla_instance_id, issue_id) | pl4_issue_sla_instances(id, issue_id) | NOT NULL | Composite FK | RESTRICT | ADR-011 |
| pl4_sla_cycles(assignment_id, issue_id) | pl4_assignments(id, issue_id) | NULL by lifecycle | Composite FK | RESTRICT | ADR-011 |
| pl4_sla_cycles(work_report_id, issue_id) | pl4_work_reports(id, issue_id) | NULL while resolution cycle runs | Composite FK; UNIQUE (work_report_id, cycle_type) | RESTRICT | ADR-010/011 |
| pl4_sla_cycles.active_issue_id | — | Generated NULL unless RUNNING | UNIQUE nullable | N/A | Max one RUNNING Cycle per Issue |

**PL4 approved policy – ADR-011**
- Every FK in PL4 issue/history/security/SLA data uses ON DELETE RESTRICT (InnoDB NO ACTION equivalent); no CASCADE DELETE.
- Do not hard-delete Issue, Assignment, Claim Token, Work Report, UOA Confirmation, Status History, Manual Submission, SLA Instance/Cycle or referenced Policy/Calendar Versions.
- Composite FKs prevent a child row from linking to a parent record belonging to another Issue.
- Preserve approved NULL/UNIQUE rules in Data Dictionary §13.4 and ADR-011.
- Do not generalize this PL4 policy to PL1, PL2, PL3, PL5 or COMMON without a separate decision.

## 7. PL5 – Work Catalog / Maintenance / Execution / Verification

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| pl5_work_catalog_items.category_id | pl5_work_catalog_categories.id | NOT NULL | FK index | RESTRICT | DOCUMENTED |
| pl5_maintenance_plans.room_id | server_rooms.id | NOT NULL | FK index | RESTRICT | DOCUMENTED |
| pl5_maintenance_plans.catalog_item_id | pl5_work_catalog_items.id | NULL | FK index | RESTRICT | Optional catalog binding |
| pl5_work_requests.business_entity_id | business_entities.id | NOT NULL | UNIQUE | RESTRICT | ADR-001 |
| pl5_work_requests.room_id | server_rooms.id | NOT NULL | FK index | RESTRICT | DOCUMENTED |
| pl5_work_requests.catalog_item_id | pl5_work_catalog_items.id | NULL | FK index | RESTRICT | Optional catalog binding |
| pl5_work_requests.maintenance_plan_id | pl5_maintenance_plans.id | NULL | FK index | RESTRICT | Optional plan origin |
| pl5_work_approvals.work_request_id | pl5_work_requests.id | NOT NULL | FK index | RESTRICT | Approval history |
| pl5_work_approvals.actor_user_id | users.id | NULL | FK index | RESTRICT | External actor possible |
| pl5_work_assignments.work_request_id | pl5_work_requests.id | NOT NULL | UNIQUE (id, work_request_id) | RESTRICT | ADR-012 |
| pl5_work_assignments.assigned_by_user_id | users.id | NULL | FK index | RESTRICT | Optional Admin actor |
| pl5_work_execution_events.work_request_id | pl5_work_requests.id | NOT NULL | FK index; UNIQUE (id, work_request_id) | RESTRICT | ADR-012/013 |
| pl5_work_execution_events(assignment_id, work_request_id) | pl5_work_assignments(id, work_request_id) | assignment_id NULL | Composite FK | RESTRICT | APPROVED – ADR-012 |
| pl5_work_verifications.work_request_id | pl5_work_requests.id | NOT NULL | FK index | RESTRICT | DOCUMENTED |
| pl5_work_verifications(execution_event_id, work_request_id) | pl5_work_execution_events(id, work_request_id) | execution_event_id NOT NULL | Composite FK; no UNIQUE execution_event_id | RESTRICT | APPROVED – ADR-013 |
| pl5_work_verifications.verifier_user_id | users.id | NULL | FK index | RESTRICT | External verifier possible |

**PL5 approved constraints – ADR-012/013**
- pl5_work_assignments has UNIQUE (id, work_request_id).
- Execution Event keeps work_request_id FK and optional assignment_id; when Assignment is present, Composite FK guarantees it belongs to the same Work Request.
- Execution Event has UNIQUE (id, work_request_id).
- Verification has mandatory execution_event_id and Composite FK to the exact Event + Work Request. Service must validate event_code = SUBMIT_RESULT.
- Do not add UNIQUE execution_event_id; do not add a direct Verification → Assignment FK.

**PL5 delete policy – ADR-014:** all actual PL5 FKs use ON DELETE RESTRICT. Work Requests, approvals, assignments, execution events, verifications and referenced catalog data cannot be deleted while dependent rows remain.

## 8. COMMON – Email, Audit, Evidence, Idempotency, Integration, Scheduler and Export

| Child table / FK | Parent key | Nullability | Key / index requirement | ON DELETE | Status / notes |
|---|---|---:|---|---|---|
| email_queue.business_entity_id | business_entities.id | NULL | FK/index | RESTRICT | NULL only for system event without business entity |
| email_queue_attempts.email_queue_id | email_queue.id | NOT NULL | UNIQUE (email_queue_id, attempt_no) | RESTRICT | Attempts belong to same queue item |
| system_audit_logs.business_entity_id | business_entities.id | NULL | INDEX (business_entity_id, occurred_at) | RESTRICT | ADR-001/009; system event may be NULL |
| system_audit_logs.actor_user_id | users.id | NULL | INDEX (actor_user_id, occurred_at) | RESTRICT | Preserve actor snapshot |
| evidence_files.uploaded_by_user_id | users.id | NULL | FK index | RESTRICT | Uploader may be external/system actor |
| entity_evidence_links.evidence_file_id | evidence_files.id | NOT NULL | UNIQUE (evidence_file_id, business_entity_id, purpose) as policy allows | RESTRICT | Link integrity |
| entity_evidence_links.business_entity_id | business_entities.id | NOT NULL | Composite UNIQUE above; FK index | RESTRICT | ADR-001 |
| entity_evidence_links.linked_by_user_id | users.id | NULL | FK index | RESTRICT | Actor may be external |
| data_exports.requested_by_user_id | users.id | NOT NULL as currently documented | FK index | RESTRICT | Preserve actor history; decision open |
| setting_change_history.actor_user_id | users.id | Confirm | FK index | RESTRICT | Verify full schema against ERD |
| integration_events.entity_type/entity_id | — | Metadata | No FK under current model | N/A | Not a polymorphic FK |
| idempotency_keys.entity_type/entity_id | — | NULL metadata | No FK under current model | N/A | ADR-012; not a polymorphic FK |
| scheduled_job_runs | — | — | run_uuid UNIQUE; inspect indexes/relations | N/A / TBD | No parent FK identified in current dictionary description |

**COMMON approved rules**
- Audit and Evidence point to business_entities.id, not polymorphic module/entity identifiers (ADR-001/009).
- Audit rows may have NULL business_entity_id only for system-level events not associated with a business record.
- Email Queue uses one item per recipient and stable event_key; attempts are children of the same queue item (ADR-008).
- Do not store raw OTP, raw action token, passwords or secrets in queue/audit metadata.
- idempotency_keys.entity_type/entity_id and integration_events.entity_type/entity_id are metadata, not foreign keys.
- PL4 Audit links to the Issue's Business Entity; child IDs are safe metadata, not direct polymorphic FKs.

**Remaining COMMON design items (not ON DELETE decisions)**
- data_exports.requested_by_user_id: preserve the User FK under ADR-014; disable accounts in normal operations. Exceptional erasure needs separately approved anonymization. The recommendation to preserve actor history is not yet approved.
- ON DELETE for all actual COMMON FKs is RESTRICT under ADR-014. Define and implement retention windows/legal holds and explicit child-first purge procedures for Email Queue/Attempts, Audit, Evidence, Idempotency, Integration Events, Scheduler Runs and Exports.
- Confirm whether scheduled_job_runs and integration_events require additional FK relationships or remain standalone operational records.
- Define privacy/retention controls for IP address, User-Agent, Audit metadata and Evidence storage.

## 9. Cross-document alignment findings

| ID | Finding | Required action | Status |
|---|---|---|---|
| CM-01 | pl3_escalation_events.email_queue_id is nullable in Data Dictionary; ERD labels FK NULL | Keep nullable notation in every physical representation | ALIGNMENT REQUIRED |
| CM-02 | pl3_integration_outbox.inspection_result_id is UNIQUE FK but NOT NULL should be explicit | Confirm and state NOT NULL consistently in Data Dictionary + ERD | ALIGNMENT REQUIRED |
| CM-03 | pl3_checklist_versions.created_by_user_id is NOT NULL in Data Dictionary, while general convention allows system-originated actor NULL | Approve actor semantics; synchronize docs | TBD – APPROVAL |
| CM-04 | data_exports.requested_by_user_id has FK; actor retention must align with global delete policy | Preserve the FK; disable User rather than hard-delete in normal operations; exceptional erasure requires separately approved audited anonymization | APPROVED – ADR-014 |
| CM-05 | PL1/PL2/PL3/PL5/COMMON delete behavior was unspecified | Apply ON DELETE RESTRICT to every actual FK across CORE, PL1–PL5 and COMMON | APPROVED – ADR-014 |
| CM-06 | entity_evidence_links.linked_by_user_id and other actor FKs need complete optional relationship notation in ERD | Ensure ERD shows actor relationships and nullability | ALIGNMENT REQUIRED |
| CM-07 | scheduled_job_runs and integration_events are described in Data Dictionary but ERD physical does not fully detail them | Add/confirm operational entities and indexes | ALIGNMENT REQUIRED |
| CM-08 | ON UPDATE behavior is not systematically specified | Keep separate from ADR-014; decide/document immutable-key policy before DDL | TBD – APPROVAL |
| CM-09 | PL5 Verification-to-Execution Event relationship | Resolved by ADR-013; no additional Assignment FK | APPROVED |
| CM-10 | PL3 Escalation Event to Email Queue cardinality/retry semantics | Resolved by ADR-002/008: one logical queue item per Event; retries reuse it | APPROVED |
| CM-11 | PL1 idempotency source response column | Resolved by ADR-012: use idempotency_keys, no source_response_id | APPROVED |

## 10. Decisions still required before Migration

1. PL3 created_by_user_id: required authenticated Admin or nullable for system/import?
2. COMMON data_exports.requested_by_user_id: retain User FK and prevent hard deletion, or introduce actor snapshot policy?
3. Confirm ADR-014 ON DELETE RESTRICT is reflected on every actual FK in Data Dictionary and physical ERD; define child-first retention purge dependencies.
4. ON UPDATE RESTRICT and immutable technical identifiers are approved globally under ADR-015.
5. Retention/archive/privacy for Audit, Evidence, IP and User-Agent.
6. Confirm explicit NOT NULL for pl3_integration_outbox.inspection_result_id.
7. Complete ERD physical representation for actor FKs, integration_events and scheduled_job_runs.

## 11. Migration readiness gate

Do not begin Migration until:
- Every FK in the Data Dictionary has a matching ERD relationship or an explicit explanation why it is metadata/non-FK.
- Every FK column has explicit NULL/NOT NULL, referenced key, supporting index, ON DELETE RESTRICT per ADR-014 and ON UPDATE RESTRICT per ADR-015.
- Every Composite FK has a matching parent UNIQUE key with same column order and compatible data types.
- All UNIQUE rules involving nullable columns are checked against MySQL 8.0 semantics.
- History/security/audit retention decisions are approved.
- ADR decisions and this matrix agree; any change to an approved decision requires a new ADR or explicit superseding decision.

**Boundary:** This matrix is a design review artifact only. It contains no executable DDL, no Migration code, no PHP code and makes no changes to any database.

## Global ON UPDATE policy – ADR-015 (APPROVED)

Áp dụng thống nhất `ON UPDATE RESTRICT` cho mọi khóa ngoại thực tế trong CORE, PL1, PL2, PL3, PL4, PL5 và COMMON.

- Không sử dụng `ON UPDATE CASCADE` hoặc `ON UPDATE SET NULL`.
- PK kỹ thuật và khóa cha đang được FK tham chiếu là bất biến sau khi tạo; Composite FK target cũng tuân thủ nguyên tắc này.
- Các trường nghiệp vụ không phải khóa (tên, email, mô tả, trạng thái, business code...) vẫn có thể cập nhật theo quyền, validation và Audit tương ứng; không thay đổi technical ID.
- Nếu gán sai quan hệ, cần quy trình correction/relink được ủy quyền, có Audit và bảo toàn lịch sử; không sửa PK cha để xử lý.
- Metadata `entity_type/entity_id` không phải FK nên không có ON UPDATE action.
- ON DELETE tiếp tục theo ADR-014: RESTRICT cho mọi FK thực tế.

**Combined FK rule:** ON DELETE RESTRICT + ON UPDATE RESTRICT. Mọi FK phải ghi rõ cả hai hành vi trước Migration. Đây là thiết kế, không phải DDL/Migration.
