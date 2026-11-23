# ERD TỔNG THỂ – UOA SERVER ROOM MANAGEMENT
**Phiên bản:** 0.4 – Đã đồng bộ các quyết định ADR-001 đến ADR-009; vẫn là thiết kế để rà soát, chưa phải migration chính thức  
**Ngày:** 22/11/2026  
**Phạm vi:** PL1–PL5, Admin, Email Queue, Evidence và Audit Center  
**Nhánh:** `feature/ITSMWEB`

## 1. Mục tiêu và nguyên tắc thiết kế

Tài liệu này xác định các thực thể, khóa chính/khóa ngoại và quan hệ dữ liệu trước khi chuẩn hóa MySQL Migration.

Nguyên tắc:
1. MySQL là nguồn dữ liệu chính; không phụ thuộc Google Sheets/Forms khi vận hành.
2. Mỗi phân hệ PL1–PL5 có bảng nghiệp vụ và lịch sử riêng; không gom mọi nghiệp vụ vào một bảng chung.
3. Dùng chung danh mục Server Room, tài khoản quản trị, cấu hình, Email Queue, Evidence và `system_audit_logs`.
4. Lưu snapshot người duyệt/phạm vi áp dụng tại thời điểm phát sinh yêu cầu để thay đổi cấu hình sau này không làm sai lịch sử.
5. Actor bên ngoài (Requester, Assignee, Executor, Inspector...) không bắt buộc phải có tài khoản Admin. Lưu thông tin actor dạng snapshot; chỉ liên kết `users.id` khi actor thực sự có tài khoản.
6. Không xóa hồ sơ nghiệp vụ đã phát sinh. Dùng trạng thái, lịch sử và cờ vô hiệu hóa danh mục.
7. Mọi quyết định/trạng thái chỉ được thay đổi qua Workflow Service; không cho CRUD trực tiếp bỏ qua quy tắc nghiệp vụ.
8. Audit Log tập trung ghi sự kiện; bảng History chuyên biệt vẫn là nguồn chi tiết của từng bước nghiệp vụ.
9. Mọi thời gian lưu DB theo UTC; giao diện hiển thị Asia/Ho_Chi_Minh.
10. Tài liệu này không tự quyết định các câu hỏi nghiệp vụ còn CHỜ XÁC NHẬN trong WORKFLOW_SPECIFICATION.md.

## 2. Nhóm thực thể tổng thể

### 2.1. Dữ liệu nền tảng và phân quyền

| Bảng | Mục đích | Khóa/quan hệ chính |
|---|---|---|
| `users` | Tài khoản đăng nhập Admin | PK `id`; unique `email` |
| `roles` | Ba vai trò Super Admin, UOA, MSTAR | PK `id`; unique `code` |
| `permissions` | Quyền chức năng chi tiết | PK `id`; unique `code` |
| `user_roles` | Gán nhiều vai trò cho tài khoản | PK ghép `user_id, role_id`; FK users, roles |
| `role_permissions` | Ma trận quyền theo vai trò | PK ghép `role_id, permission_id`; FK roles, permissions |
| `server_rooms` | Danh mục phòng Server dùng chung PL1–PL5 | PK `id`; unique `room_code` |
| `business_entities` | Định danh chung cho hồ sơ nghiệp vụ gốc PL1–PL5 | PK `id`; unique `(module_code, entity_type, business_code)`; FK được tham chiếu từ bảng hồ sơ, Audit và Evidence |
| `room_contacts` | Đầu mối theo phòng: Approver, MSTAR, Manager... | FK `room_id`; user_id nullable; lưu tên/email snapshot |
| `system_settings` | Cấu hình kỹ thuật và cấu hình hệ thống | unique `setting_key`; secret tách kho bảo mật |
| `business_settings` | Cấu hình nghiệp vụ động theo PL/phòng/nhóm | key + scope; kiểu dữ liệu, đơn vị, phạm vi, hiệu lực |
| `setting_change_history` | Lịch sử trước/sau của cấu hình | FK setting; actor; before/after; thời điểm |

**Lưu ý:** Tài liệu nghiệp vụ yêu cầu chỉ có 3 vai trò Admin chính: SUPER_ADMIN, UOA, MSTAR. Requester/Executor/Assignee/Inspector là actor nghiệp vụ, không tạo thêm role Admin.

