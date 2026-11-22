# ĐẶC TẢ NGHIỆP VỤ UOA SERVER ROOM MANAGEMENT
**Phiên bản:** 0.1 – Bản đối chiếu nguồn, chưa chốt cuối  
**Ngày lập:** 22/11/2026  
**Phạm vi:** PL1–PL5  
**Nhánh triển khai:** `feature/ITSMWEB`  
**Nguyên tắc:** Giữ nguyên nghiệp vụ đã có; không tự suy diễn phần chưa được nguồn xác nhận.

---

## 1. Mục đích và trạng thái tài liệu

Tài liệu này gom các quy tắc nghiệp vụ đọc được từ mã Apps Script và cấu trúc dữ liệu Excel của năm phụ lục. Đây là bản đặc tả đối chiếu lần 1, chưa phải tài liệu cho phép triển khai chính thức.

Nguồn được đối chiếu:
- Mã Apps Script PL1, PL2, PL3, PL4, PL5 do người dùng cung cấp.
- Năm workbook Excel tương ứng PL1–PL5.
- Các URL Form và Dashboard do người dùng cung cấp.
- Các phần mã liên quan được đọc trực tiếp trong từng Apps Script.

**Giới hạn xác minh:** Công cụ hiện không đọc được nội dung đang hiển thị của các Google Forms và Web App từ URL (Google trả về lỗi không truy cập được trong phiên kiểm tra). Vì vậy, các trường và hành vi dưới đây lấy từ Apps Script và workbook; chưa tuyên bố đã xác minh giao diện live. Những điểm cần kiểm tra trực tiếp được đánh dấu **CHỜ XÁC NHẬN**.

## 2. Bản đồ hệ thống và quan hệ

| Phân hệ | Chức năng nghiệp vụ | Dữ liệu chính | Phụ thuộc |
|---|---|---|---|
| PL1 | Quản lý yêu cầu cấp mới, thay đổi, thu hồi quyền vào phòng Server | Access Requests, Access Register, Server Rooms, Room Approvers, Audit Log, Email Queue | Danh mục phòng và người duyệt là nguồn dùng chung |
| PL2 | Đăng ký lượt vào phòng, duyệt, CHECK-IN bằng OTP, CHECK-OUT | Access Log, Email Queue, Audit Log | Lấy phòng/người duyệt từ PL1 |
| PL3 | Kiểm tra phòng Server hằng ngày, xác nhận tiếp nhận, cảnh báo quá hạn, phát hiện FAIL | Inspections, Inspection Details, Holidays, Settings, Audit Log | Lấy phòng và đầu mối từ PL1; tích hợp PL4 |
| PL4 | Ghi nhận và xử lý ISSUE, có nguồn tự động từ PL3 hoặc tạo thủ công | PL4 Issues, PL4 Form Responses, PL4 Audit Log, Settings | PL1 để xác định phòng/người duyệt; PL3 để nhận FAIL |
| PL5 | Đăng ký, phê duyệt, thực hiện, tạm dừng/tiếp tục, báo cáo và xác minh công việc/bảo trì | Work Requests, Work Approvals, Work Execution, Work Verification, Work Audit Log, Work Catalog, Email Queue/Log | Lấy phòng/người duyệt từ PL1 |

### 2.1. Nguyên tắc liên kết dữ liệu

1. PL1 là nguồn danh mục phòng Server và cấu hình người phê duyệt theo phòng.
2. PL2 và PL5 kiểm tra lựa chọn phòng gửi lên phải khớp với danh mục phòng PL1 tại thời điểm xử lý; không chỉ tin vào giá trị dropdown cũ.
3. PL3 lấy phòng đang ACTIVE và các đầu mối liên hệ từ PL1.
4. PL4 đọc PL1 để kiểm tra phòng và người phê duyệt; dữ liệu PL3 phải có khóa tham chiếu để chống tạo ISSUE trùng.
5. Mỗi PL giữ nhật ký nghiệp vụ riêng. Không gộp hoặc xóa lịch sử PL1–PL5 khi chuyển nền tảng.

