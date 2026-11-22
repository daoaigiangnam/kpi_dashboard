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

**Quyết định nghiệp vụ đã xác nhận:** Quyền truy cập có hiệu lực theo khoảng thời gian Valid From/Valid Until. Hệ thống PHP phải tự động xác định quyền hết hiệu lực khi đến hạn; không xóa Access Register. Đề xuất trạng thái riêng `EXPIRED` để phân biệt hết hạn theo lịch với `REVOKED` do người có thẩm quyền thu hồi. **Đối chiếu mã nguồn:** PL1 Apps Script hiện tại chưa có trạng thái `EXPIRED` và chưa thấy tác vụ tự động chuyển Record Status khi Valid Until đến hạn. Đây là chức năng cần bổ sung khi chuyển PHP. Cần chốt quy tắc thời điểm biên: quyền còn hiệu lực đến hết ngày Valid Until theo múi giờ Asia/Ho_Chi_Minh, sau đó chuyển EXPIRED.

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

**Quyết định đã chốt:** Nếu yêu cầu vẫn PENDING sau 12 giờ, gửi email nhắc người duyệt. Khi đủ 24 giờ tính từ Created_At mà chưa có quyết định, tự chuyển sang `EXPIRED`, vô hiệu hóa Approval_Token và ghi Audit Log. Không tự động APPROVED. Người đăng ký cần tạo yêu cầu mới nếu vẫn có nhu cầu. Yêu cầu EXPIRED không được duyệt bằng link cũ.

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

**Quyết định người nhận đã chốt lại:** Giữ theo cách PL3 hiện đang gửi trong Apps Script: Cấp 1 gửi MSTAR; Cấp 2 gửi người phụ trách phòng (Approver); Cấp 3 gửi Manager. **Quyết định đã chốt:** Cấp 1 tại DAILY_DEADLINE; cấp 2 và cấp 3 theo các mốc thời gian do Admin cấu hình. Không hard-code thời gian trong chương trình. Khi phiếu hợp lệ được ghi nhận cho đúng Room_ID/ngày, dừng các cảnh báo còn lại. **Đối chiếu mã nguồn:** PL3 hiện ánh xạ recipients theo thứ tự MSTAR → Approver → Manager, không trùng với người nhận đã chốt; đồng thời delay hiện được tính nối tiếp từ thời điểm gửi cấp trước. Khi triển khai PHP phải sửa ánh xạ và triển khai mốc theo quyết định đã chốt, không sao chép nguyên logic hiện tại.

### 5.4. Tích hợp PL3 → PL4

Mã PL3 có cơ chế gửi các checklist FAIL sang Web App PL4 qua HTTP API, có PL4_WEB_APP_URL và PL4_API_SECRET. Theo xác nhận nghiệp vụ, dữ liệu liên kết tối thiểu phải có `Inspection_ID` (ID PL3), `Inspection_Item_ID` và token xác thực. PL4 lưu khóa tham chiếu ngược về PL3 và chống tạo trùng khi nhận lại cùng sự kiện.

Token xác thực API phải tách biệt token thao tác của người thực hiện/người duyệt; không ghi secret hoặc token nhạy cảm vào log công khai. Trong cùng file PL3 còn các hàm PL4 legacy; cần chọn duy nhất cơ chế API ID + Token làm luồng tích hợp chính thức, không kích hoạt song song cơ chế tạo ISSUE khác.

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

### 6.3. Luồng xử lý hiện tại trong Apps Script

1. ISSUE được tạo từ PL3 hoặc MANUAL với trạng thái `OPEN`.
2. Người được phân công nhận ISSUE bằng Assignee_Token; hệ thống ghi Assignee và chuyển `IN_PROGRESS`.
3. Người thực hiện nhập Action_Taken, Resolution_Notes và Evidence_URL rồi gửi yêu cầu xem xét; hệ thống chuyển `PENDING_APPROVAL`.
4. Người duyệt được xác định từ PL1 theo phòng, chọn APPROVE hoặc REJECT và bắt buộc ghi chú.
5. APPROVE chuyển `CLOSED`; REJECT chuyển `REJECTED` và cho phép Assignee tiếp tục cập nhật.
6. Ghi Audit Log và gửi kết quả cho Reporter, Assignee, Approver.

### 6.4. Quyết định nghiệp vụ đã chốt

Để phân biệt rõ báo cáo của người thực hiện và nghiệm thu của UOA, áp dụng quy trình:

1. Người thực hiện được chọn báo cáo `COMPLETED` (đã xử lý) hoặc `NOT_COMPLETED` (chưa xử lý), bắt buộc ghi kết quả và minh chứng phù hợp.
2. Cả hai lựa chọn đều chuyển hồ sơ sang `PENDING_UOA_CONFIRMATION`; đây là trạng thái chờ UOA xem xét, không có nghĩa ISSUE đã đóng.
3. UOA kiểm tra kết quả:
   - Xác nhận hoàn thành: chuyển `CLOSED`.
   - Không xác nhận: bắt buộc ghi lý do, trả về `IN_PROGRESS` để tiếp tục khắc phục.
4. Người thực hiện không được tự đóng ISSUE.
5. Mỗi lần báo cáo của Assignee và mỗi lần xác nhận/không xác nhận của UOA phải ghi thành bản ghi lịch sử riêng, không ghi đè kết quả cũ.