### 2.2. PL1 – Access Authorization

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl1_access_requests` | Yêu cầu NEW / CHANGE / REVOKE | FK room; approver snapshot; requester snapshot |
| `pl1_approval_decisions` | Lịch sử quyết định duyệt/từ chối | FK access_request; approver actor; channel |
| `pl1_access_records` | Access Register, lưu quyền đã cấp | FK room; tham chiếu request tạo/cập nhật gần nhất |
| `pl1_access_record_history` | Lịch sử thay đổi quyền | FK access_record; request; before/after; actor |
| `pl1_action_tokens` | Token hành động phê duyệt có hash | liên kết đúng request/action; expiry/revoked/used |

Quy tắc quan hệ:
- Một Room có nhiều Access Request và Access Record.
- Một Access Request có nhiều Approval Decision/history, nhưng chỉ một quyết định cuối cùng hợp lệ theo workflow.
- Một Access Record có nhiều dòng history.
- Access Record được nhận diện nghiệp vụ theo Email người được cấp quyền + Room; không dùng email đơn lẻ làm khóa.
- NEW có thể tạo Access Record mới hoặc tái sử dụng dòng REVOKED theo quy tắc đã chốt; CHANGE/REVOKE chỉ tác động bản ghi ACTIVE đúng phòng.
- Trạng thái quyền gồm tối thiểu ACTIVE, REVOKED, EXPIRED; không xóa dòng khi hết hạn/thu hồi.

### 2.3. PL2 – Entry / Check-in / Check-out

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl2_visits` | Hồ sơ đăng ký một lượt vào phòng | FK room; requester snapshot; approver snapshot |
| `pl2_approval_history` | Lịch sử duyệt, từ chối, reminder, expiry | FK visit; actor/channel |
| `pl2_action_tokens` | Token riêng cho APPROVAL, CHECKIN, CHECKOUT | FK visit; lưu hash, action, expiry, used/revoked |
| `pl2_otp_challenges` | Mỗi lần yêu cầu OTP là một challenge | FK visit; hash; sent_at; expires_at; status |
| `pl2_otp_attempts` | Lịch sử từng lần nhập OTP | FK otp_challenge; result; không lưu OTP rõ |
| `pl2_visit_events` | Lịch sử check-in/out và các bước đặc thù | FK visit; actor; event data |
| `evidence_files` | Ảnh/file minh chứng dùng chung | tham chiếu entity qua bảng liên kết |

Quy tắc:
- Một Room có nhiều Visit; một Visit có nhiều Approval History, Action Token, OTP Challenge, OTP Attempt và Visit Event.
- OTP mới vô hiệu hóa challenge/token OTP cũ còn chờ; thời hạn 10 phút chỉ bắt đầu sau khi email gửi thành công.
- Tối đa 5 lần nhập sai cho challenge theo quy tắc đã chốt; không lưu OTP dạng rõ.
- CHECK-IN ghi người thực tế vào phòng và Entry Time; CHECK-OUT chỉ cho Visit đang OPEN, ghi Work Result, Exit Time và minh chứng nếu có.
- Token CHECK-IN/CHECK-OUT phải giới hạn đúng Visit và đúng action; không dùng chung token giữa các bước.