---

## 3. PL1 – Quản lý quyền truy cập phòng Server

**URL Web App do người dùng cung cấp:**  
https://script.google.com/macros/s/AKfycbzHajH4HqrKh8AgCrTYYK-rQDSN4dut7uSudO0rp2FxeetHeS-UnF5xAWfOq8UoFOpkkw/exec

### 3.1. Loại yêu cầu

Mã nguồn nhận diện ba loại:
- `New Access` – đăng ký cấp quyền mới.
- `Change Access` – thay đổi quyền đã có.
- `Revoke Access` – thu hồi quyền.

Các trường dữ liệu được đọc từ Form:
- Phòng Server;
- Loại yêu cầu;
- Họ và tên người được cấp quyền;
- Email người được cấp quyền;
- Đơn vị;
- Vai trò / Lý do;
- Quyền truy cập;
- Thời hạn từ ngày, đến ngày;
- Người đề xuất;
- Email người đề xuất.

### 3.2. Quy tắc kiểm tra trước khi tạo yêu cầu

Khóa đối chiếu quyền hiện có là **Email người được cấp quyền + Room_ID**:
- NEW: không cho tạo mới nếu cùng email đã có bản ghi ACTIVE tại đúng phòng.
- NEW: cho phép đăng ký ở phòng khác; một người có thể có quyền riêng tại nhiều phòng.
- NEW: nếu cùng email + phòng có bản ghi REVOKED thì có thể tái sử dụng bản ghi đó khi được duyệt.
- CHANGE: chỉ hợp lệ khi người đó đang có quyền ACTIVE tại đúng phòng; không cho chuyển quyền giữa hai phòng bằng CHANGE.
- REVOKE: chỉ hợp lệ khi người đó có quyền ACTIVE tại đúng phòng.
- Nếu email đang ACTIVE ở phòng khác nhưng không ACTIVE ở phòng được chọn, hệ thống báo sai phòng thay vì tự thay đổi/thu hồi quyền khác.

Các điều kiện CHANGE/REVOKE được kiểm tra lúc gửi yêu cầu và kiểm tra lại lúc duyệt để tránh dữ liệu đã thay đổi trong thời gian chờ.

### 3.3. Luồng xử lý

1. Người đề xuất gửi Form.
2. Hệ thống kiểm tra trạng thái hệ thống ACTIVE, Form Response ID và dữ liệu phòng.
3. Form Response ID được dùng chống xử lý trùng.
4. Hệ thống kiểm tra điều kiện NEW/CHANGE/REVOKE với Access Register.
5. Tạo Request ID, token ngẫu nhiên; chỉ lưu hash token và thời hạn token.
6. Tạo yêu cầu với trạng thái `PENDING`.
7. Gửi email phê duyệt đến **một người duyệt của đúng phòng**, theo cấu hình PL1 đã chụp tại thời điểm gửi.
8. Người duyệt mở link token và chọn approve hoặc reject.
9. Token phải đúng, còn hạn, yêu cầu còn PENDING; sau quyết định token bị vô hiệu hóa.
10. Nếu APPROVED, cập nhật Access Register trước khi hoàn tất quyết định; nếu REJECTED thì không cập nhật quyền đang hiệu lực.
11. Ghi Audit Log và xếp email thông báo kết quả vào hàng đợi.

### 3.4. Quy tắc cập nhật Access Register

- NEW: thêm bản ghi mới nếu chưa có bản ghi cùng email + phòng; nếu đã có dòng REVOKED thì tái sử dụng dòng đó.
- CHANGE: cập nhật bản ghi ACTIVE hiện có tại đúng phòng.
- REVOKE: cập nhật bản ghi hiện có, ghi Request ID, thông tin phê duyệt và chuyển Record Status thành `REVOKED`.
- Khi APPROVED, trạng thái bản ghi quyền tương ứng là `ACTIVE` đối với NEW/CHANGE.

