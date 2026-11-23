# ADR-011 – PL4 Referential Integrity: Composite FK & Delete Policy

**Trạng thái:** APPROVED  
**Phiên bản:** 1.0  
**Ngày phê duyệt:** 23/11/2026  
**Phạm vi:** PL4 – Issue, Assignment, Claim Token, Work Report, UOA Confirmation và SLA.

## 1. Bối cảnh

Các FK đơn lẻ bảo đảm mỗi ID tham chiếu tồn tại, nhưng chưa bảo đảm các bản ghi liên quan cùng thuộc một ISSUE. Ví dụ Work Report có thể tham chiếu một Assignment hợp lệ nhưng Assignment đó thuộc Issue khác. Cần ràng buộc ở Database để ngăn dữ liệu chéo hồ sơ.

## 2. Quyết định được phê duyệt

### D1 – Composite FK

Bổ sung UNIQUE key hỗ trợ trên bảng cha:
- `pl4_assignments(id, issue_id)`.
- `pl4_work_reports(id, issue_id)`.
- `pl4_issue_sla_instances(id, issue_id)`.

Tạo các Composite FK:
1. `pl4_work_reports(assignment_id, issue_id)` → `pl4_assignments(id, issue_id)`.
2. `pl4_uoa_confirmations(work_report_id, issue_id)` → `pl4_work_reports(id, issue_id)`.
3. `pl4_sla_cycles(issue_sla_instance_id, issue_id)` → `pl4_issue_sla_instances(id, issue_id)`.
4. `pl4_sla_cycles(assignment_id, issue_id)` → `pl4_assignments(id, issue_id)`.
5. `pl4_sla_cycles(work_report_id, issue_id)` → `pl4_work_reports(id, issue_id)`.

Các FK ghép bảo đảm bản ghi con và bản ghi được tham chiếu thuộc cùng Issue. Với assignment_id/work_report_id nullable của SLA Cycle, NULL được giữ theo lifecycle đã phê duyệt; Service phải kiểm tra field bắt buộc theo cycle_type và trạng thái trước Commit.

### D2 – ON DELETE policy

Toàn bộ FK thuộc hồ sơ, lịch sử, bảo mật và SLA PL4 dùng `ON DELETE RESTRICT` (InnoDB `NO ACTION` tương đương). Không dùng `ON DELETE CASCADE`.

Không hard-delete Issue, Assignment, Claim Token, Work Report, UOA Confirmation, Status History, Manual Submission, SLA Instance/Cycle hoặc Policy/Calendar Version đã được tham chiếu. Kết thúc/hủy bằng trạng thái nghiệp vụ; danh mục ngừng sử dụng bằng INACTIVE/RETIRED. Claim Token đã hết hạn, dùng hoặc thu hồi vẫn được giữ để truy vết.

### D3 – NULL/UNIQUE

Giữ các quy tắc:
- `pl4_issues.source_inspection_result_id`: NULL được phép + UNIQUE.
- `pl4_assignments.active_issue_id`: generated nullable + UNIQUE; nhiều NULL được phép.
- `pl4_manual_submissions.issue_id`: NOT NULL + UNIQUE; UNIQUE `(source_form_id, form_response_id)`.
- `pl4_issue_sla_instances.issue_id`: NOT NULL + UNIQUE.
- `pl4_sla_cycles.assignment_id`, `work_report_id`: nullable theo lifecycle; không UNIQUE `(assignment_id, cycle_type)`; giữ UNIQUE `(work_report_id, cycle_type)`.
- `pl4_assignment_claim_tokens.assignment_id`: NOT NULL, không UNIQUE; `token_hash`: NOT NULL + UNIQUE.

## 3. Tác động và phạm vi

- Chặn Work Report dùng Assignment của Issue khác.
- Chặn UOA Confirmation xác nhận Work Report thuộc Issue khác.
- Chặn SLA Cycle tham chiếu SLA Instance, Assignment hoặc Work Report của Issue khác.
- Bảo toàn lịch sử và bằng chứng; không cascade xóa dữ liệu nghiệp vụ.
- Không thay đổi PL4 state machine, Assignment/Claim lifecycle, Manual Submission atomicity, Email Queue, SLA policy hoặc SLA calculation đã phê duyệt.

## 4. Tài liệu đồng bộ

- `DATA_DICTIONARY.md` §13.
- `ERD_PHYSICAL.md` §14.
- `ERD_DESIGN.md` §10.

**Giới hạn:** ADR này phê duyệt thiết kế vật lý; chưa viết DDL, Migration hoặc PHP. Composite FK/UNIQUE/index sẽ được cụ thể hóa khi chuẩn hóa Migration.