### 2.4. PL3 – Inspection

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl3_inspections` | Phiếu kiểm tra theo phòng/ngày | FK room + checklist version; inspector snapshot |
| `pl3_checklist_items` | Danh mục gốc câu hỏi/checklist item | unique item_code; không xóa item đã sử dụng |
| `pl3_checklist_versions` | Phiên bản bộ checklist DAILY | version_code; trạng thái DRAFT/PUBLISHED/RETIRED |
| `pl3_checklist_version_items` | Nội dung câu hỏi được chụp theo từng phiên bản | FK version + item; snapshot nội dung, thứ tự, quy tắc trả lời |
| `pl3_inspection_results` | Kết quả từng item của phiếu | FK inspection + checklist version item |
| `pl3_acknowledgements` | UOA xác nhận đã tiếp nhận hồ sơ | FK inspection; actor/channel/time |
| `pl3_escalation_runs` | Một chu kỳ cảnh báo thiếu inspection theo phòng/ngày/lịch | UNIQUE `(room_id, work_date, schedule_type)`; DAILY hiện tại; config snapshot |
| `pl3_escalation_events` | Từng cấp cảnh báo, có trạng thái queue/retry riêng | FK escalation_run; recipient snapshot; email queue |
| `calendar_holidays` | Ngày nghỉ áp dụng lịch kiểm tra | ngày; tên; active |
| `pl3_integration_outbox` | Outbox PL3 → PL4 xử lý nội bộ bằng PHP Worker | FK inspection result UNIQUE; event key UNIQUE; trạng thái/retry/lock |

Quy tắc:
- Bộ checklist được quản lý theo phiên bản. Phiên bản PUBLISHED là bất biến; muốn sửa/thêm/ngừng câu hỏi phải tạo phiên bản DRAFT mới rồi publish. Mỗi Inspection lưu checklist_version_id và dùng đúng nội dung câu hỏi đã chụp trong phiên bản đó.
- Phiên bản đầu tiên khởi tạo 12 câu hỏi I01–I12 theo Phụ lục 3. Số lượng câu hỏi không hard-code là 12: khi SUBMITTED, service phải có đúng một kết quả cho mỗi item thuộc phiên bản đã gắn với Inspection, không thiếu/không trùng/không có item ngoài phiên bản. UNIQUE (inspection_id, checklist_version_item_id) bảo đảm không lặp.
- FAIL bắt buộc có remarks/action.
- Một Inspection có thể có nhiều acknowledgement; mỗi Run có tối đa một Event logic cho mỗi cấp.
- Phạm vi kiểm tra được số hóa theo ADR-003 đã phê duyệt là DAILY; Form không có trường Inspection Frequency và bảng `pl3_inspections` không lưu `inspection_frequency`. Weekly/Monthly ngoài phạm vi hiện tại. Lịch cảnh báo thiếu phiếu cũng chỉ là DAILY theo ADR-002.
- Mỗi Run được định danh duy nhất bằng `(room_id, work_date, schedule_type)`; trong phạm vi hiện tại `schedule_type = DAILY`.
- Run lưu snapshot cấu hình áp dụng. Event lưu recipient snapshot, mốc dự kiến/thử/gửi, trạng thái và liên kết Email Queue.
- Level 1 đến hạn tại `DAILY_DEADLINE + ALERT_LEVEL1_DELAY_MINUTES`; Level 2/3 tính tuần tự từ thời điểm ghi nhận kết quả cấp trước cộng delay cấu hình tương ứng. Nếu cấp trước `SKIPPED`, timestamp SKIPPED vẫn là mốc tính cấp kế tiếp.
- Khi Inspection hợp lệ đúng phòng/ngày được ghi nhận, Run dừng các Event chưa gửi; Event đã xử lý được giữ nguyên lịch sử.
- PL3 → PL4 dùng Outbox nội bộ trên cùng MySQL trong ứng dụng PHP. Outbox row được tạo cùng transaction với Inspection Result; PHP Worker claim/xử lý/retry an toàn khi chạy đồng thời. Không tiếp tục Apps Script Web App hoặc Google Sheets làm nguồn tích hợp khi vận hành PHP.
- Mỗi `pl3_inspection_results` có tối đa một outbox event (UNIQUE `inspection_result_id`) và tối đa một PL4 Issue (UNIQUE `source_inspection_result_id`); retry cập nhật cùng outbox row, không tạo event/Issue mới. Outbox xác định Inspection thông qua `inspection_result_id → inspection_id`, tránh lưu hai FK nguồn có thể lệch nhau.
- ISSUE PL3 lưu FK nguồn để truy nguyên và snapshot Inspection/Room/Checklist version/item/FAIL remarks tại thời điểm tạo. Snapshot bất biến; thay đổi Checklist về sau không cập nhật ISSUE đã tạo.

### 2.5. PL4 – Issue Management

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl4_priorities` | Danh mục Priority động do Super Admin quản lý | unique code; active flag; không xóa mã đã dùng |
| `pl4_issues` | Hồ sơ sự cố/vi phạm | FK room, Priority; source inspection result nullable; Priority snapshot |
| `pl4_assignments` | Lịch sử phân công/nhận việc/tái phân công | FK issue; assignee/assigner snapshot; claim/release time; status |
| `pl4_work_reports` | Mỗi lần Assignee báo cáo COMPLETED/NOT_COMPLETED | FK issue + Assignment; actor; result; notes |
| `pl4_uoa_confirmations` | Mỗi lần UOA xác nhận/không xác nhận | FK issue + work_report; UOA actor; reason |
| `pl4_issue_status_history` | Lịch sử chuyển trạng thái | FK issue; from/to; actor; reason |
| `pl4_manual_submissions` | Nguồn tiếp nhận/idempotency cho ISSUE MANUAL; một Form Response liên kết đúng một ISSUE | FK issue UNIQUE; UNIQUE (source_form_id, form_response_id) |