**Điểm cần xác nhận:** Thời hạn hiệu lực Valid From/Valid Until có được tự động dùng để tự chuyển Record Status hay chỉ lưu thông tin thời hạn? Mã nguồn được rà soát hiện chưa đủ căn cứ để khẳng định có bộ lập lịch tự động hết hạn.

### 3.5. Email queue PL1

PL1 có queue riêng, độc lập PL5:
- Trạng thái: `PENDING`, `SENDING`, `RETRY`, `SENT`, `FAILED`.
- Batch tối đa 8 email mỗi lượt.
- Tối đa 12 lần thử.
- Retry cơ sở 5 phút, tăng theo cấp số nhân và giới hạn 6 giờ.
- Dòng SENDING quá 15 phút có cơ chế khôi phục.
- Event_Key chống enqueue trùng.
- Có thao tác retry thủ công cho dòng FAILED sau khi xem Last_Error.
- Email được lưu queue trước khi gửi; lỗi gửi email không được đảo ngược quyết định nghiệp vụ đã commit.
- Cửa sổ lỗi sau khi Gmail nhận email nhưng trước khi cập nhật SENT có thể gây gửi ít nhất một lần; không thể cam kết exactly-once.

---

## 4. PL2 – Đăng ký và ghi nhận lượt ra/vào phòng Server

**Form URL:** https://forms.gle/rASpPuZ1REW48sru9

### 4.1. Dữ liệu đăng ký

Workbook Access Log và mã nguồn thể hiện các thông tin chính:
- Tên / Đơn vị;
- Mục đích / Công việc;
- Người phê duyệt;
- Người giám sát;
- Email người đăng ký;
- Số lượng khách;
- Ngày và giờ dự kiến vào;
- Phòng Server, vị trí;
- Visit_ID;
- Approval_Status;
- Approval_Token;
- Room_Approver_Email;
- Thời hạn phê duyệt theo phòng (cấu hình workbook mẫu có giá trị 24 giờ).

### 4.2. Luồng phê duyệt

1. Người đăng ký gửi Form ENTRY.
2. Hệ thống ghi Visit_ID và dữ liệu đăng ký, trạng thái phê duyệt ban đầu PENDING.
3. Hệ thống gửi link token cho người duyệt.
4. Người duyệt chọn APPROVED hoặc REJECTED; quyết định chỉ thực hiện khi bản ghi còn PENDING và token hợp lệ.
5. Hệ thống ghi Approved_By, Approved_At, xóa Approval_Token và ghi Audit Log.
6. Nếu APPROVED, tạo Checkin_Token và gửi email cho người đăng ký chứa link CHECK-IN.
7. Nếu REJECTED, gửi email thông báo kết quả cho người đăng ký.

### 4.3. CHECK-IN bằng OTP

1. Người đăng ký mở link CHECK-IN.
2. Hệ thống chỉ cho tiếp tục khi Approval_Status là APPROVED và Visit_Status chưa được thiết lập.
3. Người đăng ký yêu cầu gửi OTP đến Requester_Email đã đăng ký; không cho thay email nhận OTP.
4. OTP gồm 6 chữ số; hệ thống lưu hash, không lưu OTP dạng rõ trong bản ghi nghiệp vụ.
5. OTP chỉ được kích hoạt thời hạn sau khi email OTP gửi thành công; thời hạn là 10 phút.
6. Yêu cầu OTP mới hủy các OTP email đang PENDING/RETRY của cùng Visit_ID và thay hash OTP cũ.
7. Giới hạn 5 lần nhập sai; khi đủ 5 lần, người dùng phải yêu cầu cấp OTP mới.
8. Người dùng nhập OTP và **tên người thực tế vào phòng**.
9. Nếu OTP hợp lệ, hệ thống ghi Entry Time, Date, Checkin_By, thời điểm xác minh OTP; chuyển Visit_Status thành `OPEN`; vô hiệu hóa Checkin_Token và OTP.
10. Tạo Checkout_Token và gửi link CHECK-OUT.

