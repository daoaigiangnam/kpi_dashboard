# ADR-010 – PL4 SLA Policy, Dashboard and Reports

- **Trạng thái:** APPROVED
- **Phiên bản:** 1.0
- **Ngày phê duyệt:** 22/11/2026
- **Phân hệ:** PL4 – Issue Management
- **Nhánh:** feature/ITSMWEB
- **Phạm vi:** SLA Calendar, SLA Policy, Policy Assignment, SLA Cycles, Dashboard và Reports
- **Căn cứ:** ADR-004 đến ADR-009 và phê duyệt D1–D22 của chủ nghiệp vụ ngày 22/11/2026.

## 1. Bối cảnh

PL4 hiện có Issue, Priority, Assignment, Work Report, UOA Confirmation và Status History. Các ADR trước đã chốt Priority độc lập với SLA và state machine PL4. Chưa có mô hình chính thức để quản lý lịch SLA, Policy, chu kỳ đo thời gian, Dashboard và báo cáo SLA.

ADR này bổ sung lớp SLA độc lập, không thay đổi state machine PL4, quy tắc Priority, Assignment/Claim, Work Report, UOA Confirmation, PL3 → PL4 integration, Email Queue hoặc Audit architecture đã được phê duyệt.

## 2. Quyết định

### D1 – Tách SLA Assignee và UOA
Đo thời gian xử lý Assignee và thời gian UOA xác nhận bằng hai chu kỳ SLA độc lập. Thời gian UOA xem xét không được tính vào thời gian xử lý của Assignee.

### D2 – Lịch SLA cấu hình theo từng Policy
Mỗi SLA Policy Version tham chiếu một Calendar Version. Hệ thống hỗ trợ lịch 24/7, giờ hành chính hoặc lịch riêng; lịch gồm múi giờ, khung giờ theo ngày trong tuần và ngoại lệ/ngày nghỉ/ngày làm bù. Mặc định múi giờ hiển thị là Asia/Ho_Chi_Minh; thời gian lưu DB là UTC.

### D3 – Policy Assignment kết hợp Default và Conditional Rules
Có một Default SLA Policy Version đang hiệu lực tại một thời điểm và các Conditional Policy Rules theo Room và Source Type. Rule phù hợp được ưu tiên hơn Default Policy.

### D4 – Thời điểm hiệu lực Rule và Default Policy
Cấu hình được áp dụng ngay hoặc được đặt lịch hiệu lực trong tương lai. Mỗi cấu hình có effective_from và effective_to; khoảng hiệu lực được hiểu là [effective_from, effective_to). Thời gian lưu DB là UTC. Thay đổi chỉ áp dụng cho Issue được tạo trong khoảng hiệu lực tương ứng; Issue cũ giữ nguyên Policy/Calendar snapshot.

### D5 – Ba chỉ tiêu SLA
Quản lý ba chỉ tiêu độc lập:
- Assignment Response: từ lúc giao Assignment đến khi Assignee claim.
- Assignee Resolution: từ lúc Assignee claim đến khi gửi Work Report.
- UOA Confirmation: từ lúc gửi Work Report đến khi UOA CONFIRMED hoặc NOT_CONFIRMED.

Assignment Response là KPI bổ sung; Assignee Resolution và UOA Confirmation là hai SLA xử lý chính. Giá trị thời gian cụ thể do Super Admin cấu hình trong Policy Version; ADR không đặt các con số mặc định.

### D6 – Điều kiện Policy Assignment
Conditional Rules chỉ sử dụng Room (room_id) và Source Type (PL3 hoặc MANUAL). Priority không phải điều kiện tự động gán SLA. Không suy ra SLA từ LOW/MEDIUM/HIGH/CRITICAL theo ADR-004.

### D7 – Thứ tự ưu tiên Rule
Thứ tự khớp cố định:
1. Khớp cả Room và Source Type.
2. Chỉ khớp Room.
3. Chỉ khớp Source Type.
4. Không có Rule phù hợp thì dùng Default Policy.