Quy tắc:
- ISSUE nguồn PL3 tham chiếu đúng `pl3_inspection_results.id`; một FAIL result chỉ sinh tối đa một ISSUE (UNIQUE `source_inspection_result_id`). Không dùng `item_code` làm khóa liên kết duy nhất.
- ISSUE MANUAL không có nguồn PL3.
- `pl4_priorities` là danh mục động; khởi tạo LOW/MEDIUM/HIGH/CRITICAL. Không xóa cứng Priority đã sử dụng; mã đã dùng không đổi.
- `pl4_issues` lưu `priority_id`, `priority_code_snapshot`, `priority_name_snapshot`; snapshot không đổi khi danh mục được chỉnh sửa.
- ISSUE MANUAL chọn Priority đang active. ISSUE PL3 lấy Priority mặc định từ `business_settings` key `PL4_PL3_DEFAULT_PRIORITY_CODE`, khởi tạo HIGH; nếu cấu hình không hợp lệ/inactive thì chặn tạo và ghi Audit Log, không tự fallback.
- Priority không tự gắn SLA/escalation và không thay đổi state machine PL4.
- Một ISSUE có thể có nhiều Assignment, Work Report, UOA Confirmation và Status History. ISSUE PL3 mới tạo ở OPEN, chưa có Assignee; Reporter/người có quyền phân công tạo Assignment mới, Assignee được chỉ định nhận qua link bảo mật. Mỗi ISSUE chỉ có tối đa một Assignment đang hiệu lực (ASSIGNED hoặc CLAIMED). Claim phải khóa/kiểm tra nguyên tử; hai người không thể cùng nhận. Từ chối/hết hạn/giải phóng/tái phân công tạo trạng thái hoặc Assignment lịch sử mới, không xóa Assignment cũ. Work Report tham chiếu Assignment đã nhận.
- COMPLETED và NOT_COMPLETED đều chuyển PENDING_UOA_CONFIRMATION.
- UOA xác nhận → CLOSED; UOA không xác nhận (bắt buộc lý do) → IN_PROGRESS.
- Assignee không có quyền tự đóng ISSUE; mọi lần báo cáo và xác nhận phải lưu riêng, không ghi đè.
- ISSUE MANUAL được tiếp nhận qua `pl4_manual_submissions`; cặp `source_form_id + form_response_id` là khóa idempotency UNIQUE. Retry cùng submission trả về ISSUE hiện hữu, không tạo ISSUE mới.
- Tạo `business_entities` + `pl4_issues` + `pl4_manual_submissions` + Assignment đầu tiên (`ASSIGNED`) + SLA Instance/Cycle (nếu Policy hợp lệ) + `system_audit_logs` + `email_queue` trong cùng MySQL/InnoDB transaction. Manual Form bắt buộc chọn Assignee hợp lệ; lỗi ghi DB ở bất kỳ bước nào rollback toàn bộ; Email Queue Worker chỉ gửi SMTP sau Commit theo ADR-007.
- Theo ADR-008, email PL4 dùng Email Queue chung; Assignee gửi Work Report phải enqueue hai email riêng cho Reporter và UOA Approver theo Room trong cùng transaction với Work Report, Status History và Audit. Không gửi MSTAR ở sự kiện này; email lỗi không rollback nghiệp vụ.
- Theo ADR-009, Timeline ISSUE tổng hợp dữ liệu từ Issue Status History, Assignments, Work Reports, UOA Confirmations, system_audit_logs, email_queue và email_queue_attempts. Không tạo bảng PL4 audit/notification history riêng; Audit của mọi thao tác trên bản ghi con liên kết về business_entities.id của ISSUE.

### 2.6. PL5 – Work & Maintenance

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `pl5_work_catalog_categories` | Nhóm danh mục công việc | danh mục |
| `pl5_work_catalog_items` | Catalog công việc, hướng dẫn/risk | FK category |
| `pl5_maintenance_plans` | Kế hoạch bảo trì định kỳ | FK room; catalog item nullable/required theo quy tắc |
| `pl5_work_requests` | Công việc phát sinh/can thiệp hoặc phiếu bảo trì | FK room; requester snapshot; approver snapshot; catalog |
| `pl5_work_approvals` | Lịch sử UOA approve/reject/cancel | FK work request; actor/channel/note |
| `pl5_work_assignments` | Lịch sử giao Executor | FK work request; executor snapshot |
| `pl5_work_execution_events` | START / HOLD / RESUME / SUBMIT_RESULT | FK work request; actor; time; reason/result |
| `pl5_work_verifications` | Lịch sử UOA xác minh PASS/FAIL | FK work request; verifier; result/note |
| `pl5_work_attachments` | Liên kết file minh chứng | FK work request + evidence file |
| `pl5_resources` | Nguồn lực/nhân sự/đơn vị thực hiện nếu cần quản lý danh mục | danh mục, chưa ép thành user Admin |