### 4.4. CHECK-OUT

1. Link CHECK-OUT gắn với đúng Visit_ID và token của lượt đó.
2. Chỉ cho CHECK-OUT khi Visit_Status là `OPEN`.
3. Bắt buộc nhập Work Result và xác nhận đã hoàn tất công việc/rời phòng.
4. Có thể đính kèm ảnh; mã nguồn giới hạn định dạng PNG/JPEG/JPG/WEBP và dung lượng tối đa 5 MB.
5. Ghi Exit Time, Closed_At, Checkout_By, Work_Result, Work_Image_URL; chuyển Visit_Status thành `CLOSED`; xóa Checkout_Token.
6. Ghi Audit Log và xếp email xác nhận CHECK-OUT vào queue.

### 4.5. Trạng thái và email

Các trạng thái nghiệp vụ đã thấy trong mã:
- Approval_Status: `PENDING`, `APPROVED`, `REJECTED`.
- Visit_Status: chưa có trạng thái trước CHECK-IN; sau CHECK-IN là `OPEN`; sau CHECK-OUT là `CLOSED`.

PL2 có Email Queue riêng, worker chạy mỗi 5 phút, có trạng thái SENDING/PENDING/RETRY/SENT/FAILED/CANCELLED theo các nhánh xử lý. OTP chỉ bắt đầu tính hạn sau callback gửi email thành công.

**CHỜ XÁC NHẬN:** Quy tắc hết hạn yêu cầu phê duyệt theo Room_Approval_Timeout_Hours (24 giờ trong workbook mẫu): cần xác minh chính xác trigger xử lý yêu cầu quá hạn và trạng thái cuối cùng; không suy ra chỉ từ giá trị cấu hình.

---

## 5. PL3 – Kiểm tra phòng Server hằng ngày

**Form URL:** https://forms.gle/dxyynJbxgfeck8VA7

### 5.1. Biểu mẫu kiểm tra

Các trường chính:
- Phòng Server;
- Ngày kiểm tra, giờ kiểm tra;
- Người thực hiện;
- Nhiệt độ (°C), độ ẩm (%), có thể để trống;
- 12 hạng mục I01–I12;
- Mỗi hạng mục chọn PASS / FAIL / N/A;
- Ghi chú / hành động khắc phục cho từng hạng mục;
- Kết luận;
- Xác nhận người thực hiện.

Kết luận hợp lệ:
- `PASS - Không phát hiện vấn đề`;
- `ISSUES - Cần thực hiện hành động khắc phục`;
- `EMERGENCY - Cần ứng phó khẩn cấp`.

Quy tắc:
- Phải có kết quả PASS/FAIL/N/A cho đủ I01–I12.
- Nếu một hạng mục FAIL thì bắt buộc có ghi chú/hành động.
- Nhiệt độ và độ ẩm nếu nhập phải là số.
- Phòng gửi lên phải khớp chính xác với danh mục phòng ACTIVE hiện tại của PL1.
- Form Response ID chống xử lý lặp.

### 5.2. Ghi nhận và xác nhận UOA

1. Khi nhận Form, hệ thống tạo Inspection_ID.
2. Ghi một bản ghi Inspections và 12 dòng Inspection Details.
3. Nếu có FAIL, sinh PL4 link token và thực hiện luồng chuyển dữ liệu sang PL4.
4. Gửi email yêu cầu UOA xem và xác nhận tiếp nhận hồ sơ.
5. UOA mở token, xem thông tin và xác nhận.
6. Ghi trạng thái xác nhận, người xác nhận, thời điểm xác nhận và Audit Log.

**Quy tắc rất quan trọng:** Xác nhận UOA ở PL3 chỉ chứng minh UOA đã tiếp nhận/xem hồ sơ. Nó **không đồng nghĩa** các hạng mục FAIL đã được khắc phục hoặc ISSUE đã đóng.

### 5.3. Cảnh báo thiếu phiếu kiểm tra hằng ngày