UOA là bên xác nhận cuối cùng; không bắt buộc có bước UOA duyệt phân công ban đầu. Quy trình này thay thế bước APPROVE/REJECT sau khi Assignee báo cáo trong Apps Script hiện tại.

### 6.5. Trạng thái

**Trạng thái hiện tại trong mã:** `OPEN`, `IN_PROGRESS`, `PENDING_APPROVAL`, `CLOSED`, `REJECTED`.

**Trạng thái đã chốt:** `OPEN` → `IN_PROGRESS` → `PENDING_UOA_CONFIRMATION` → `CLOSED`; nếu UOA không xác nhận thì quay lại `IN_PROGRESS`. Không dùng `REJECTED` cho kết quả UOA không nghiệm thu; ghi quyết định `NOT_CONFIRMED` trong lịch sử xác minh để tránh nhầm với từ chối xử lý.

### 6.6. Cấu hình và tích hợp

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

**Quyết định đã xác nhận:** Dashboard PL5 giai đoạn đầu chỉ có quyền xem (read-only), dành cho nhân sự UOA và nhóm MSTAR. Dashboard được phép hiển thị số liệu tổng hợp, danh sách và chi tiết Work_ID, trạng thái, lịch sử duyệt/thực hiện/xác minh. Không được tạo/sửa/hủy phiếu, duyệt/từ chối, thay đổi người thực hiện, xác minh/đóng công việc hoặc xóa dữ liệu. Các thao tác nghiệp vụ tiếp tục theo Form/Token hiện có. Dashboard hiện thống kê các trạng thái PENDING_APPROVAL, APPROVED, IN_PROGRESS, ON_HOLD, PENDING_VERIFICATION, CLOSED và liệt kê tối đa 100 yêu cầu gần nhất theo mã nguồn. Việc phân quyền phải giới hạn người dùng vào đúng nhóm UOA hoặc MSTAR đã được cấp quyền; không công khai Dashboard.

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
| Q01 | Chốt thời điểm biên Valid Until và chu kỳ tác vụ tự động EXPIRED của PL1 | PL1 Apps Script hiện chưa tự chuyển EXPIRED; cần chốt cách hiểu ngày hết hạn và tần suất cập nhật trạng thái trong PHP |
| Q02 | Thời hạn token phê duyệt PL1 và cách xử lý khi hết hạn | Cần đối chiếu giá trị Settings triển khai |
| Q03 | PL2: nhắc ở 12 giờ, EXPIRED ở 24 giờ, không tự duyệt | ĐÃ CHỐT theo quyết định D08 |
| Q04 | PL2 có giới hạn thời gian được CHECK-IN sau khi duyệt hay không | Chưa chốt từ các đoạn mã đã rà |
| Q05 | PL3 cách tính mốc cấp 2/cấp 3 | Không cố định trong code; Admin phải cấu hình động thời điểm hoặc khoảng trễ của từng cấp và cách tính mốc. Hệ thống thực thi theo cấu hình đang có hiệu lực. |
| Q06 | Xác minh deployment thực tế chỉ kích hoạt tích hợp API PL3 ID + Token | Chủ nghiệp vụ đã chọn cơ chế ID PL3 + Token; cần kiểm tra trigger/deployment đang chạy |
| Q07 | State machine PL4: Assignee báo cáo, UOA xác nhận cuối cùng; không cho Assignee tự đóng | ĐÃ CHỐT theo quyết định D10; thay thế bước APPROVE/REJECT sau báo cáo trong Apps Script |
| Q08 | PL4 có queue/retry email ở bản đang triển khai không | Mã nguồn đã đọc cho thấy gửi MailApp trực tiếp ở các nhánh; cần kiểm tra các nhánh còn lại |
| Q09 | Chốt phạm vi read-only PL5 Dashboard và nhóm tài khoản được xem | ĐÃ CHỐT: read-only; nhóm được xem gồm nhân sự UOA và nhóm MSTAR |
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


---

## 11. Nhật ký quyết định nghiệp vụ – cập nhật 22/11/2026

| Mã | Nội dung | Kết quả |
|---|---|---|
| D01 | PL1 tự hết hạn quyền truy cập | ĐÃ XÁC NHẬN: có hiệu lực theo khoảng thời gian Valid From/Valid Until; tự hết hạn, không xóa lịch sử. Đề xuất trạng thái EXPIRED riêng với REVOKED. |
| D02 | PL2 yêu cầu chờ duyệt quá 24 giờ | ĐÃ CHỐT: nhắc người duyệt ở giờ thứ 12; đủ 24 giờ từ Created_At mà vẫn PENDING thì chuyển EXPIRED, vô hiệu hóa link duyệt, ghi Audit Log; không tự động duyệt. |
| D03 | PL3 người nhận cảnh báo | ĐÃ CHỐT LẠI: giữ theo Apps Script hiện tại — Cấp 1 MSTAR; Cấp 2 người phụ trách phòng (Approver); Cấp 3 Manager. Quyết định này thay thế ánh xạ người nhận trước đó. |
| D04 | PL3 thời gian cảnh báo | ĐÃ CHỐT: Cấp 1 theo DAILY_DEADLINE; thời điểm/khoảng trễ cấp 2 và cấp 3 do Admin cấu hình động. Không hard-code mốc thời gian. Khi có phiếu hợp lệ thì dừng cảnh báo còn lại. |
| D05 | PL3 → PL4 | ĐÃ XÁC NHẬN: tích hợp bằng ID PL3 (Inspection_ID), checklist item ID và Token xác thực; cần chống gửi/tạo ISSUE trùng. |
| D06 | PL4 xác nhận hoàn thành | ĐÃ CHỐT: Assignee báo cáo COMPLETED/NOT_COMPLETED; cả hai đều chuyển PENDING_UOA_CONFIRMATION. UOA xác nhận thì CLOSED; không xác nhận (bắt buộc lý do) thì về IN_PROGRESS. Assignee không được tự đóng ISSUE. Lưu riêng lịch sử báo cáo và xác minh. |
| D07 | PL5 Dashboard | ĐÃ XÁC NHẬN: giai đoạn đầu chỉ xem; không có thao tác ghi/sửa/duyệt/xóa trên Dashboard. Nhóm được xem: UOA và MSTAR. |