Thứ tự này dựa trên độ cụ thể của điều kiện, không dựa trên priority_order do người dùng tự nhập.

### D8 – Xung đột Rule
Không cho kích hoạt Rule nếu cùng mức cụ thể, cùng điều kiện và có khoảng hiệu lực chồng lấn. Các Rule có điều kiện khác độ cụ thể có thể cùng hiệu lực; thứ tự D7 quyết định Rule được chọn. Nếu dữ liệu cấu hình vẫn tạo ra kết quả mơ hồ, dừng gán Policy, ghi Audit và không tự chọn ngẫu nhiên.

### D9 – Policy không hợp lệ hoặc không có Default
Issue vẫn được tạo theo nghiệp vụ. SLA Instance được tạo với trạng thái NOT_CONFIGURED; policy/calendar reference để NULL khi không có Policy hợp lệ. Ghi Audit Log về lỗi cấu hình. Không fallback ngầm, không tự đặt due date và không chọn một Policy khác ngoài quy tắc D7.

Nếu Rule cụ thể đã khớp nhưng Policy Version của Rule không hợp lệ, không bỏ qua Rule để dùng Default Policy.

### D10 – Thời điểm gán Policy và snapshot
Policy được xác định tại thời điểm tạo Issue. Mỗi Issue có tối đa một SLA Instance. Instance lưu Policy Version, Calendar Version, Rule nguồn (nếu có), mã/tên/version snapshot, timezone và ba target snapshot. Policy Assignment không tự chạy lại khi Rule, Default Policy hoặc danh mục thay đổi.

### D11 – Calendar Version bất biến
Calendar Version phải được PUBLISHED trước khi được Policy Version sử dụng. Version đã PUBLISHED/được tham chiếu không được sửa trực tiếp. Mọi thay đổi tạo Version mới. Issue SLA Instance giữ đúng Calendar Version snapshot đã gán.

### D12 – Policy Version bất biến
Policy Version chỉ được sử dụng khi PUBLISHED và có đủ ba target hợp lệ lớn hơn 0 cùng Calendar Version hợp lệ. Version đã PUBLISHED không được sửa trực tiếp; thay đổi tạo Version mới. Issue hiện hữu không tự chuyển sang Version mới.

### D13 – Reassignment
Khi Assignment bị DECLINED, EXPIRED hoặc RELEASED, chu kỳ liên quan được kết thúc và giữ lịch sử. Không coi việc dừng này là MET. Khi tạo Assignment mới, bắt đầu chu kỳ Assignment Response mới; sau khi Assignee claim, bắt đầu chu kỳ Assignee Resolution mới. Reassignment không xóa hoặc ghi đè chu kỳ cũ.

### D14 – UOA NOT_CONFIRMED
Khi UOA CONFIRMED hoặc NOT_CONFIRMED, chu kỳ UOA Confirmation tương ứng kết thúc. CONFIRMED đóng Issue theo state machine hiện hành. NOT_CONFIRMED bắt buộc có lý do, chuyển Issue về IN_PROGRESS và bắt đầu chu kỳ Assignee Resolution mới. Nếu Assignment được thay thế, áp dụng chu kỳ mới theo D13.

### D15 – SLA Worker
SLA Worker chạy nền độc lập với Dashboard. Worker tính elapsed time theo Calendar Version, xác định due_at và đánh dấu BREACHED. Xử lý phải idempotent và an toàn khi nhiều Worker chạy đồng thời; không tạo trùng sự kiện breach. Dashboard chỉ đọc, không làm thay đổi trạng thái SLA.

### D16 – Audit
Các thay đổi Policy, Policy Version, Calendar Version, Assignment Rule, Default Policy, Policy Assignment và SLA Cycle phải được ghi vào system_audit_logs. Sự kiện liên quan Issue phải liên kết business_entity_id của Issue theo ADR-001 và ADR-009. Không ghi raw token, secret hoặc dữ liệu nhạy cảm không cần thiết.