Cấu hình đọc được từ Settings:
- DAILY_DEADLINE;
- WORK_DAYS;
- ALERT_LEVEL1_DELAY_MINUTES;
- ALERT_LEVEL2_DELAY_MINUTES;
- ALERT_LEVEL3_DELAY_MINUTES;
- DAILY_ALERT_ENABLED;
- ALERT_CHECK_INTERVAL_MINUTES;
- danh sách Holidays;
- đầu mối MSTAR_Email, Approver_Email, Manager_Email theo từng phòng lấy từ PL1.

Quy tắc từ mã:
- Chỉ xử lý ngày làm việc nằm trong WORK_DAYS và không nằm trong ngày nghỉ được kích hoạt trong Holidays.
- Kiểm tra theo từng phòng và ngày; xác định đã có phiếu dựa trên Room_ID + Inspection_Date + Form_Response_ID.
- Sau DAILY_DEADLINE nếu chưa có phiếu thì gửi cảnh báo theo 3 cấp.
- Cấp cảnh báo kế tiếp được tính từ thời điểm cấp trước đã gửi cộng thời gian delay cấu hình.
- Nếu thiếu email người nhận cấp tương ứng, ghi sự kiện SKIPPED và chuyển cấp trong trạng thái escalation.
- Hệ thống ghi Audit Log cho từng cấp cảnh báo.

**CHỜ XÁC NHẬN:** Cần chốt bảng ánh xạ chính xác cấp 1/2/3 tới người nhận (MSTAR, Room Approver, Manager) và các giá trị delay thực tế trong workbook/Settings triển khai.

### 5.4. Tích hợp PL3 → PL4

Mã PL3 có cơ chế gửi các checklist FAIL sang Web App PL4 qua HTTP API, có PL4_WEB_APP_URL và PL4_API_SECRET; đồng thời có khóa tham chiếu để thử lại khi push lỗi và tránh tạo trùng.

Trong cùng file PL3 cũng còn các hàm mang tên luồng PL4 nội bộ/legacy. Cần kiểm tra cấu hình triển khai thực tế để xác định hàm nào đang được trigger và URL nào đang hoạt động. Không được triển khai đồng thời hai cơ chế tạo ISSUE nếu chưa xác nhận, vì có nguy cơ nhân đôi nghiệp vụ.

---

## 6. PL4 – Quản lý ISSUE / sự cố

**Form URL:** https://forms.gle/U1AJCsNtj6sRyJbEA

### 6.1. Hai nguồn tạo ISSUE

- `PL3`: tự động từ từng checklist item FAIL.
- `MANUAL`: người dùng tạo thủ công qua Form PL4; Settings có ALLOW_MANUAL_CREATION.

Với nguồn PL3, mỗi FAIL item là một ISSUE riêng. Khóa PL3_Reference_Key dùng để ngăn tạo trùng theo nguồn Inspection và Checklist Item.

### 6.2. Dữ liệu ISSUE

Các trường chính trong PL4 Issues:
- Issue_ID, Created_At, Source_Type;
- Source_Inspection_ID, Source_Room_ID, Source_Room_Name, Source_Location;
- Source_Checklist_Item_ID, Source_Checklist_Item, Source_FAIL_Remarks;
- Title, Description, Priority;
- Room_ID, Room_Name, Location;
- Reporter_Name, Reporter_Email;
- Assignee_Name, Assignee_Email;
- Approver_Name, Approver_Email, MSTAR_Email;
- Action_Taken, Resolution_Notes, Evidence_URL;
- Status, Submitted_For_Approval_At;
- Approved_By, Approved_At, Approval_Result, Approval_Notes;
- Assignee_Token, Approval_Token, PL3_Reference_Key.

### 6.3. Luồng xử lý đọc được từ mã