Quy tắc:
- Work Catalog chỉ Super Admin quản lý.
- UOA và MSTAR xem PL5 Dashboard read-only toàn bộ phòng; quyền xem này không kéo theo quyền thao tác.
- Người yêu cầu được hủy khi PENDING_APPROVAL, bắt buộc lý do.
- Approver và Verifier là UOA theo Room, không phải Requester.
- Workflow: PENDING_APPROVAL → APPROVED/REJECTED/CANCELLED; APPROVED → IN_PROGRESS; HOLD → ON_HOLD; RESUME → IN_PROGRESS; SUBMIT_RESULT → PENDING_VERIFICATION; VERIFY PASS → CLOSED; VERIFY FAIL → IN_PROGRESS + REWORK_REQUIRED.
- Work Approval, Execution, Verification phải lưu lịch sử riêng.

### 2.7. Dịch vụ dùng chung

| Bảng | Mục đích | Quan hệ |
|---|---|---|
| `email_queue` | Hàng đợi email nghiệp vụ dùng chung PL1–PL5; mỗi người nhận là một queue item | module + Business Entity; event_key UNIQUE; trạng thái/retry |
| `email_queue_attempts` | Lịch sử từng lần gửi | FK email_queue |
| `business_entities` | Định danh thống nhất cho hồ sơ nghiệp vụ gốc PL1–PL5 | PK `id`; một dòng tương ứng một hồ sơ gốc; FK từ Audit/Evidence |
| `system_audit_logs` | Audit Log tập trung PL1–PL5 | FK `business_entity_id` nullable; actor, event, channel, correlation |

Theo ADR-009, `system_audit_logs.business_entity_id` là FK trực tiếp tới `business_entities.id`; sự kiện trên Assignment/Work Report/Confirmation vẫn gắn ISSUE gốc, ID bản ghi con chỉ nằm trong metadata an toàn. Timeline là truy vấn hợp nhất, không phải bảng lưu trữ mới.
| `evidence_files` | Metadata file và checksum | owner/uploader; storage key |
| `entity_evidence_links` | Liên kết file với hồ sơ nghiệp vụ | FK `business_entity_id`; không dùng polymorphic reference |
| `integration_events` | Theo dõi request/response tích hợp ngoài | module, event key, correlation; không lưu secret |
| `idempotency_keys` | Chống xử lý trùng từ Form/API/action callback | scope + key unique; result reference |
| `scheduled_job_runs` | Theo dõi từng lần chạy scheduler/worker | job code, started/finished, outcome |
| `data_exports` | Nhật ký tạo Evidence Package/report export | actor, scope, filters, file/checksum |

## 3. Quan hệ tổng quát (cardinality)

- `server_rooms 1 — N pl1_access_requests`
- `server_rooms 1 — N pl1_access_records`
- `pl1_access_requests 1 — N pl1_approval_decisions`
- `pl1_access_records 1 — N pl1_access_record_history`
- `server_rooms 1 — N pl2_visits`
- `pl2_visits 1 — N pl2_approval_history / pl2_action_tokens / pl2_otp_challenges / pl2_visit_events`
- `pl3_inspections 1 — N pl3_inspection_results`
- `pl3_checklist_items 1 — N pl3_checklist_version_items`; `pl3_checklist_versions 1 — N pl3_checklist_version_items`; `pl3_checklist_version_items 1 — N pl3_inspection_results` theo các Inspection đã lưu.
- `pl3_inspection_results 1 — 0..1 pl4_issues` (nguồn PL3; unique source result reference)
- `pl4_priorities 1 — N pl4_issues` (Priority hiện hành được snapshot trên từng ISSUE)
- `pl4_issues 1 — N pl4_assignments / pl4_work_reports / pl4_uoa_confirmations / pl4_issue_status_history`; mỗi Work Report tham chiếu Assignment thực hiện nếu có.
- `server_rooms 1 — N pl5_work_requests / pl5_maintenance_plans`
- `pl5_work_requests 1 — N approvals / assignments / execution_events / verifications / attachments`
- `email_queue 1 — N email_queue_attempts`
- `business_entities 1 — N system_audit_logs` qua `business_entity_id` (NULL chỉ với sự kiện hệ thống không gắn hồ sơ)
- `business_entities 1 — N entity_evidence_links` qua `business_entity_id`
- Mỗi hồ sơ nghiệp vụ gốc PL1–PL5 có đúng một `business_entities` record; quan hệ 1–1 được kiểm soát bằng UNIQUE FK và transaction
- `evidence_files 1 — N entity_evidence_links`