### 11.1. Những điểm cần chủ nghiệp vụ duyệt tiếp





| D08 | Xác nhận phương án xử lý PL2 quá hạn | ĐÃ CHỐT: nhắc ở 12 giờ; tự EXPIRED ở 24 giờ tính từ Created_At nếu chưa duyệt; vô hiệu hóa token; không tự động APPROVED. |
| D09 | Lịch cảnh báo PL3 | ĐÃ CHỐT: Cấp 1 tại DAILY_DEADLINE; cấp 2 sau 30 phút; cấp 3 sau 60 phút nếu chưa có phiếu hợp lệ; dừng cảnh báo khi đã ghi nhận phiếu hợp lệ. |

| D10 | Quy trình xử lý và xác nhận ISSUE PL4 | ĐÃ CHỐT: Assignee báo cáo COMPLETED hoặc NOT_COMPLETED; cả hai đều chờ UOA xác nhận. UOA xác nhận thì CLOSED; không xác nhận phải ghi lý do và trả về IN_PROGRESS. Assignee không tự đóng ISSUE. Lưu lịch sử từng lần báo cáo/xác minh. |

| D11 | Nhóm người dùng được xem PL5 Dashboard | ĐÃ CHỐT: nhân sự UOA và nhóm MSTAR; Dashboard vẫn read-only, không công khai và phải kiểm soát quyền truy cập theo nhóm. |


---

## 12. Kết quả rà soát sai khác giữa nghiệp vụ đã chốt và Apps Script

Mục này phân biệt rõ hành vi đang có trong mã cũ và hành vi cần triển khai theo quyết định mới. Không được coi các quyết định mới là chức năng đã tồn tại trong Apps Script.

| Phân hệ | Hiện trạng đọc được từ mã nguồn | Nghiệp vụ đích đã chốt / việc cần làm |
|---|---|---|
| PL1 | Có Valid From/Valid Until trong dữ liệu quyền; chưa thấy trạng thái EXPIRED hoặc scheduler tự chuyển trạng thái khi hết hạn. Token phê duyệt mặc định 7 ngày, cấu hình bằng TOKEN_EXPIRY_DAYS. | Bổ sung xử lý hết hạn quyền theo khoảng thời gian; giữ lịch sử; phân biệt EXPIRED với REVOKED sau khi chốt quy tắc biên. |
| PL2 | Kiểm tra thời hạn duyệt khi mở/xử lý link theo Room_Approval_Timeout_Hours (mặc định 24h); đoạn mã rà soát chưa thể hiện scheduler tự chuyển PENDING sang EXPIRED hoặc email nhắc tại giờ thứ 12. OTP 6 số có thời hạn 10 phút sau khi email gửi thành công. | Bổ sung reminder 12h và job tự chuyển EXPIRED tại 24h; vô hiệu hóa token và ghi Audit Log; không tự duyệt. |
| PL3 | Người nhận theo mã hiện tại: cấp 1 MSTAR, cấp 2 Approver, cấp 3 Manager. Delay hiện được tính nối tiếp từ lúc gửi cấp trước. | Giữ nguyên ánh xạ người nhận hiện tại. Admin cấu hình động deadline, khoảng trễ/mốc gửi từng cấp và cách tính mốc; không hard-code. Dừng escalation khi có phiếu hợp lệ. |
| PL4 | Mã hiện tại dùng PENDING_APPROVAL; Approver chọn APPROVE/REJECT; APPROVE đóng ISSUE, REJECT chuyển REJECTED. | Thay bằng Assignee báo cáo COMPLETED/NOT_COMPLETED; cả hai chờ UOA xác nhận; UOA xác nhận đóng, không xác nhận trả IN_PROGRESS. Bổ sung lịch sử báo cáo/xác minh. |
| PL5 | Dashboard home hiển thị số liệu và tối đa 100 phiếu gần nhất; trong luồng doGet đang đọc, action home gọi trực tiếp dashboard, chưa thấy kiểm tra thành viên UOA/MSTAR tại điểm vào Dashboard. | Giữ read-only; bổ sung xác thực và phân quyền nhóm UOA/MSTAR ở phía server. Không chỉ ẩn nút trên giao diện. |

### 12.1. Các lưu ý triển khai bắt buộc