### D17 – Dashboard và Reports
Cung cấp tám báo cáo:
- R08-01 Issue Register.
- R08-02 Issue Aging.
- R08-03 Priority Analysis.
- R08-04 Assignee Performance.
- R08-05 Assignee SLA Compliance.
- R08-06 UOA SLA Compliance.
- R08-07 UOA Confirmation History.
- R08-08 PL3 → PL4 Analysis.

Báo cáo hỗ trợ bộ lọc phù hợp theo kỳ, Room, Priority, Issue Status, Assignee, UOA Approver, Source Type, SLA Policy/Version và Calendar Version. Ba nhóm chỉ số Response, Assignee Resolution và UOA Confirmation phải hiển thị riêng, không gộp thành một tỷ lệ SLA chung.

### D18 – Phân quyền
UOA xem Dashboard/báo cáo nghiệp vụ trong phạm vi được cấp. MSTAR chỉ xem số liệu tổng hợp read-only theo quyền đã chốt. Super Admin quản lý Calendar, Policy, Rules và cấu hình SLA nhưng không mặc nhiên có quyền xem dữ liệu nghiệp vụ. Assignee chỉ xem SLA của Issue được giao. SLA Worker dùng quyền hệ thống giới hạn cho tác vụ nền.

### D19 – Tích hợp PL3 và Manual
Gán Policy và tạo SLA Instance nằm trong transaction tạo Issue:
- Manual Issue tuân thủ transaction ADR-007.
- PL3-generated Issue tuân thủ Outbox/Worker và tính nguyên tử theo ADR-005.

Nếu không có Policy hợp lệ, Issue vẫn được tạo và SLA Instance ở NOT_CONFIGURED theo D9. Không tạo SMTP trực tiếp; thông báo nếu được phê duyệt sau này phải qua Email Queue theo ADR-008.

### D20 – Chu kỳ dừng không đạt SLA
STOPPED, DECLINED, EXPIRED, RELEASED hoặc CANCELLED không được tính là MET. Các chu kỳ này được báo cáo riêng. Nếu đã quá hạn trước khi bị dừng, giữ kết quả BREACHED; nếu dừng trước hạn, giữ STOPPED và lý do tương ứng.

### D21 – BREACHED không chuyển ngược MET
Một chu kỳ đã được ghi nhận BREACHED không được đổi ngược thành MET khi hoàn tất muộn. Lưu breached_at, stopped_at, elapsed_seconds và kết quả cuối để báo cáo lịch sử chính xác.

### D22 – Thay đổi Policy chỉ áp dụng Issue mới
Thay đổi Policy Version, Calendar Version, Default Policy hoặc Assignment Rule không làm thay đổi SLA Instance của Issue đã tạo. Tái áp dụng Policy cho Issue cũ không nằm trong phạm vi ADR này và cần quyết định/quy trình riêng, có Audit đầy đủ.

## 3. Mô hình dữ liệu được chấp thuận

Bổ sung 9 bảng:
1. pl4_sla_calendars
2. pl4_sla_calendar_versions
3. pl4_sla_calendar_periods
4. pl4_sla_calendar_exceptions
5. pl4_sla_policies
6. pl4_sla_policy_versions
7. pl4_sla_policy_assignment_rules
8. pl4_issue_sla_instances
9. pl4_sla_cycles

Default Policy Version được quản lý bằng Business Settings có lịch hiệu lực và lịch sử thay đổi, sử dụng setting history hiện có; không tạo bảng thứ mười chỉ để lưu Default Policy. Conditional Rules tham chiếu trực tiếp Policy Version.