## 4. Mô hình định danh hồ sơ nghiệp vụ dùng chung (ADR-001 đã phê duyệt)\n\nÁp dụng mô hình Supertype/Subtype cho các hồ sơ nghiệp vụ gốc cần Audit/Evidence. `business_entities` là supertype; bảng PL tương ứng giữ toàn bộ thuộc tính nghiệp vụ riêng và có thêm `business_entity_id` UNIQUE NOT NULL FK tới `business_entities.id`. PK nội bộ `id` hiện tại của bảng PL được giữ trong giai đoạn thiết kế này; không tự ý thay PK khi chưa có quyết định riêng.\n\nPhạm vi hồ sơ gốc: `pl1_access_requests`, `pl1_access_records`, `pl2_visits`, `pl3_inspections`, `pl4_issues`, `pl5_work_requests`. Nếu sau rà soát cần Audit/Evidence độc lập cho loại hồ sơ gốc khác (ví dụ maintenance plan), phải bổ sung rõ vào phạm vi trước Migration. Các bảng con như history, attempts, checklist results và execution events không tạo registry riêng; Audit của chúng gắn về hồ sơ gốc tương ứng.\n\nQuy tắc tạo: tạo `business_entities` và dòng hồ sơ PL trong cùng transaction; nếu một bước lỗi phải rollback cả hai. UNIQUE trên `business_entity_id` tại từng bảng subtype ngăn một định danh chung gắn nhiều dòng cùng loại. Service phải kiểm soát `entity_type` khớp đúng bảng subtype; FK đơn thuần không tự bảo đảm đúng loại subtype hoặc bắt buộc mọi dòng cha có con. Không xóa cứng business entity đã có lịch sử.\n\n## 5. Các điểm phải sửa so với schema.sql khởi tạo

Schema hiện tại trong `database/schema.sql` chỉ là skeleton khởi tạo, chưa đủ điều kiện dùng production. Khi thiết kế migration chính thức cần xử lý ít nhất:

1. Đổi `audit_logs` thành thiết kế `system_audit_logs` theo Section 17; bổ sung event_uuid, event_code, result SUCCESS/FAILED, workflow_step, actor_type, action_channel, correlation_id, causation_id, request_id, occurred_at/recorded_at và metadata an toàn.
2. Không dùng một `visits` table chứa duy nhất một OTP hash và một token cho mọi giai đoạn; tách Action Token, OTP Challenge và OTP Attempt để bảo toàn lịch sử và chống replay.
3. Tách `authorized_personnel` thành Access Register có lịch sử thay đổi; một request không nhất thiết tương ứng vĩnh viễn 1:1 với một Access Record vì CHANGE/REVOKE cập nhật quyền hiện có.
4. `email_queue` phải hỗ trợ trạng thái theo nguồn PL1 gồm PENDING/SENDING/RETRY/SENT/FAILED; PL2/PL5 có thể có CANCELLED theo nghiệp vụ. Chuẩn hóa trạng thái dùng chung, không để PROCESSING/SENDING lệch nhau.
5. Tách PL4 Work Report và UOA Confirmation thành nhiều bản ghi; không lưu duy nhất Action_Taken/Resolution_Notes/Approved_By trong bảng incidents.
6. Bổ sung đầy đủ PL5 Approval, Assignment, Execution, Verification, Maintenance Plan, Catalog, Attachments; bảng `work_requests` hiện tại mới chỉ là bản phác thảo.
7. Bổ sung PL3 escalation run/event và integration outbox; không chỉ lưu một trạng thái xác nhận trong inspections.
8. Bổ sung snapshot actor/approver và action channel để hỗ trợ Email/Admin/Public Form; không bắt buộc mọi actor phải là users.
9. Bổ sung idempotency cho Form Response ID, API callback và action link.
10. Tách business settings động khỏi secrets kỹ thuật; ghi setting change history và áp dụng RBAC.
11. Bổ sung Evidence metadata/checksum và bảng liên kết file; không chỉ lưu URL/path không có kiểm soát.
12. Bổ sung PL1 Access Record History và các lịch sử approval/action.
13. Không dùng soft-delete cho hồ sơ nghiệp vụ đã phát sinh; danh mục có thể inactive, dữ liệu lịch sử phải còn truy vết.

## 6. Quy tắc khóa và dữ liệu

- PK nội bộ: `BIGINT UNSIGNED` hoặc UUID/ULID; mã nghiệp vụ hiển thị riêng như ACCESS-..., VISIT-..., INSP-..., ISSUE-..., WORK-....
- Email chuẩn hóa lowercase + trim trước khi so sánh; giữ giá trị gốc đã nhận nếu cần bằng chứng.
- Unique Form Response ID theo từng PL/source; unique PL3 source key cho PL4; unique event_key cho email queue.
- FK dùng InnoDB; xóa phòng/danh mục đã được sử dụng phải bị chặn hoặc chỉ chuyển INACTIVE.
- Các trạng thái nên dùng VARCHAR + CHECK/lookup tùy phiên bản MySQL và chiến lược migration; workflow service vẫn phải xác thực state transition.
- Lưu snapshot tên/email/phòng/approver tại thời điểm phát sinh để lịch sử không đổi khi danh mục được cập nhật.
- Token lưu hash, có token purpose, entity binding, expiry, used_at, revoked_at; không log raw token.
- File lưu ngoài webroot/object storage; DB chỉ lưu storage key, MIME, size, checksum SHA-256, uploader, created_at.
- Không lưu OTP, password, API secret dạng rõ trong DB/log.