1. PL1: TOKEN_EXPIRY_DAYS (mặc định 7 ngày) là thời hạn link phê duyệt, không được nhầm với Valid Until của quyền truy cập.
2. PL2: 24 giờ hiện có là timeout kiểm tra link duyệt theo từng phòng; chức năng tự chuyển trạng thái EXPIRED và reminder 12 giờ là phần nghiệp vụ mới đã chốt.
3. PL3: Không sao chép nguyên mảng recipients hoặc phép tính delay từ Apps Script cũ; phải áp dụng ánh xạ và thời điểm đã chốt.
4. PL4: Không giữ trạng thái PENDING_APPROVAL/REJECTED làm luồng nghiệm thu mới, trừ khi cần lưu dữ liệu lịch sử di trú; phải lưu dấu vết các quyết định cũ.
5. PL5: Phân quyền UOA/MSTAR phải kiểm tra ở backend trước khi trả Dashboard và dữ liệu chi tiết; người ngoài hai nhóm không được truy cập.

**Trạng thái rà soát:** Đã xác định được các sai khác lớn nêu trên. Các câu hỏi Q01, Q02, Q04, Q06, Q08 và Q10 vẫn cần được giải quyết/đối chiếu trước khi thiết kế ERD và migration. Q05 được chốt theo nguyên tắc cấu hình động trên Admin. Chưa thay đổi Schema hoặc viết nghiệp vụ PHP.

| D12 | Xác nhận lại người nhận cảnh báo PL3 | ĐÃ CHỐT: giữ nguyên theo Apps Script hiện tại — cấp 1 MSTAR, cấp 2 Approver (người phụ trách phòng), cấp 3 Manager. Thay thế quyết định ánh xạ người nhận trước đó. |


---

## 13. Nguyên tắc cấu hình động trên Admin

**Quyết định đã chốt:** Các mốc thời gian và các tham số cấu hình nghiệp vụ phải được quản trị động trên giao diện Admin; không cố định (hard-code) các giá trị có thể thay đổi trong mã nguồn.

Phạm vi áp dụng bao gồm:
- Thời hạn và chu kỳ xử lý tự động của PL1, PL2, PL3, PL4, PL5.
- DAILY_DEADLINE, lịch làm việc, ngày nghỉ, bật/tắt cảnh báo, thời điểm hoặc khoảng trễ của từng cấp cảnh báo PL3.
- Thời hạn token, OTP, thời gian chờ duyệt, retry email, số lần thử và các ngưỡng xử lý.
- Các cờ bật/tắt chức năng và điều kiện tiếp nhận yêu cầu vốn là cấu hình trong Settings của Apps Script.
- Các cấu hình nghiệp vụ khác được xác định trong quá trình rà soát nguồn.

Yêu cầu quản trị:
1. Admin được xem và cập nhật cấu hình theo từng phân hệ; cấu hình có kiểu dữ liệu, đơn vị, giá trị hợp lệ và mô tả rõ ràng.
2. Các thay đổi cấu hình phải được lưu vết: người thay đổi, thời điểm, giá trị trước và sau.
3. Tác vụ nền phải đọc cấu hình đang có hiệu lực; không yêu cầu sửa code hoặc deploy lại khi thay đổi giá trị cấu hình thông thường.
4. Phải phân biệt cấu hình nghiệp vụ có thể chỉnh sửa với thông số kỹ thuật/secret chỉ dành cho quản trị hệ thống; không hiển thị secret nhạy cảm trên giao diện.
5. Cần xác định quyền Admin nào được thay đổi từng nhóm cấu hình trong thiết kế phân quyền; không mặc định mọi tài khoản đều có quyền này.

| D13 | Nguyên tắc cấu hình động | ĐÃ CHỐT: Các mốc thời gian và tham số cấu hình nghiệp vụ phải định nghĩa/thay đổi động trên Admin, có lưu Audit Log; không hard-code và không cần deploy lại cho thay đổi cấu hình thông thường. |


---

## 14. Danh mục cấu hình trích xuất từ Apps Script gốc

Danh mục dưới đây được trích từ năm file Apps Script đã cung cấp. Đây là **inventory nguồn hiện trạng**, không đồng nghĩa tất cả các khóa đều phải đưa nguyên xi lên giao diện Admin. Khi thiết kế PHP cần phân loại thành:
- Cấu hình nghiệp vụ: Admin được phép thay đổi theo quyền.
- Cấu hình vận hành: Admin/System Admin được thay đổi có kiểm soát.
- Bí mật và định danh triển khai: lưu trong môi trường bảo mật, không hiển thị giá trị secret trên Admin.
- Hằng số kỹ thuật: chỉ đưa lên Admin nếu có nhu cầu vận hành thực tế và có kiểm tra giới hạn; nếu không thì giữ là thông số nội bộ có tài liệu.

### 14.1. PL1 – Settings và Email Queue