### 3.1. SLA Calendar
Calendar là danh mục. Calendar Version chứa timezone, trạng thái DRAFT/PUBLISHED/RETIRED và version number. Calendar Period lưu nhiều khoảng giờ cho từng thứ trong tuần. Calendar Exception lưu ngày nghỉ, ngày làm bù hoặc khung giờ đặc biệt. Một ngày có thể có nhiều khoảng làm việc; khoảng thời gian phải hợp lệ, không chồng lấn trong cùng ngày.

### 3.2. SLA Policy
SLA Policy là danh mục. Policy Version tham chiếu một Calendar Version và chứa ba target độc lập. DRAFT được chỉnh sửa; PUBLISHED/RETIRED bất biến. Không cho Publish nếu thiếu target, target không dương hoặc Calendar Version chưa PUBLISHED.

### 3.3. Assignment Rule
Rule chứa rule_code, rule_name, room_id nullable, source_type nullable, policy_version_id, effective_from, effective_to nullable, status, actor và timestamps. room_id NULL nghĩa là mọi Room; source_type NULL nghĩa là mọi nguồn. Rule có cả hai điều kiện NULL không được dùng thay Default Policy; Default Policy nằm trong Business Settings.

Rule ACTIVE được xét theo thời điểm tạo Issue. Cùng mức cụ thể và cùng điều kiện không được có khoảng hiệu lực giao nhau. Room+Source có độ cụ thể cao hơn Room-only hoặc Source-only. Không có Rule phù hợp thì đọc Default Policy có hiệu lực tại cùng thời điểm.

### 3.4. Issue SLA Instance
Một Issue có tối đa một Instance (UNIQUE issue_id). Instance lưu issue_id, policy_version_id nullable, calendar_version_id nullable, assignment_rule_id nullable, assignment_source (RULE/DEFAULT/NOT_CONFIGURED), policy_code/name/version snapshots, ba target snapshots, timezone_snapshot và status ACTIVE/NOT_CONFIGURED/COMPLETED. NOT_CONFIGURED không có target/due date giả định.

### 3.5. SLA Cycle
Cycle lưu issue_sla_instance_id, issue_id, cycle_type, cycle_no, assignment_id nullable, work_report_id nullable, started_at, stopped_at, target_minutes snapshot, elapsed_seconds, due_at, breached_at, status, start_reason, stop_reason và timestamps.

Cycle type:
- ASSIGNMENT_RESPONSE: liên kết Assignment; bắt đầu khi giao Assignment; kết thúc khi claim hoặc Assignment kết thúc.
- ASSIGNEE_RESOLUTION: liên kết Assignment; bắt đầu khi claim; kết thúc khi Work Report được gửi; khi kết thúc gắn work_report_id.
- UOA_CONFIRMATION: liên kết chính xác Work Report; bắt đầu khi Work Report được gửi; kết thúc khi UOA CONFIRMED/NOT_CONFIRMED.

cycle_no tăng theo từng Issue + cycle_type. Không đặt UNIQUE assignment_id + cycle_type cho ASSIGNEE_RESOLUTION vì một Assignment có thể có nhiều Work Report/round khi UOA NOT_CONFIRMED. Mỗi UOA Confirmation Cycle chỉ được tạo một lần cho một Work Report. Một Issue không có hai SLA Cycle RUNNING đồng thời. Quy tắc này được kiểm soát bằng transaction/row lock và constraint phù hợp; không dựa riêng vào giao diện.

### 3.6. SLA time calculation
elapsed_seconds là thời gian làm việc thực tế tính theo Calendar Version snapshot, không phải phép trừ timestamp thuần. due_at là thời điểm đạt đủ target theo lịch đó. Lịch 24/7 tính liên tục; lịch hành chính bỏ qua ngoài giờ, ngày nghỉ và áp dụng ngoại lệ. Calendar Version và timezone snapshot bảo đảm kết quả lịch sử có thể tái dựng.