1. ISSUE được tạo từ PL3 hoặc MANUAL.
2. Với ISSUE PL3, thông tin checklist gốc được hiển thị để người xử lý tham chiếu; phần Form PL4 thu thập thông tin xử lý do người thực hiện nhập.
3. Gửi thông báo cho người được phân công.
4. Người được phân công mở link token, xác nhận nhận xử lý và gửi biện pháp xử lý, kết quả, minh chứng.
5. Sau khi gửi kết quả, hệ thống tạo/gửi link yêu cầu người duyệt xem xét.
6. Người duyệt phải là Approver hiện đang được cấu hình trong PL1 cho đúng phòng; nếu người duyệt đã thay đổi so với snapshot ISSUE, hệ thống từ chối xử lý và yêu cầu cập nhật.
7. Người duyệt chọn APPROVE hoặc REJECT và bắt buộc có ghi chú.
8. APPROVE chuyển ISSUE thành `CLOSED`; REJECT chuyển thành `REJECTED`; token phê duyệt bị vô hiệu hóa.
9. Ghi Audit Log và gửi kết quả cho Reporter, Assignee, Approver.

### 6.4. Trạng thái

Các trạng thái thấy trong mã và workbook:
- `OPEN`;
- `IN_PROGRESS`;
- `PENDING_APPROVAL`;
- `CLOSED`;
- `REJECTED`.

Cần xác nhận chính xác điểm chuyển OPEN → IN_PROGRESS và điều kiện nào cho phép gửi phê duyệt; đồng thời xác nhận ý nghĩa nghiệp vụ của REJECTED (từ chối xử lý hay từ chối kết quả hoàn thành). Không tự đồng nhất REJECTED với CLOSED.

### 6.5. Cấu hình và tích hợp

- PL4 đọc danh mục phòng/người duyệt từ PL1.
- PL4 có API secret để nhận dữ liệu PL3; phải lưu secret an toàn, không ghi vào tài liệu hoặc log công khai.
- PL4 có ALLOW_MANUAL_CREATION và SYSTEM_STATUS.
- Mã PL4 hiện dùng MailApp trực tiếp ở các luồng email được rà soát; chưa thấy queue-first retry tương đương PL1/PL2/PL5.

---

## 7. PL5 – Quản lý công việc và bảo trì

**Form URL:** https://forms.gle/Ej6Toof1Y6KAi4qFA  
**Dashboard URL:** https://script.google.com/a/macros/backup.uoa.com.vn/s/AKfycbzXqV6DVR3naX-JonxHsuuVWxbKqZw_yohsP6aQ4HZszyhP-JoiKnXi3sVMzzxnGn7CHQ/exec

### 7.1. Dữ liệu yêu cầu

Các trường chính:
- Work_Type, Work_Category;
- Phòng Server, vị trí;
- Tiêu đề, mô tả, lý do/mục tiêu;
- Ngày giờ bắt đầu và kết thúc dự kiến;
- Đơn vị thực hiện, nhân sự thực hiện, email người phụ trách;
- Thiết bị/hệ thống liên quan;
- Ảnh hưởng dịch vụ;
- Mức độ rủi ro;
- Phương án dự phòng/hoàn tác;
- Lý do khẩn cấp nếu Work_Type là Công việc khẩn cấp.

Điều kiện:
- Người gửi phải có email respondent hợp lệ.
- Phòng phải khớp danh mục PL1 hiện tại.
- Thời điểm kết thúc phải sau thời điểm bắt đầu.
- Công việc khẩn cấp bắt buộc có lý do khẩn cấp.
- Email người phụ trách thực hiện phải hợp lệ.
- Tiêu đề, mô tả và phương án hoàn tác bắt buộc có.
- Chỉ nhận yêu cầu khi SYSTEM_STATUS = ACTIVE và ALLOW_NEW_REQUESTS = TRUE.
- Form Response ID chống tạo yêu cầu trùng.

### 7.2. Vai trò