| Khóa / tham số | Giá trị hiện tại đọc được từ mã | Phân loại / ghi chú |
|---|---:|---|
| TOKEN_EXPIRY_DAYS | Mặc định 7 ngày | Cấu hình nghiệp vụ; thời hạn link phê duyệt, không phải thời hạn quyền truy cập |
| SYSTEM_STATUS | ACTIVE / INACTIVE | Công tắc vận hành phân hệ |
| EMAIL_QUEUE batch size | 8 | Tham số vận hành queue |
| EMAIL_QUEUE max attempts | 12 | Tham số vận hành queue |
| EMAIL_QUEUE retry base | 5 phút | Tham số vận hành; backoff tăng theo cấp số nhân, giới hạn 6 giờ trong mã |
| EMAIL_QUEUE stale SENDING | 15 phút | Tham số khôi phục queue |
| EMAIL_QUEUE worker interval | 5 phút | Chu kỳ worker được cài trong trigger |
| WEB_APP_URL | Script Properties | URL triển khai; không phải cấu hình nghiệp vụ thông thường |

PL1 còn có Approval_Timeout_Hours theo từng phòng trong bảng Room Approvers. Đây là cấu hình theo từng phòng, cần giữ đúng phạm vi dữ liệu thay vì thay bằng một timeout chung.

### 14.2. PL2 – Settings và quy tắc OTP

| Khóa / tham số | Giá trị / hành vi hiện tại đọc được | Phân loại / ghi chú |
|---|---|---|
| PL1_SPREADSHEET_ID | Có giá trị mặc định trong mã, có thể đọc từ Settings | Định danh tích hợp; không cho người dùng nghiệp vụ tùy ý sửa |
| APPROVER_ENABLED | TRUE/FALSE | Công tắc tương thích/cấu hình phê duyệt cũ; luồng thực tế lấy Approver theo phòng từ PL1 |
| APPROVER_EMAIL / APPROVER_NAME | Cấu hình approver cũ | Cần đánh dấu legacy nếu không còn dùng trong luồng thực tế |
| APPROVAL_METHOD | Mặc định EMAIL; mã hiện chỉ hỗ trợ EMAIL | Cấu hình phương thức; không hiển thị lựa chọn chưa hỗ trợ |
| APPROVAL_TIMEOUT_HOURS | Mặc định 24 giờ trong hàm cấu hình approver cũ | Luồng thực tế lưu Room_Approval_Timeout_Hours lấy theo từng phòng PL1 |
| WEB_APP_URL | Settings hoặc URL runtime | Cấu hình triển khai |
| Room_Approval_Timeout_Hours | Theo từng phòng, đọc từ PL1 Room Approvers; mẫu mặc định 24 giờ | Cấu hình nghiệp vụ theo phòng |
| OTP length | 6 chữ số | Quy tắc bảo mật; không nên cho chỉnh tùy ý nếu chưa có đánh giá bảo mật |
| OTP validity | 10 phút sau khi email gửi thành công | Cấu hình nghiệp vụ/bảo mật có thể quản trị nếu đặt giới hạn an toàn |
| OTP max wrong attempts | 5 lần | Cấu hình bảo mật có thể quản trị trong giới hạn |
| Email worker interval | 5 phút theo mô tả triển khai PL2 | Tham số vận hành queue |
| Reminder at 12h / EXPIRED at 24h | Chưa có đầy đủ trong Apps Script cũ; đã chốt bổ sung | Cấu hình nghiệp vụ mới trên Admin |

Lưu ý: không được dùng APPROVAL_TIMEOUT_HOURS chung để ghi đè timeout riêng từng phòng nếu nguồn PL1 đang cung cấp timeout theo Room_ID.

### 14.3. PL3 – Settings

| Khóa | Giá trị hiện tại đọc được từ mã | Phân loại / ghi chú |
|---|---|---|
| SYSTEM_STATUS | INACTIVE mặc định khi tạo Settings | Công tắc vận hành |
| DAILY_DEADLINE | HH:mm, ban đầu để trống | Cấu hình nghiệp vụ |
| WORK_DAYS | MON,TUE,WED,THU,FRI | Cấu hình lịch làm việc |
| ALERT_LEVEL1_DELAY_MINUTES | Ban đầu để trống | Cấu hình thời điểm cấp 1 tính từ deadline |
| ALERT_LEVEL2_DELAY_MINUTES | Ban đầu để trống | Cấu hình khoảng trễ cấp 2 |
| ALERT_LEVEL3_DELAY_MINUTES | Ban đầu để trống | Cấu hình khoảng trễ cấp 3 |
| ALERT_CHECK_INTERVAL_MINUTES | Mặc định 5 phút | Chu kỳ scheduler; mã chỉ chấp nhận các mức 1, 5, 10, 15, 30 phút |
| FAIL_ALERT_ENABLED | TRUE | Bật/tắt thông báo khi checklist có FAIL |
| DAILY_ALERT_ENABLED | FALSE mặc định | Bật/tắt cảnh báo thiếu phiếu |
| EMAIL_SENDER_NAME | UOA Server Room Management | Tên hiển thị email |
| WEB_APP_URL | URL Web App | Cấu hình triển khai |
| Holidays | Bảng Holidays riêng | Ngày nghỉ dùng để loại trừ ngày không làm việc |

Người nhận cảnh báo theo quyết định D12: Cấp 1 MSTAR, Cấp 2 Approver, Cấp 3 Manager. Admin cần cấu hình/ánh xạ người nhận theo vai trò và từng phòng; không tự đổi thành người thực hiện kiểm tra.

### 14.4. PL4 – Settings