SLA Worker phải xử lý idempotent, khóa chu kỳ khi cập nhật và ghi breach một lần. Nếu chu kỳ đã BREACHED, hoàn tất muộn không chuyển thành MET.

## 4. Ma trận chuyển chu kỳ

| Sự kiện nghiệp vụ | Chu kỳ SLA |
|---|---|
| Assignment được tạo | Bắt đầu ASSIGNMENT_RESPONSE |
| Assignee claim | Kết thúc ASSIGNMENT_RESPONSE; bắt đầu ASSIGNEE_RESOLUTION |
| Assignment DECLINED/EXPIRED/RELEASED | Kết thúc chu kỳ liên quan; không tự tính MET |
| Assignment mới | Bắt đầu ASSIGNMENT_RESPONSE mới |
| Work Report được gửi | Kết thúc ASSIGNEE_RESOLUTION; bắt đầu UOA_CONFIRMATION |
| UOA CONFIRMED | Kết thúc UOA_CONFIRMATION; Issue CLOSED |
| UOA NOT_CONFIRMED | Kết thúc UOA_CONFIRMATION; Issue IN_PROGRESS; bắt đầu ASSIGNEE_RESOLUTION mới khi Assignment hiện hành còn hợp lệ |
| Issue CLOSED | Không còn chu kỳ SLA đang RUNNING |

## 5. Dashboard và định nghĩa báo cáo

R08-01 Issue Register; R08-02 Issue Aging; R08-03 Priority Analysis; R08-04 Assignee Performance; R08-05 Assignee SLA Compliance; R08-06 UOA SLA Compliance; R08-07 UOA Confirmation History; R08-08 PL3 → PL4 Analysis.

Tỷ lệ compliance tính trên các chu kỳ hoàn tất có dữ liệu SLA hợp lệ. STOPPED/CANCELLED/NOT_CONFIGURED báo cáo riêng, không tính thành đạt. BREACHED được giữ là vi phạm dù hoàn tất sau hạn. Dashboard hỗ trợ lọc theo thời gian, Room, Priority, Issue Status, Assignee, UOA Approver, Source Type, Policy/Version và Calendar Version.

## 6. Audit và bảo mật

Các event code tối thiểu:
- PL4_SLA_POLICY_CREATED
- PL4_SLA_POLICY_VERSION_PUBLISHED
- PL4_SLA_CALENDAR_VERSION_PUBLISHED
- PL4_SLA_ASSIGNMENT_RULE_CREATED
- PL4_SLA_ASSIGNMENT_RULE_ACTIVATED
- PL4_SLA_ASSIGNMENT_RULE_CHANGED
- PL4_SLA_POLICY_ASSIGNED
- PL4_SLA_CYCLE_STARTED
- PL4_SLA_CYCLE_MET
- PL4_SLA_CYCLE_BREACHED
- PL4_SLA_CYCLE_STOPPED
- PL4_SLA_CONFIGURATION_ERROR

Audit về Issue liên kết business_entity_id của Issue. Không ghi raw token/secret. Không tạo PL4 audit/notification history riêng; dùng system_audit_logs và các bảng SLA Cycle.

## 7. Transaction và concurrency

- Tạo Issue, Business Entity, nguồn submission/outbox, Audit và SLA Instance trong transaction tương ứng ADR-005/007.
- Policy resolution đọc cấu hình có hiệu lực tại thời điểm tạo Issue và phải dùng một consistent snapshot trong transaction.
- Policy Version/Calendar Version được kiểm tra PUBLISHED trước khi gán.
- SLA Cycle start/stop và cập nhật Issue workflow phải nguyên tử.
- Worker claim Cycle an toàn khi chạy đồng thời; duplicate execution không tạo thêm breach event.
- Rule/Default configuration validation phải kiểm tra overlap và hiệu lực trước khi kích hoạt.
- Các lỗi cấu hình được Audit; không âm thầm fallback.

## 8. Quan hệ với ADR trước

