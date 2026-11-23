# Kế hoạch thứ tự Migration – UOA Server Room Management

**Nhánh:** feature/ITSMWEB  
**CSDL mục tiêu:** MySQL 8.0+, InnoDB, utf8mb4  
**Trạng thái:** DESIGN ONLY – chưa phải Migration/DDL triển khai  
**Căn cứ:** ERD_PHYSICAL.md, DATA_DICTIONARY.md, IMPLEMENTATION_PLAN.md, ADR-001 đến ADR-011.

## 1. Nguyên tắc sắp xếp

1. Tạo bảng cha trước bảng con; chỉ tạo FK khi bảng được tham chiếu đã tồn tại.
2. Tạo bảng nền tảng và business_entities trước các bảng hồ sơ gốc PL1–PL5.
3. Email Queue phải được tạo trước pl3_escalation_events, vì Event tham chiếu Queue.
4. PL3 Checklist Version/Items và Inspection/Results phải có trước PL4 Issue vì Issue tham chiếu kết quả PL3 và Checklist Version Item.
5. PL4 Issue phải có trước Assignment/Manual Submission/Work Report; Assignment và Work Report phải có trước Claim Token và các liên kết SLA tương ứng.
6. SLA Calendar/Policy/Version/Rule phải có trước Issue SLA Instance; SLA Instance, Assignment và Work Report phải có trước SLA Cycle.
7. Tạo UNIQUE key hỗ trợ trước khi tạo Composite FK theo ADR-011.
8. Các FK lịch sử PL4 dùng ON DELETE RESTRICT; không dùng CASCADE DELETE.
9. Đây là thứ tự phụ thuộc logic. Khi triển khai phải kiểm tra lại toàn bộ FK thực tế trong Data Dictionary và schema cuối cùng.

## 2. Thứ tự Migration theo đợt

| Đợt | Phạm vi | Bảng chính | Điều kiện phụ thuộc |
|---:|---|---|---|
| 01 | CORE – tài khoản và danh mục gốc | users, roles, permissions, server_rooms | Không phụ thuộc bảng nghiệp vụ |
| 02 | CORE – quan hệ và cấu hình | user_roles, role_permissions, room_contacts, business_settings, setting_change_history | Đợt 01; Setting History cần Settings và Users |
| 03 | Business Entity registry | business_entities | Đợt 01; tạo trước mọi hồ sơ gốc có business_entity_id |
| 04 | COMMON – queue/audit/evidence | email_queue, email_queue_attempts, system_audit_logs, evidence_files, entity_evidence_links, idempotency_keys, integration_events, scheduled_job_runs, data_exports | business_entities, users; Evidence Links cần Evidence Files + Business Entities; Queue Attempts cần Email Queue |
| 05 | PL1 – hồ sơ gốc | pl1_access_requests, pl1_access_records | business_entities, server_rooms; Access Records còn tham chiếu Access Requests |
| 06 | PL1 – bảng con | pl1_approval_decisions, pl1_action_tokens, pl1_access_record_history | Access Requests và Access Records |
| 07 | PL2 – hồ sơ gốc | pl2_visits | business_entities, server_rooms |
| 08 | PL2 – bảng con | pl2_approval_history, pl2_action_tokens, pl2_otp_challenges, pl2_visit_events, pl2_otp_attempts | Visits; OTP Attempts cần OTP Challenges |
| 09 | PL3 – danh mục Checklist | pl3_checklist_items, pl3_checklist_versions, pl3_checklist_version_items | Users cho các cột actor; Version Items cần Items + Versions |
| 10 | PL3 – Inspection | pl3_inspections, pl3_inspection_results, pl3_acknowledgements | business_entities, server_rooms, Checklist Versions/Version Items; Results cần Inspections + Version Items |
| 11 | PL3 – Escalation và Outbox | pl3_escalation_runs, pl3_escalation_events, pl3_integration_outbox | Escalation Runs cần Rooms; Events cần Runs + Email Queue; Outbox cần Inspection Results |
| 12 | PL4 – danh mục và Issue | pl4_priorities, pl4_issues | business_entities, server_rooms, PL3 Inspection Results/Checklist Version Items; Priority trước Issue |
| 13 | PL4 – nguồn và Assignment | pl4_manual_submissions, pl4_assignments, pl4_assignment_claim_tokens | Issue; Assignment cần Issue; Claim Token cần Assignment; Manual Submission cần Issue |
| 14 | PL4 – Work Report và xác nhận | pl4_work_reports, pl4_uoa_confirmations, pl4_issue_status_history | Issue + Assignment; UOA Confirmation cần Work Report + Issue |
| 15 | PL4 – SLA cấu hình | pl4_sla_calendars, pl4_sla_calendar_versions, pl4_sla_calendar_periods, pl4_sla_calendar_exceptions, pl4_sla_policies, pl4_sla_policy_versions, pl4_sla_policy_assignment_rules | Calendar trước Version/Periods/Exceptions; Policy trước Policy Version; Calendar Version trước Policy Version; Rule cần Policy Version và Rooms |
| 16 | PL4 – SLA runtime | pl4_issue_sla_instances, pl4_sla_cycles | Issue + Policy Version/Calendar Version/Rule; Cycle cần Instance + Issue + Assignment/Work Report |
| 17 | PL4 – ràng buộc bổ sung | Composite UNIQUE keys và Composite FK theo ADR-011; rà soát CHECK/UNIQUE/index còn lại | Tất cả bảng PL4 liên quan đã tồn tại; tạo UNIQUE target trước Composite FK |
| 18 | PL5 – danh mục | pl5_work_catalog_categories, pl5_work_catalog_items | Category trước Catalog Item |
| 19 | PL5 – kế hoạch và hồ sơ gốc | pl5_maintenance_plans, pl5_work_requests | Rooms + Catalog Items; Work Requests còn cần Business Entities và Maintenance Plans nếu FK được bật |
| 20 | PL5 – workflow con | pl5_work_approvals, pl5_work_assignments, pl5_work_execution_events, pl5_work_verifications | Work Requests; Execution Events cần Work Requests + Work Assignments |
| 21 | Kiểm tra cuối | FK audit, index, CHECK, seed data và schema verification | Toàn bộ các đợt trước |