## 7. Những quyết định ERD chưa được tự ý chốt

Các mục dưới đây phải đối chiếu câu hỏi còn mở trong WORKFLOW_SPECIFICATION.md và xác nhận trước khi tạo migration:
- PL1 Valid Until tính hết ngày hay hết thời điểm; thời điểm/cadence scheduler chuyển EXPIRED.
- PL1 approval token expiry thực tế/cấu hình có cần giữ chính xác như Apps Script hay thay đổi.
- PL2 thời hạn CHECK-IN sau khi APPROVED và cơ chế xử lý không check-in.
- PL3 hiện chỉ áp dụng escalation `DAILY`; khóa Run `(room_id, work_date, schedule_type)` đã được chốt tại ADR-002. Nếu bổ sung Weekly/Monthly phải có quyết định nghiệp vụ/ADR riêng.
- Trường tần suất kiểm tra `frequency` trong `pl3_inspections` chưa được chốt để triển khai: Phụ lục 3 PDF có Daily/Weekly/Monthly, nhưng `Code PL3.txt` không khai báo cột Frequency trong `INSPECTION_HEADERS`, không tạo câu hỏi này trong `createForm_()` và không đọc giá trị trong `handlePL3Submit()`. Theo dõi tại ADR-003.
- PL3 → PL4 khi vận hành PHP dùng Outbox/PHP Worker nội bộ; không chạy song song Apps Script Web App/Google Sheets integration legacy.
- PL4 email queue/retry hiện tại và yêu cầu tương thích.
- Các trường live Form chưa xác minh trực tiếp; phải đối chiếu workbook + Apps Script trước khi migration.
- Retention, privacy, IP/User-Agent, WORM/signature và partitioning cho Audit/Evidence.
- Cấu trúc actor identity: có cần danh mục external contacts riêng hay chỉ snapshot trên từng hồ sơ.
- Phạm vi liên kết file và thời hạn lưu Evidence theo từng PL.

## 8. Trạng thái

**ERD logic v0.2 – DRAFT, đã áp dụng quyết định kiến trúc ADR-001; chưa phê duyệt triển khai Migration.**  
Đây là bản đồ thực thể và quan hệ để rà soát. Chưa thay thế `database/schema.sql`, chưa tạo migration, chưa tạo PHP nghiệp vụ. Sau khi rà soát các quan hệ và chốt các điểm chặn, mới chuyển thành ERD vật lý, Data Dictionary, rồi Migration theo từng nhóm.


## 9. PL4-R08 – SLA Policy, Calendar, Cycles, Dashboard và Reports (ADR-010 đã phê duyệt)

ADR-010 được phê duyệt ngày 22/11/2026. Bổ sung 9 thực thể:
- pl4_sla_calendars → pl4_sla_calendar_versions → pl4_sla_calendar_periods / pl4_sla_calendar_exceptions.
- pl4_sla_policies → pl4_sla_policy_versions.
- pl4_sla_policy_assignment_rules tham chiếu Policy Version.
- pl4_issues 1 — 0..1 pl4_issue_sla_instances.
- pl4_issue_sla_instances 1 — N pl4_sla_cycles.

Mỗi Policy Version tham chiếu một Calendar Version và chứa ba target độc lập: Assignment Response, Assignee Resolution, UOA Confirmation. Calendar/Policy Version đã PUBLISHED là bất biến; thay đổi tạo version mới.

Policy Assignment dùng Default Policy Version trong Business Settings và Conditional Rules theo Room + Source Type (PL3/MANUAL). Ưu tiên: Room+Source → Room → Source → Default. Rule có thời gian hiệu lực; cấu hình áp dụng ngay hoặc đặt lịch. Không cho Rule cùng mức cụ thể, cùng điều kiện có khoảng hiệu lực chồng lấn. Rule cụ thể đã khớp nhưng Policy không hợp lệ không được fallback sang Default.

Issue được gán Policy khi tạo và giữ snapshot Policy Version, Calendar Version, Rule, timezone và targets. Nếu không có Policy hợp lệ, Issue vẫn được tạo; SLA Instance là NOT_CONFIGURED, không tự đặt due date.