- ADR-004: Priority vẫn độc lập với SLA.
- ADR-005: PL3 → PL4 vẫn dùng Outbox, source key và snapshot đã chốt.
- ADR-006: Assignment/Claim lifecycle giữ nguyên; SLA đo trên các sự kiện của lifecycle.
- ADR-007: Manual Issue atomicity/idempotency giữ nguyên; SLA Instance nằm trong transaction tạo Issue.
- ADR-008: Không thay đổi Email Queue/notification matrix; không gửi SMTP trực tiếp từ SLA Worker.
- ADR-009: Audit dùng system_audit_logs và business_entities; không tạo audit/notification history trùng.

## 9. Phạm vi chưa triển khai

ADR này phê duyệt thiết kế. Chưa viết Migration, schema.sql, PHP Controller/Service/Worker, UI, SQL report hoặc email cảnh báo SLA. Giá trị target cụ thể do Super Admin cấu hình sau khi hệ thống có giao diện quản trị. Cảnh báo email SLA là tính năng tương lai, cần quyết định riêng nếu triển khai.

## 10. Tiêu chí nghiệm thu thiết kế

1. Một Issue tối đa một SLA Instance.
2. Một Issue tối đa một SLA Cycle RUNNING tại một thời điểm.
3. Mỗi Work Report có tối đa một UOA Confirmation Cycle.
4. Một Assignment có thể có nhiều Assignee Resolution Cycles theo các vòng báo cáo.
5. Reassignment giữ nguyên lịch sử và khởi tạo chu kỳ mới.
6. Policy/Calendar snapshot không thay đổi khi cấu hình hiện tại được cập nhật.
7. Rule selection deterministic theo D7.
8. Rule conflict/Policy invalid không fallback ngầm.
9. Worker chạy lại hoặc chạy đồng thời không tạo duplicate breach.
10. NOT_CONFIGURED và chu kỳ dừng không bị tính là MET.
11. BREACHED không chuyển ngược MET.
12. Dashboard không làm thay đổi trạng thái nghiệp vụ/SLA.
13. Không có thay đổi state machine PL4 hoặc Priority behavior.

**Kết luận:** ADR-010 được chủ nghiệp vụ phê duyệt D1–D22 ngày 22/11/2026. Tài liệu này là chuẩn thiết kế cho PL4-R08; mọi thay đổi tiếp theo phải được ghi nhận bằng ADR hoặc quyết định bổ sung.


## 11. Làm rõ kỹ thuật sau rà soát thiết kế – 22/11/2026

Các nội dung dưới đây làm rõ cách thực thi ADR-010, không thay đổi D1–D22 đã được phê duyệt.

### 11.1. SLA Instance status
- Khi tạo Issue và resolve được Policy Version hợp lệ: tạo Instance `ACTIVE`.
- Khi không có Policy hợp lệ hoặc cấu hình bị lỗi theo D9: tạo Instance `NOT_CONFIGURED`; Policy/Calendar/targets có thể NULL. Instance này giữ `NOT_CONFIGURED` kể cả khi Issue về sau đóng, vì Issue không có SLA hợp lệ để hoàn tất.
- Khi Instance `ACTIVE` và Issue chuyển `CLOSED` do UOA `CONFIRMED`: chuyển Instance sang `COMPLETED` trong cùng transaction. Không xóa hoặc sửa kết quả các Cycle đã kết thúc.
- `ACTIVE` có thể không có Cycle RUNNING, ví dụ Issue PL3 vừa tạo nhưng chưa được phân công. Đây không phải lỗi; SLA Response chỉ bắt đầu khi Assignment thực sự được tạo.