| Khóa | Giá trị hiện tại đọc được từ mã | Phân loại / ghi chú |
|---|---|---|
| SYSTEM_STATUS | ACTIVE | Công tắc vận hành |
| ALLOW_MANUAL_CREATION | TRUE | Cho phép tạo ISSUE thủ công từ PL4 Form |
| EMAIL_SENDER_NAME | UOA Server Room Management | Tên hiển thị email |
| PL1_SPREADSHEET_ID | ID nguồn PL1 | Định danh tích hợp; chuyển thành cấu hình liên kết hệ thống được bảo vệ |
| PL3_SPREADSHEET_ID | Script Properties | Định danh tích hợp PL3 |
| PL4_API_SECRET | Secret trong Script Properties | Bí mật tích hợp; tuyệt đối không hiển thị giá trị trong Admin hoặc log |
| PL4_WEB_APP_URL | Script Properties | URL triển khai |
| Email queue | Mã được rà soát dùng MailApp trực tiếp ở các luồng đã kiểm tra | Chưa có queue/retry thống nhất được xác nhận; cần xử lý trong thiết kế vận hành email |

### 14.5. PL5 – Work_Settings và Work Catalog

| Khóa / tham số | Giá trị hiện tại đọc được từ mã | Phân loại / ghi chú |
|---|---|---|
| SYSTEM_STATUS | ACTIVE | Công tắc vận hành |
| ALLOW_NEW_REQUESTS | TRUE | Bật/tắt nhận yêu cầu mới |
| EMAIL_SENDER_NAME | UOA Server Room Management | Tên hiển thị email |
| PL1_SPREADSHEET_ID | ID nguồn PL1 | Định danh tích hợp |
| PL5_WEB_APP_URL | Work_Settings / Script Properties | URL triển khai |
| Work_Catalog | Danh mục loại công việc, có cột mô tả, hướng dẫn/rủi ro và Is_Active | Danh mục nghiệp vụ cần quản trị trên Admin; không hard-code lựa chọn Form |

### 14.6. Nguyên tắc thiết kế System Configuration

1. Admin hiển thị cấu hình theo nhóm PL1, PL2, PL3, PL4, PL5, Email/Queue, Tích hợp và Lịch làm việc.
2. Cấu hình theo phòng (ví dụ Approval_Timeout_Hours, Approver, MSTAR, Manager) phải gắn Room_ID; không chuyển thành một giá trị toàn hệ thống nếu nghiệp vụ hiện tại là theo phòng.
3. Giá trị cấu hình phải có kiểu dữ liệu, đơn vị, min/max hoặc tập giá trị hợp lệ, giá trị mặc định, mô tả tác động và trạng thái đang áp dụng.
4. Với thời gian escalation, Admin phải cho chọn cách tính rõ ràng: tính từ deadline hay tính nối tiếp từ lần gửi cấp trước. Không suy luận hoặc hard-code một cách tính khi chưa có quyết định.
5. Khi đổi cấu hình, phải lưu phiên bản/giá trị trước-sau, người thay đổi, thời điểm, lý do thay đổi (nếu được yêu cầu) và hiệu lực áp dụng.
6. Secret, token ký, mật khẩu SMTP/API key và thông tin kết nối phải được quản lý riêng trong cấu hình bảo mật; giao diện chỉ cho phép cập nhật theo quy trình an toàn, không trả lại secret dạng rõ.
7. Thay đổi cấu hình thông thường có hiệu lực mà không cần deploy lại; các thay đổi ảnh hưởng trigger/scheduler phải được áp dụng an toàn và ghi Audit Log.
8. Không đưa giá trị mặc định minh họa hoặc legacy vào sản xuất nếu chưa kiểm tra Settings đang vận hành thực tế.

### 14.7. Những thông tin chưa được kết luận từ mã nguồn

- Không phải mọi Settings PL2 đều còn được dùng trong luồng ENTRY thực tế; một số khóa APPROVER_* là cấu hình tương thích/kiểm tra cũ.
- Chưa xác nhận toàn bộ giá trị Settings hiện đang triển khai thực tế khác với giá trị seed/default trong Apps Script.
- Cần kiểm tra trigger đang cài thực tế ở từng project trước khi quyết định chu kỳ scheduler cuối cùng.
- PL4 chưa được xác nhận có email queue/retry; không được giả định đã có.
- Giá trị 12h reminder và 24h EXPIRED của PL2 là quyết định mới, không phải hành vi đầy đủ sẵn có trong mã cũ.


---

## 15. Mô hình vai trò và phân quyền (ĐÃ CHỐT NGUYÊN TẮC)

### 15.1. Nguyên tắc

Chỉ **Super Admin** được quyền quản trị và thay đổi cấu hình hệ thống. Các vai trò nghiệp vụ không được tự thay đổi Settings, danh mục, biểu mẫu, quy trình hoặc phân quyền.

Tách biệt quyền quản trị với quyền nghiệp vụ:
- Super Admin: quản trị hệ thống và cấu hình.
- UOA: thực hiện phê duyệt/xác nhận nghiệp vụ và xem báo cáo.
- MSTAR: chỉ xem báo cáo; không có quyền phê duyệt hoặc thay đổi dữ liệu nghiệp vụ.
- Requester / Executor / Assignee: không phải vai trò quản trị Admin; chỉ thực hiện các thao tác nghiệp vụ được quy định qua Public Form hoặc liên kết xác thực gắn với hồ sơ cụ thể.