## 3. Lưu ý về các điểm phụ thuộc đặc biệt

### 3.1 Business Entity và các hồ sơ gốc

business_entities phải được tạo trước PL1 Access Request/Access Record, PL2 Visit, PL3 Inspection, PL4 Issue và PL5 Work Request. Không tạo FK ngược từ business_entities về từng bảng subtype; việc bảo đảm entity_type/module_code khớp subtype do Service kiểm tra theo ADR-001.

### 3.2 Email Queue và PL3 Escalation Event

email_queue tham chiếu Business Entity theo thiết kế chung. pl3_escalation_events.email_queue_id tham chiếu Email Queue, do đó Email Queue phải có trước bảng Events. Không có FK ngược từ Email Queue về Escalation Event nên không tạo vòng FK.

### 3.3 PL3 → PL4

PL4 Issue tham chiếu pl3_inspection_results và pl3_checklist_version_items. Vì vậy toàn bộ bảng nguồn PL3 phải được tạo trước PL4 Issue. Outbox tham chiếu Inspection Result và được tạo ở đợt PL3 trước PL4 để hoàn thiện miền tích hợp nguồn.

### 3.4 PL4 Composite FK

Theo ADR-011, tạo UNIQUE (id, issue_id) ở pl4_assignments, pl4_work_reports, pl4_issue_sla_instances trước khi tạo Composite FK tương ứng. Composite FK bảo đảm Assignment/Work Report/SLA Instance được tham chiếu cùng Issue. Các FK nullable trong SLA Cycle giữ NULL theo lifecycle; Service xác thực tính bắt buộc theo cycle_type trước Commit.

### 3.5 PL5 Work Request và Maintenance Plan

pl5_maintenance_plans cần Catalog Item và Room. pl5_work_requests có thể tham chiếu Maintenance Plan, vì vậy Maintenance Plan phải được tạo trước Work Request. Nếu rà soát DDL phát hiện FK ngược chưa được thể hiện trong ERD, phải cập nhật ERD/Data Dictionary trước khi chốt Migration; không tự thêm vòng FK.

## 4. Chiến lược chia nhỏ Migration

- Mỗi Migration tạo một nhóm bảng nhỏ có quan hệ rõ ràng; tránh một Migration khổng lồ cho toàn hệ thống.
- Có thể tạo bảng trước, sau đó bổ sung FK/UNIQUE/index ở Migration kế tiếp nếu phụ thuộc cần xử lý sau.
- Với quan hệ vòng được phát hiện khi chuẩn hóa DDL, tạo cả hai bảng trước với cột nullable/không FK tạm thời nếu phù hợp nghiệp vụ, sau đó thêm FK riêng. Không tự thay đổi NULL hoặc nghiệp vụ chỉ để né thứ tự.
- Seed danh mục nền (Roles, Permissions, Priority, trạng thái/danh mục được xác định) sau khi bảng tương ứng tồn tại; seed phải idempotent.
- Kiểm tra schema sau từng đợt: bảng, cột, type, NULL, default, UNIQUE, FK, ON DELETE, index và charset/collation.

## 5. Rollback và an toàn triển khai

MySQL DDL có thể gây implicit commit; không giả định chuỗi Migration DDL có thể rollback nguyên tử bằng một transaction. Do đó:
1. Backup và xác minh khả năng restore trước mỗi đợt thay đổi schema lớn.
2. Triển khai theo thứ tự 01 → 21, kiểm tra sau từng đợt.
3. Không dùng rollback destructive để xóa dữ liệu đã phát sinh; ưu tiên Migration sửa tiếp (forward-fix).
4. Chỉ rollback một Migration khi chắc chắn chưa có dữ liệu/đối tượng phụ thuộc và đã đánh giá tác động.
5. Không DROP bảng/cột hoặc xóa dữ liệu lịch sử chỉ để làm Migration chạy lại.

## 6. Các việc còn phải hoàn tất trước khi viết Migration

- Đối chiếu toàn bộ cột FK trong DATA_DICTIONARY với ERD_PHYSICAL, đặc biệt PL5 và COMMON.
- Chốt các câu hỏi còn mở trong WORKFLOW_SPECIFICATION và Data Dictionary; không tự suy diễn những điểm đang CHỜ CHỐT.
- Chốt chính sách retention/archival cho Audit, Evidence, Token, Email Queue và dữ liệu nghiệp vụ.
- Chốt seed data và quy tắc cập nhật danh mục.
- Chuẩn hóa DDL cuối cùng: tên constraint, kiểu dữ liệu hai đầu FK, NULL, UNIQUE, CHECK, index, ON DELETE.
- Có xác nhận riêng của chủ nghiệp vụ trước khi bắt đầu viết Migration/PHP.

## 7. Trạng thái

Đây là kế hoạch thứ tự phụ thuộc ở mức thiết kế. Không tạo hoặc chạy Migration, không thay đổi Database, không viết PHP. Thứ tự cuối cùng phải được xác minh lại trên DDL đã chuẩn hóa trước khi triển khai.