- Requester: người đăng ký; có quyền hủy phiếu đang PENDING_APPROVAL bằng Request_Token và phải ghi lý do.
- UOA Approver: người duyệt lấy từ PL1 theo phòng; không được tự duyệt/từ chối phiếu do chính mình đăng ký; bắt buộc ghi ghi chú khi APPROVE/REJECT.
- Executor: đúng email người phụ trách đã đăng ký; thực hiện START, HOLD, RESUME, SUBMIT_RESULT.
- Verifier: UOA Approver theo phòng; xác minh PASS/FAIL; không được là Requester.

### 7.3. State machine

| Trạng thái hiện tại | Hành động | Điều kiện / vai trò | Trạng thái tiếp theo |
|---|---|---|---|
| PENDING_APPROVAL | APPROVE | Approver PL1 đúng phòng, khác Requester, có ghi chú | APPROVED |
| PENDING_APPROVAL | REJECT | Approver PL1 đúng phòng, khác Requester, có lý do | REJECTED |
| PENDING_APPROVAL | CANCEL | Requester đúng email, có lý do | CANCELLED |
| APPROVED | START | Executor đúng email đã đăng ký | IN_PROGRESS |
| IN_PROGRESS | HOLD | Executor đúng email, có lý do | ON_HOLD |
| ON_HOLD | RESUME | Executor đúng email | IN_PROGRESS |
| IN_PROGRESS | SUBMIT_RESULT | Executor đúng email, có kết quả | PENDING_VERIFICATION |
| PENDING_VERIFICATION | VERIFY PASS | Approver đúng phòng, khác Requester, có ghi chú | CLOSED |
| PENDING_VERIFICATION | VERIFY FAIL | Approver đúng phòng, khác Requester, có ghi chú | IN_PROGRESS |

VERIFY FAIL ghi thêm hành động REWORK_REQUIRED vào Work Execution. Vì vậy lịch sử lần thực hiện và lịch sử xác minh phải được giữ riêng, không chỉ ghi đè một trường trạng thái.

### 7.4. Lịch sử và bằng chứng

- Work_Approvals: lịch sử quyết định duyệt/từ chối.
- Work_Execution: START, HOLD, RESUME, SUBMIT_RESULT, REWORK_REQUIRED và nội dung thực hiện.
- Work_Verification: từng lần xác minh PASS/FAIL.
- Work_Audit_Log: nhật ký tổng thể.
- Các thông tin kết quả gồm Result_Notes, Equipment_Changed, Incident_Notes, Evidence_Links.
- Khi PASS, ghi Verified_By, Verified_At, Closed_At; xóa Executor_Token và Verification_Token.
- Khi FAIL, quay lại IN_PROGRESS; không đóng yêu cầu.

### 7.5. Dashboard và email

Dashboard hiện thống kê các trạng thái PENDING_APPROVAL, APPROVED, IN_PROGRESS, ON_HOLD, PENDING_VERIFICATION, CLOSED và liệt kê tối đa 100 yêu cầu gần nhất theo mã nguồn.

PL5 có Work_Email_Queue riêng, worker 5 phút, trạng thái SENDING/PENDING/RETRY/SENT/FAILED và khôi phục SENDING bị treo sau 15 phút. Có Email Log. Queue PL5 độc lập PL1.

---

## 8. Quy tắc kỹ thuật có ảnh hưởng trực tiếp đến nghiệp vụ

