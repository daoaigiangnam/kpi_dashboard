# SRM – Schema Review Batch 2 (7 bảng nghiệp vụ)

> Đối chiếu Data Dictionary, Data Integrity & State Machines và Column Schema Batch 2. Đây là kết quả review thiết kế, chưa phải phê duyệt migration.

## 1. Kết luận tổng quan

Batch 2 gồm Access Logs, Inspections/Results, Incidents/Actions, Maintenance Requests và Equipment Movements. Các quan hệ nghiệp vụ cơ bản phù hợp. Tuy nhiên có một số điểm không đồng nhất giữa Data Dictionary và State Machine; đặc biệt là thời điểm Access Log, vòng đời Inspection, trạng thái Incident và trường thời gian/approval của Movement.

## 2. Review theo từng bảng

### 2.1 server_room_access_logs

**Đã nhất quán**
- Thuộc một customer và một server room; access_person_id nullable để ghi nhận visitor không có account.
- visitor_name là snapshot tên thực tế; status dự kiến registered/checked_in/completed/cancelled.
- State Machine: registered → checked_in → completed; registered → cancelled; checked_in → completed bằng exception_close có lý do.

**Cần chốt**
1. entry_at trong Data Dictionary là giờ vào; Batch 2 ghi thời gian dự kiến/thực tế theo status. State Machine dùng entry_at như thời điểm check-in thực tế. Đề xuất entry_at = giờ vào thực tế; nếu cần đăng ký trước, thêm scheduled_entry_at.
2. registered là đăng ký trước giờ vào hay chỉ là bản ghi vừa tạo? Nếu đăng ký trước, cần thời gian dự kiến riêng.
3. access_person_id khi khác NULL phải thuộc đúng customer và đúng server_room_id. FK ghép tới access_persons cần candidate key (id, customer_id, server_room_id).
4. Nếu access_person_id NULL, phải có quy tắc xác minh người vào và ai phê duyệt ngoại lệ; exception_reason hiện mới là đề xuất thêm.
5. exit_at >= entry_at; checked_in thì exit_at NULL; completed thì exit_at bắt buộc. Cần xử lý log đang mở qua ngày và quên checkout.
6. approver_id/supervisor_id cần authorization theo tenant và vai trò; FK users.id chỉ chứng minh user tồn tại.
7. Cần quyết định visitor_name/organization có bắt buộc cho mọi access_person hay tự snapshot từ access_person.

### 2.2 server_room_inspections

**Đã nhất quán**
- Inspection thuộc room và template cùng tenant.
- template_version, inspector_name là snapshot; inspection_code unique theo customer.
- Sau submit không sửa trực tiếp kết quả; correction/revision phải giữ audit.

**Mâu thuẫn cần giải quyết**
1. Data Dictionary status: draft/submitted/reviewed/closed/cancelled.
2. State Machine cho phép review_return → draft hoặc correction_required.
3. Batch 2 chọn thêm correction_required. Đề xuất giữ correction_required để phân biệt phiếu đã bị trả về với bản nháp mới tạo.
4. reviewed và closed là hai bước khác nhau; cần định nghĩa điều kiện chuyển reviewed và đóng.
5. Nếu review_return trả về draft, cần revision/audit để bảo toàn dữ liệu đã submit; không ghi đè lịch sử.
6. template_id và template_version phải cùng customer và version thực tế. Nếu mỗi version là một row, cần kiểm tra version bằng service.
7. completed_at là hoàn tất kiểm tra, không đồng nghĩa submitted_at; cần thống nhất thời điểm ghi.
8. conclusion pass/corrective_action_required/emergency cần định nghĩa có bắt buộc trước review/close không.

### 2.3 server_room_inspection_results

**Đã nhất quán**
- Mỗi result thuộc inspection; giữ snapshot item code/name/description/response_type.
- checklist_item_id nullable để lịch sử không phụ thuộc item nguồn còn active.
- Unique (inspection_id, item_code_snapshot) giả định mỗi item xuất hiện một lần trên phiếu.

**Cần chốt**
1. Cần candidate key (id, customer_id) cho inspections và checklist_items để tạo composite FK.
2. checklist_item_id khi khác NULL phải thuộc đúng customer và đúng template/version của inspection, không chỉ cùng customer.
3. result hiện chứa pass/fail/na hoặc yes/no; boolean_value cũng chứa đúng/sai. Đề xuất bỏ boolean_value và dùng result cho yes_no; giữ numeric_value/text_value/date_value cho kiểu tương ứng.
4. response_type_snapshot chưa có multi_select; cần quyết định trước migration. Nếu có multi_select, cần cấu trúc lưu mảng có schema/validation rõ.
5. Cần ràng buộc đúng một kiểu value theo response_type; validation ứng dụng và CHECK constraint nếu biểu thức MySQL 8 phù hợp.
6. UNIQUE (inspection_id, item_code_snapshot) không cho phép cùng item xuất hiện hai lần. Nếu cần repeatable item, phải có định danh item instance.
7. Kết quả sau submitted phải bất biến; cần xác định audit log có đủ hay phải lưu revision snapshot.