### 15.2. Vai trò và quyền

| Vai trò | Quyền được phép |
|---|---|
| Super Admin | Quản lý tài khoản, vai trò và phân quyền; cấu hình toàn hệ thống; quản lý Settings, danh mục, biểu mẫu và tham số PL1–PL5; cấu hình tích hợp/secret theo quy trình bảo mật; xem System Audit Log. |
| UOA | Phê duyệt/xác nhận các bước nghiệp vụ được quy định trong PL1–PL5; xem báo cáo và Dashboard. Không được thay đổi cấu hình, danh mục, biểu mẫu hoặc phân quyền hệ thống. |
| MSTAR | Chỉ xem báo cáo và PL5 Dashboard read-only trên phạm vi toàn bộ phòng Server. Không được tạo/sửa dữ liệu, phê duyệt, xác nhận, phân công hoặc thay đổi cấu hình. |
| Requester / Executor / Assignee | Thực hiện đúng hành động được cho phép trên hồ sơ cụ thể thông qua Public Form hoặc token/link xác thực theo từng quy trình; không có quyền truy cập quản trị Admin. |

### 15.3. Ma trận quyền tóm tắt

| Chức năng | Super Admin | UOA | MSTAR |
|---|---|---|---|
| Quản lý tài khoản, vai trò, phân quyền | Có | Không | Không |
| Thay đổi Settings PL1–PL5 | Có | Không | Không |
| Quản lý Work Catalog, Checklist và danh mục nghiệp vụ | Có | Không | Không |
| Thay đổi biểu mẫu và quy trình | Có | Không | Không |
| PL1/PL2 phê duyệt yêu cầu | Theo quyền quản trị; không mặc định thay người duyệt nghiệp vụ | Có theo vai trò/người duyệt được cấu hình | Không |
| PL3 xác nhận tiếp nhận | Theo quyền quản trị; không mặc định thay người xác nhận nghiệp vụ | Có | Không |
| PL4 xác nhận kết quả ISSUE | Theo quyền quản trị; không mặc định thay UOA nghiệm thu | Có | Không |
| PL5 phê duyệt yêu cầu / xác minh hoàn thành | Theo quyền quản trị; không mặc định thay người xử lý nghiệp vụ | Có | Không |
| Xem báo cáo PL1–PL5 | Có | Có | Có – chỉ xem |
| PL5 Dashboard toàn bộ phòng Server | Có | Có – read-only | Có – read-only |
| Xem System Audit Log | Có | Không | Không |

**Nguyên tắc quan trọng:** Quyền Super Admin là quyền quản trị kỹ thuật/cấu hình, không tự động thay thế người phê duyệt hoặc xác nhận nghiệp vụ. Mọi can thiệp đặc biệt cần cơ chế riêng, lý do bắt buộc và Audit Log; không được âm thầm tạo quyết định nghiệp vụ bằng quyền quản trị.

### 15.4. Kiểm soát quyền

1. Mọi quyền UOA phê duyệt/xác nhận phải được kiểm tra ở Backend theo đúng bước quy trình và người được phân công; nếu bước nghiệp vụ có phạm vi phòng Server thì phải kiểm tra theo Room_ID.
2. UOA được xem báo cáo theo phạm vi báo cáo được cấp; riêng PL5 Dashboard được xem toàn bộ phòng Server ở chế độ read-only theo D17.
3. MSTAR chỉ có quyền xem báo cáo; riêng PL5 Dashboard được xem toàn bộ phòng Server ở chế độ read-only theo D17.
4. MSTAR nhận email cảnh báo PL3 cấp 1 theo quyết định D12. Đây là thông báo theo quy trình, không cấp quyền phê duyệt hoặc chỉnh sửa dữ liệu.
5. Mọi thao tác quản trị cấu hình, thay đổi vai trò, phân quyền, quyết định nghiệp vụ và truy cập dữ liệu nhạy cảm phải được ghi Audit Log tương ứng.
6. Không dựa vào việc ẩn nút giao diện; mọi quyền phải được kiểm tra ở Backend/API.
7. Public Form và token/link chỉ cho phép các hành động được quy định rõ cho từng hồ sơ; không được coi là quyền Admin hoặc quyền xem báo cáo toàn hệ thống.

**Trạng thái:** Nguyên tắc vai trò và quyền Super Admin / UOA / MSTAR đã được chủ nghiệp vụ chốt. Chi tiết kỹ thuật về ma trận quyền từng hành động sẽ được đặc tả nhất quán theo các luồng PL1–PL5 trước khi triển khai.

## 16. Public Form và cơ chế phê duyệt đa kênh

### 16.1. Public Form cho PL1–PL5

**Nguyên tắc đã thống nhất:** Cả năm Form PL1–PL5 có thể được công khai bằng đường dẫn để người dùng tự truy cập, nhập thông tin và gửi đăng ký/yêu cầu. Việc Form có thể Public không đồng nghĩa người gửi được cấp tài khoản hệ thống hoặc có quyền xử lý nghiệp vụ.