SLA Cycle có ba loại ASSIGNMENT_RESPONSE, ASSIGNEE_RESOLUTION, UOA_CONFIRMATION. Mỗi Issue chỉ có tối đa một Cycle RUNNING tại một thời điểm. Một Assignment có thể có nhiều Assignee Resolution Cycle do UOA NOT_CONFIRMED và nhiều vòng Work Report. UOA Cycle gắn chính xác Work Report. Reassignment giữ lịch sử và tạo chu kỳ mới.

Elapsed time và due_at tính theo Calendar Version snapshot, không dùng phép trừ timestamp thuần. SLA Worker chạy nền, idempotent, đánh dấu BREACHED một lần. STOPPED/CANCELLED không được tính MET; BREACHED không chuyển ngược MET.

Dashboard/Reports gồm R08-01 đến R08-08; ba nhóm Response, Assignee Resolution và UOA Confirmation được báo cáo riêng. Priority không tự quyết định SLA. Phân quyền UOA/MSTAR/Super Admin giữ nguyên D18/D19; Audit dùng system_audit_logs liên kết business_entity_id Issue theo ADR-009. Chi tiết đầy đủ tại ADR-010-PL4-SLA-POLICY-DASHBOARD-REPORTS.md.


## 2.5.1. Làm rõ Manual Assignment và SLA (ADR-006/007/008/010)

- Manual Form phải chọn Assignee; không tạo ISSUE MANUAL ở trạng thái chưa phân công.
- Transaction tạo Manual Issue bao gồm Business Entity, Issue, Manual Submission, Assignment `ASSIGNED`, SLA Instance, Cycle `ASSIGNMENT_RESPONSE` nếu Instance ACTIVE, Audit và Email Queue. Nếu bất kỳ thao tác ghi DB bắt buộc nào lỗi, rollback toàn bộ.
- `PL4_ISSUE_CREATED_MANUAL` là email duy nhất cho sự kiện tạo Manual Issue và phân công đầu tiên; không enqueue thêm `PL4_ASSIGNMENT_ASSIGNED` cho cùng Assignment.
- ISSUE PL3 vẫn khởi tạo OPEN/chưa có Assignee; Assignment Response chỉ bắt đầu khi Assignment được tạo.
- Một Cycle RUNNING trên mỗi Issue được bảo vệ bằng generated column `active_issue_id` STORED UNIQUE và transaction/row lock. UNIQUE `(work_report_id, cycle_type)` cho các Cycle gắn Work Report; không UNIQUE `(assignment_id, cycle_type)`.
- SLA Instance ACTIVE chuyển COMPLETED khi Issue CLOSED; NOT_CONFIGURED giữ nguyên NOT_CONFIGURED. Khi kết thúc Cycle, kiểm tra elapsed ngay để phân loại MET/BREACHED/STOPPED chính xác, không phụ thuộc thời điểm Worker chạy.


### 2.5.2. Assignment Claim Token

Bổ sung thực thể `pl4_assignment_claim_tokens`, quan hệ 1-N từ `pl4_assignments`. Mỗi token gắn một Assignment cụ thể, lưu SHA-256 hash UNIQUE và thời hạn/used/revoked timestamps. Email Queue không chứa raw token; Worker phát hành token và dựng Claim URL trong bộ nhớ khi gửi email Assignment. Chi tiết schema tại `DATA_DICTIONARY.md` và quy tắc tại ADR-006/008.


## 10. PL4 – Toàn vẹn tham chiếu vật lý (ADR-011)

Đã phê duyệt nguyên tắc Composite FK và ON DELETE RESTRICT:
- Work Report phải tham chiếu Assignment thuộc cùng Issue: `(assignment_id, issue_id)` → Assignment `(id, issue_id)`.
- UOA Confirmation phải tham chiếu Work Report thuộc cùng Issue: `(work_report_id, issue_id)` → Work Report `(id, issue_id)`.
- SLA Cycle phải tham chiếu SLA Instance thuộc cùng Issue: `(issue_sla_instance_id, issue_id)` → SLA Instance `(id, issue_id)`.
- Các liên kết Assignment/Work Report tùy chọn của SLA Cycle cũng dùng Composite FK để ngăn tham chiếu chéo Issue.
- Bảng cha bổ sung UNIQUE `(id, issue_id)` làm đích cho FK ghép.
- FK hồ sơ/lịch sử/bảo mật/SLA dùng ON DELETE RESTRICT; không CASCADE DELETE hoặc hard-delete dữ liệu đã phát sinh lịch sử. Kết thúc bằng trạng thái nghiệp vụ.

NULL/UNIQUE tiếp tục theo DATA_DICTIONARY §13.4. Các ràng buộc không thay đổi state machine, quy trình hoặc SLA; chưa tạo DDL/Migration.