1. **Idempotency:** PL1, PL2, PL3, PL5 dùng Form Response ID hoặc Event_Key để ngăn xử lý trùng. PL4 dùng khóa tham chiếu PL3 để ngăn tạo ISSUE trùng.
2. **Token:** Token phê duyệt/thực hiện là credential theo từng quy trình; cần lưu hash hoặc cơ chế bảo vệ tương đương, giới hạn mục đích, hết hạn/vô hiệu hóa sau khi dùng. Không đưa token thực tế vào log/tài liệu.
3. **Audit:** Giữ sự kiện, actor, timestamp, đối tượng và chi tiết; không thay bằng một trường Last_Updated duy nhất.
4. **Email không được đảo ngược nghiệp vụ:** Nếu quyết định đã được ghi nhận, lỗi gửi email không được làm mất quyết định; phải có trạng thái gửi và cơ chế xử lý lại phù hợp.
5. **Phân quyền theo phòng:** Người duyệt được xác định theo Room_ID từ PL1; không dùng một approver chung cho mọi phòng nếu cấu hình gốc là theo phòng.
6. **Lịch sử:** PL5 giữ các lần thực hiện và xác minh riêng; PL2 giữ các mốc ENTRY/CHECK-IN/CHECK-OUT; PL4 giữ lịch sử xử lý/phê duyệt; PL3 giữ chi tiết từng checklist item.
7. **Múi giờ:** Mã nguồn dùng Asia/Ho_Chi_Minh; hệ thống PHP cần thống nhất timezone nghiệp vụ.
8. **Không gộp queue:** PL1, PL2 và PL5 đang có queue riêng. Việc dùng một dịch vụ email chung ở PHP là quyết định kỹ thuật; phải giữ được cấu hình retry, event, lịch sử và tính độc lập nghiệp vụ của từng PL.

---

## 9. Các điểm chưa được phép tự quyết

| Mã | Nội dung cần xác nhận | Lý do |
|---|---|---|
| Q01 | PL1 Valid Until có tự động hết hạn quyền hay chỉ là dữ liệu thời hạn | Chưa đủ bằng chứng về scheduler tự đổi Record Status |
| Q02 | Thời hạn token phê duyệt PL1 và cách xử lý khi hết hạn | Cần đối chiếu giá trị Settings triển khai |
| Q03 | PL2 xử lý Visit quá thời hạn phê duyệt 24 giờ như thế nào | Có cấu hình timeout trong workbook; cần xác minh nhánh xử lý |
| Q04 | PL2 có giới hạn thời gian được CHECK-IN sau khi duyệt hay không | Chưa chốt từ các đoạn mã đã rà |
| Q05 | PL3 ánh xạ cấp cảnh báo 1/2/3 tới MSTAR/Approver/Manager và delay thực tế | Cần lấy đúng giá trị Settings đang chạy |
| Q06 | PL3 đang dùng tích hợp PL4 API độc lập hay các hàm PL4 nội bộ/legacy | Trong Code PL3 tồn tại nhiều hàm PL4; cần xác minh deployment/triggers |
| Q07 | PL4 chính xác điều kiện chuyển OPEN → IN_PROGRESS và ý nghĩa REJECTED | Cần đọc toàn bộ nhánh assignee và đối chiếu dữ liệu lịch sử |
| Q08 | PL4 có queue/retry email ở bản đang triển khai không | Mã nguồn đã đọc cho thấy gửi MailApp trực tiếp ở các nhánh; cần kiểm tra các nhánh còn lại |
| Q09 | Dashboard PL5 phân quyền theo tài khoản/nhóm nào và các thao tác quản trị nào được hiển thị | URL live chưa đọc được trong phiên này |
| Q10 | Danh sách trường Form đang phát hành có khác mã Apps Script/workbook không | Chưa truy cập trực tiếp được giao diện Google Forms |

---

## 10. Điều kiện để chuyển sang thiết kế PHP/MySQL

Chỉ chuyển sang bước chốt ERD, migration và code khi:
- Các câu hỏi Q01–Q10 được xác nhận hoặc được chủ nghiệp vụ chấp thuận cách xử lý.
- Bản đồ trạng thái PL1–PL5 được duyệt.
- Danh sách vai trò và quyền theo phòng được duyệt.
- Danh sách email event/recipient và quy tắc retry được duyệt.
- Cách tích hợp PL3 → PL4 được xác nhận một cơ chế chính thức.
- Form field và Dashboard actions được đối chiếu với giao diện thực tế.

**Kết luận:** Bản 0.1 đã trích xuất được khung nghiệp vụ và các state machine chính từ mã nguồn. Đây chưa phải bản chốt cuối; các câu hỏi ở Mục 9 phải được giải quyết trước khi sửa schema hoặc viết chức năng nghiệp vụ PHP.