### 2.4 server_room_incidents

**Đã nhất quán**
- Sự cố thuộc một room; nhiều asset liên quan được lưu qua pivot.
- incident_code unique theo customer; có severity, detected_at, reported_at và snapshot reporter.
- State Machine: open → in_progress → resolved → closed; resolved có thể reopen về in_progress.

**Mâu thuẫn cần giải quyết**
1. Data Dictionary liệt kê open/investigating/in_progress/resolved/verified/closed.
2. State Machine không mô tả transition riêng cho investigating hoặc verified.
3. Đề xuất chuẩn hóa tối giản: open/in_progress/resolved/closed/cancelled; chỉ thêm investigating nếu có bước triage riêng với owner/SLA rõ ràng. verified_by/verified_at lưu người xác minh trong bước resolved → closed, không nhất thiết cần status verified.
4. cancelled có trong State Machine nhưng Data Dictionary cũ không liệt kê.
5. Cần lý do bắt buộc khi cancel/reopen; schema hiện chưa có cancel_reason/reopen_reason. Có thể lưu event vào audit nếu đủ chi tiết.
6. resolved_at phải được set khi resolve; nếu reopen thì giữ lịch sử lần resolve trước bằng audit/event, không chỉ ghi đè.
7. Incident asset pivot phải enforce cùng customer. Có cần asset cùng server_room_id với incident hay cho phép asset ở room khác trong cùng tenant? Chưa chốt.

### 2.5 server_room_incident_actions

**Đã nhất quán**
- Mỗi action thuộc incident cùng tenant.
- action_type corrective/preventive; assigned_to có thể NULL và assigned_name là snapshot người ngoài.
- State: pending → in_progress → completed → verified; có thể cancelled trước khi hoàn tất.

**Cần chốt**
1. verified là trạng thái action; cần định nghĩa người xác minh có phải khác người thực hiện không.
2. Khi completed, completed_at và completion_note có bắt buộc không; khi verified, verified_by/verified_at/verification_note có bắt buộc không?
3. Khi assigned_to NULL, assigned_name có bắt buộc không? Có cho phép nhóm/nhà thầu thực hiện?
4. Không hard delete action khi incident đóng; cancellation cần reason và audit.
5. due_at quá hạn là điều kiện report, không tự động tạo status mới nếu chưa có nghiệp vụ.

### 2.6 server_room_maintenance_requests

**Đã nhất quán**
- Thuộc room; một request có thể liên quan nhiều asset qua pivot.
- Có lịch dự kiến và thời gian thực tế; vendor/personnel snapshot; impact, rollback và kết quả.
- State Machine draft → pending_approval → approved → in_progress → completed → verified; reject/cancel theo điều kiện.

**Cần chốt**
1. Emergency maintenance được State Machine mô tả nhưng Data Dictionary ban đầu chưa có cột riêng. Batch 2 đề xuất emergency_*; cần quyết định triển khai ngay hay loại khỏi phiên bản đầu.
2. Nếu bật emergency, cần quyền riêng, emergency_reason, actor/time và hậu kiểm bắt buộc; không chỉ bỏ qua approval.
3. personnel JSON là snapshot, không phải quan hệ nhân sự. Cần chốt cấu trúc và hạn chế dữ liệu cá nhân.
4. work_start_at/work_end_at là lịch dự kiến; actual_start_at/actual_end_at là thực tế. Cần validate cặp thời gian và audit khi đổi lịch.
5. Khi completed, actual times và post_work_result nên bắt buộc; khi verified cần verified_by/verified_at.
6. rejected/cancelled cần lý do; schema chưa có rejection_reason/cancellation_reason. Có thể dùng comments/audit nếu workflow lưu đủ.
7. requested_by NULL có nghĩa cho phép request do hệ thống/import tạo; cần xác nhận.
8. Pivot maintenance_assets phải cùng customer. Asset có bắt buộc cùng server_room_id của request hay được ở room khác cùng tenant?
9. work_type=inspection có chồng lấn với Inspection module không? Cần định nghĩa phạm vi.

### 2.7 server_room_equipment_movements

**Đã nhất quán**
- Dùng from_server_room_id/to_server_room_id, asset_id nullable, movement_type check_in/check_out/relocate.
- Quy tắc from/to theo movement_type đã có trong Data Dictionary và State Machine.
- Movement completed bất biến; cập nhật asset và movement trong cùng transaction.
- Chuyển Rack/U trong cùng room cho phép from=to.