### 11.2. Ràng buộc một Cycle RUNNING trên mỗi Issue
Bổ sung ràng buộc DB cho `pl4_sla_cycles`:
- `active_issue_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'RUNNING' THEN issue_id ELSE NULL END) STORED`.
- UNIQUE KEY trên `active_issue_id`; nhiều NULL được phép.
- Mọi thao tác tạo/chuyển trạng thái Cycle vẫn phải khóa dòng Issue/SLA Instance và kiểm tra trong transaction. Constraint DB là lớp bảo vệ cuối, không thay thế concurrency control ở service.

### 11.3. Liên kết Work Report và Cycle
- UOA Confirmation Cycle bắt buộc có `work_report_id`; UNIQUE `(work_report_id, cycle_type)` bảo đảm một UOA cycle cho mỗi Work Report.
- Assignee Resolution Cycle khi đã kết thúc phải gắn Work Report đã được tạo bởi đúng Assignment; UNIQUE `(work_report_id, cycle_type)` ngăn gắn nhiều Assignee Resolution cycle vào cùng Work Report.
- Không UNIQUE `(assignment_id, cycle_type)` vì cùng Assignment có thể có nhiều vòng Assignee Resolution sau UOA NOT_CONFIRMED.
- `work_report_id` có thể NULL khi Assignee Resolution đang RUNNING; khi submit Work Report, cập nhật liên kết trong cùng transaction dừng Cycle.

### 11.4. Kết quả khi kết thúc Cycle và Worker
Khi có sự kiện kết thúc Cycle, service phải tính elapsed theo Calendar Version snapshot ngay tại transaction:
- Nếu đã vượt target, kết quả là `BREACHED` dù SLA Worker chưa kịp chạy.
- Nếu kết thúc trước hoặc đúng hạn, sự kiện kết thúc hợp lệ là `MET`.
- Nếu Assignment bị DECLINED/EXPIRED/RELEASED hoặc bị hủy trước hạn, Cycle là `STOPPED`/`CANCELLED`, không phải MET. Nếu đã quá hạn tại thời điểm dừng thì giữ `BREACHED`.
- Cycle đã `BREACHED` không bao giờ chuyển về `MET` hoặc `STOPPED`.
- SLA Worker độc lập Dashboard; phải phát hiện và đánh dấu các Cycle quá hạn chưa kết thúc. Cập nhật phải idempotent, khóa an toàn và chỉ ghi Audit breach một lần.

### 11.5. Calendar Exceptions – quy tắc đề xuất, chờ chủ nghiệp vụ xác nhận
- Cho phép nhiều dòng `SPECIAL_WORKING` cho cùng `calendar_version_id + exception_date` để biểu diễn nhiều khoảng giờ trong một ngày.
- Mỗi khoảng phải có `start_time < end_time`; các khoảng SPECIAL_WORKING cùng ngày không được chồng lấn.
- Đề xuất: `HOLIDAY` hoặc `CLOSED` là ngoại lệ đóng cả ngày, loại trừ mọi `SPECIAL_WORKING` trong ngày đó; một ngày chỉ có tối đa một bản ghi đóng cả ngày.
- Đề xuất: khi tồn tại bất kỳ Exception cho một ngày, tập Exception thay thế lịch tuần của ngày đó; không cộng dồn với Calendar Period.
- Hai quy tắc precedence ở trên là quyết định nghiệp vụ về cách xử lý ngày ngoại lệ; cần chủ nghiệp vụ xác nhận trước Migration, không coi là nội dung D1–D22 đã được phê duyệt.

### 11.6. Assignment khởi tạo cho Manual Issue
Theo ADR-007 đã được làm rõ: Manual Form bắt buộc chọn Assignee; tạo Issue, Manual Submission, Assignment `ASSIGNED`, Audit, Email Queue và SLA Instance trong cùng transaction. Nếu Policy hợp lệ, tạo Cycle `ASSIGNMENT_RESPONSE` ngay từ `assigned_at`. Nếu NOT_CONFIGURED, vẫn tạo Issue và Assignment nhưng không tạo Cycle; ghi Audit cấu hình.