Yêu cầu thiết kế:
1. Public Form chỉ cung cấp các thao tác tiếp nhận được quy định cho từng phân hệ; không cấp quyền phê duyệt, quản trị hoặc xem dữ liệu của người khác.
2. Dữ liệu gửi từ Public Form phải được Backend kiểm tra hợp lệ, kiểm tra trạng thái phân hệ, danh mục tham chiếu và điều kiện nghiệp vụ trước khi tạo hồ sơ.
3. Có biện pháp chống spam/bot, giới hạn tần suất, chống gửi trùng và ghi nhận nguồn tiếp nhận.
4. Khi nghiệp vụ yêu cầu xác minh người gửi hoặc người thực hiện, sử dụng OTP/token đúng phạm vi và thời hạn; không mặc định email được nhập trong Form là đã xác minh.
5. Liên kết tra cứu hoặc thực hiện bước tiếp theo phải gắn với hồ sơ cụ thể, có xác thực và không làm lộ dữ liệu hồ sơ khác.
6. Mọi hành động nhạy cảm tiếp theo vẫn phải tuân thủ RBAC, trạng thái quy trình và Audit Log.

### 16.2. Approval Engine thống nhất

**Quyết định đã chốt:** Bổ sung khả năng phê duyệt trực tiếp trên Admin, đồng thời tiếp tục duy trì phê duyệt qua Email. Hai kênh là hai giao diện thực hiện trên cùng một Approval Engine; không xây dựng hai luồng phê duyệt độc lập.

- **Email Approval:** Người có quyền nhận email, mở liên kết và thực hiện quyết định theo cơ chế xác thực/token đã quy định.
- **Admin Approval:** Người có quyền đăng nhập Admin, mở danh sách hồ sơ chờ xử lý, xem chi tiết và thực hiện quyết định.
- Cả hai kênh phải gọi chung quy tắc nghiệp vụ và cập nhật cùng một trạng thái hồ sơ.

Các điều kiện bắt buộc:
1. Kiểm tra quyền người phê duyệt, vai trò, phạm vi phòng Server và trạng thái hồ sơ ở Backend tại thời điểm quyết định.
2. Mỗi hồ sơ chỉ được ghi nhận một quyết định hợp lệ cho cùng một bước phê duyệt; thao tác đồng thời hoặc quyết định lặp phải bị từ chối an toàn.
3. Nếu Email được xử lý trước, Admin phải phản ánh ngay trạng thái mới; nếu Admin được xử lý trước, liên kết Email cũ phải mất hiệu lực và không thể quyết định lần hai.
4. Ghi nhận người quyết định, thời điểm, kết quả, ý kiến, kênh thực hiện và thông tin Audit Log.
5. Giữ nguyên các điều kiện xác thực đặc thù của từng quy trình, bao gồm token, OTP hoặc xác minh email khi được yêu cầu. Đăng nhập Admin không tự động miễn trừ các điều kiện nghiệp vụ khác.
6. Không áp dụng nút Approve/Reject máy móc cho các bước có ngữ nghĩa khác nhau. PL3 xác nhận tiếp nhận; PL4 UOA xác nhận kết quả ISSUE; PL5 có bước phê duyệt yêu cầu và bước xác minh hoàn thành riêng.
7. Thông báo email là kênh thông báo/điểm truy cập; lỗi gửi email không được làm sai lệch hoặc đảo ngược quyết định nghiệp vụ đã được ghi nhận thành công.

### 16.3. Trung tâm phê duyệt trên Admin

Admin cần có khu vực Approval Center để người dùng được phân quyền:
- Xem danh sách hồ sơ đang chờ mình xử lý.
- Lọc theo phân hệ, phòng Server, trạng thái và thời gian.
- Mở chi tiết hồ sơ và thông tin liên quan cần thiết cho quyết định.
- Thực hiện đúng hành động của bước quy trình tương ứng; bắt buộc nhập lý do/ghi chú ở những bước nghiệp vụ yêu cầu.
- Xem lịch sử quyết định và kênh đã xử lý.

Danh sách và số liệu hiển thị phải được truy vấn theo quyền Backend; không được tải dữ liệu ngoài phạm vi rồi chỉ ẩn bằng giao diện.

| D16 | Public Form và Approval Engine | ĐÃ CHỐT: PL1–PL5 có thể cung cấp Form Public để người dùng tự gửi đăng ký/yêu cầu. Bổ sung phê duyệt trên Admin bên cạnh Email; hai kênh dùng chung Approval Engine, chung trạng thái và kiểm soát quyền, chống quyết định trùng, ghi Audit Log. |


| D17 | Phạm vi xem PL5 Dashboard | ĐÃ CHỐT: UOA và MSTAR được xem toàn bộ danh sách và chi tiết công việc của tất cả phòng Server vì Dashboard chỉ có quyền xem (read-only). Không giới hạn theo phòng/đơn vị; không phát sinh quyền chỉnh sửa hay xử lý nghiệp vụ. |


| D18 | Mô hình vai trò quản trị và nghiệp vụ | ĐÃ CHỐT: Chỉ Super Admin được thay đổi cấu hình hệ thống, Settings, danh mục nghiệp vụ, biểu mẫu, quy trình và phân quyền. UOA được phê duyệt/xác nhận nghiệp vụ và xem báo cáo. MSTAR chỉ xem báo cáo; không có quyền chỉnh sửa hoặc phê duyệt. MSTAR vẫn nhận email cảnh báo PL3 cấp 1 theo D12, đây là thông báo chứ không phải quyền thao tác. |