**Mâu thuẫn/thiếu cần giải quyết**
1. Data Dictionary có movement_at nhưng chưa định nghĩa là thời điểm yêu cầu, dự kiến hay thực thi. Batch 2 đề xuất status và executed_at nhưng đây là cột bổ sung chưa được phê duyệt.
2. State Machine cần draft/pending_approval/approved/rejected/completed/cancelled; cần status để biểu diễn lifecycle.
3. Đề xuất tách requested_at và executed_at; không dùng một movement_at cho hai ý nghĩa. Có thể giữ movement_at chỉ khi định nghĩa rõ.
4. performed_by là người thực hiện; executed_at là thời điểm hoàn tất. Khi approved cần approved_by/approved_at; khi completed cần performed_by/executed_at.
5. Với asset_id khác NULL, asset hiện tại phải ở from_server_room_id trước khi relocate/check_out. Check_in cần định nghĩa asset chưa thuộc phòng hoặc trạng thái phù hợp.
6. Với asset_id NULL, equipment_name/serial/tag snapshot phải đủ cho thiết bị ngoài danh mục. Có quy trình tạo asset sau check_in không?
7. Đổi Rack/U trong cùng room: source_location/destination_location hiện là text; nếu cần đối soát chính xác, cân nhắc snapshot rack ID/U riêng.
8. Cần candidate keys và composite FK cho from room, to room, asset. Với asset, kiểm tra thuộc đúng source room; cùng customer là chưa đủ.
9. Cấm movement xuyên customer. Check_in/check_out có một đầu NULL theo type.

## 3. Ma trận tenant integrity

| Bảng con | Quan hệ cần bảo đảm | Điều kiện |
|---|---|---|
| access_logs | room + customer | Composite FK |
| access_logs | access_person + customer + room | Composite FK/service check |
| inspections | room + customer | Composite FK |
| inspections | template + customer | Composite FK; kiểm tra template_version |
| inspection_results | inspection + customer | Composite FK |
| inspection_results | checklist_item + customer | Composite FK; kiểm tra đúng template/version |
| incidents | room + customer | Composite FK |
| incident_actions | incident + customer | Composite FK |
| maintenance_requests | room + customer | Composite FK |
| equipment_movements | from room + customer | Composite FK nullable theo type |
| equipment_movements | to room + customer | Composite FK nullable theo type |
| equipment_movements | asset + customer + source room | Composite FK/service transaction |
| incident_assets pivot | incident + asset + customer | Composite FK; room constraint nếu cần |
| maintenance_assets pivot | request + asset + customer | Composite FK; room constraint nếu cần |

Actor fields phải được authorization theo tenant và permission; FK users.id không tự chứng minh quyền.

## 4. Quyết định cần chủ dự án xác nhận

1. Access Log: entry_at là giờ vào thực tế; có cần scheduled_entry_at cho đăng ký trước không?
2. Inspection: review_return dùng correction_required hay trả về draft có revision? Đề xuất correction_required.
3. Inspection Result: có cần multi_select? Có bỏ boolean_value và dùng result cho yes_no?
4. Incident: chốt open/in_progress/resolved/closed/cancelled hay cần investigating riêng? Có cần verified như status riêng không?
5. Incident pivot: asset liên quan bắt buộc cùng phòng với incident hay chỉ cùng customer?
6. Incident Action: điều kiện completed/verified và người xác minh có phải khác người thực hiện?
7. Maintenance: có triển khai emergency maintenance ngay phiên bản đầu không?
8. Maintenance pivot: asset phải thuộc room của request hay có thể ở room khác cùng tenant?
9. Maintenance: cấu trúc personnel JSON và quy tắc dữ liệu cá nhân.
10. Movement: tách requested_at/executed_at hay giữ movement_at với một định nghĩa duy nhất?
11. Movement: có cho check_in/check_out thiết bị ngoài danh mục (asset_id NULL), và quy trình tạo asset sau đó?
12. Movement: có cần snapshot Rack/U nguồn/đích có cấu trúc thay vì text?
13. Movement: check_out làm asset chuyển trạng thái nào; asset có thể check_in lại bằng phiếu mới không?

## 5. Điều kiện kỹ thuật trước migration

- [ ] Bổ sung candidate keys (id, customer_id) cho parent tables cần composite FK.
- [ ] Xác định composite FK cho room, template, access_person, incident, asset và maintenance.
- [ ] Chốt allowlist status/transition đồng nhất giữa Data Dictionary, State Machine, model và UI.
- [ ] Chốt snapshot và immutability/revision cho Inspection Results.
- [ ] Chốt time semantics cho access log, inspection, maintenance và movement.
- [ ] Chốt asset location lifecycle khi movement check_in/check_out/relocate.
- [ ] Viết test tenant mismatch, invalid transition, timestamp order, duplicate inspection item, movement source mismatch và double execution.
- [ ] Kiểm tra transaction/locking khi execute movement để tránh một asset bị di chuyển đồng thời bởi hai phiếu.

## 6. Kết luận Batch 2

- Đã rà soát đủ 7 bảng và ghi nhận mâu thuẫn/thiếu sót có căn cứ từ ba tài liệu nguồn.
- Các status/field được đề xuất thêm trong Column Schema Batch 2 chưa phải quyết định đã duyệt.
- Chưa tạo Laravel migrations; cần chủ dự án chốt mục 4 trước khi final schema.
