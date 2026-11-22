# ADR-001 – Định danh hồ sơ nghiệp vụ dùng chung cho Audit và Evidence

**Phiên bản:** 0.1 – Đề xuất chờ phê duyệt  
**Phạm vi:** CORE, PL1–PL5, COMMON  
**Trạng thái:** PROPOSED – CHƯA ĐƯỢC PHÊ DUYỆT  
**CSDL mục tiêu:** MySQL 8.0 / InnoDB

## 1. Bối cảnh

Các bảng `system_audit_logs` và `entity_evidence_links` cần liên kết tới nhiều loại hồ sơ thuộc PL1–PL5. MySQL không hỗ trợ một cột FK có thể tham chiếu luân phiên tới nhiều bảng đích.

ERD vật lý v0.1 hiện dùng tham chiếu polymorphic gồm `module + entity_type + entity_id`; tính hợp lệ của hồ sơ đích do service layer kiểm tra. Cần chọn cách tổ chức để bảo đảm Audit và Evidence luôn liên kết đúng hồ sơ, đồng thời không làm mất khả năng mở rộng.

## 2. Các phương án

### Phương án A – Polymorphic reference + service validation

Cấu trúc tham chiếu:
- `module_code`
- `entity_type`
- `entity_id`

**Ưu điểm**
- Ít bảng và ít thay đổi các bảng nghiệp vụ hiện tại.
- Dễ bổ sung loại hồ sơ mới.
- Cấu trúc đơn giản, phù hợp nếu chấp nhận kiểm soát toàn bộ ở tầng ứng dụng.

**Nhược điểm**
- Không có FK từ Audit/Evidence đến hồ sơ nghiệp vụ.
- Có nguy cơ liên kết mồ côi nếu code xóa/sửa sai hoặc có luồng ghi dữ liệu bỏ qua service chuẩn.
- Kiểm tra tính toàn vẹn phải được thực hiện nhất quán ở mọi luồng ghi.

### Phương án B – Registry độc lập, các bảng nghiệp vụ giữ PK riêng

Tạo `business_entities` để Audit/Evidence tham chiếu, nhưng mỗi bảng PL vẫn giữ khóa chính riêng và registry lưu cặp `entity_type + entity_pk`.

**Ưu điểm**
- Audit/Evidence có FK tới registry.
- Có danh mục định danh chung để tìm kiếm và tổng hợp.

**Nhược điểm**
- Registry vẫn phải lưu khóa ngoại dạng dữ liệu; DB không thể tạo FK từ registry tới nhiều bảng PL.
- Vì vậy, đây chưa phải mô hình bảo đảm đầy đủ tính toàn vẹn tham chiếu ở DB.
- Vẫn cần service validation và transaction.

### Phương án C – Supertype/Subtype dùng chung (khuyến nghị)

Tạo `business_entities` làm bảng cha định danh. Mỗi hồ sơ nghiệp vụ PL có một dòng trong bảng cha và một dòng tương ứng trong bảng con.

Mô hình khái niệm:
- `business_entities.id` là khóa định danh chung.
- Mỗi bảng hồ sơ nghiệp vụ có `business_entity_id` vừa là PK vừa là FK tới `business_entities.id`.
- `system_audit_logs.business_entity_id` là FK tới `business_entities.id`, cho phép NULL đối với sự kiện hệ thống không gắn hồ sơ.
- `entity_evidence_links.business_entity_id` là FK tới `business_entities.id`.
- Không dùng cặp `module + entity_type + entity_id` làm khóa liên kết chính nữa; có thể giữ module/type để lọc hoặc phân loại nếu cần.

**Ưu điểm**
- Audit và Evidence liên kết bằng FK tới một định danh thống nhất.
- Các bảng PL vẫn giữ cấu trúc dữ liệu riêng, không phải gom mọi thuộc tính vào một bảng lớn.
- Hỗ trợ truy vết xuyên module và mở rộng thêm loại hồ sơ.
- Xóa hồ sơ có thể bị chặn bởi FK hoặc được kiểm soát bằng quy trình lưu trữ; phù hợp nguyên tắc không xóa lịch sử nghiệp vụ.

**Nhược điểm / điều kiện**
- Phải tạo định danh cha và hồ sơ con trong cùng transaction.
- FK bảo đảm hồ sơ con tham chiếu tới một dòng cha hợp lệ, nhưng FK thông thường không bảo đảm mỗi dòng cha luôn có đúng một dòng con. Quy tắc tạo đủ cặp cha-con phải được bảo đảm bằng service/transaction; có thể cân nhắc trigger nếu sau này có yêu cầu DB-level nghiêm ngặt.
- Tất cả bảng hồ sơ PL cần điều chỉnh PK/FK theo mô hình này; Migration phức tạp hơn và cần kế hoạch chuyển dữ liệu nếu đã có dữ liệu thật.

## 3. Khuyến nghị

Chọn **Phương án C – Supertype/Subtype** cho hệ thống UOA Server Room.

Lý do:
1. Hệ thống có nhiều module và yêu cầu Audit xuyên suốt PL1–PL5.
2. Evidence cần gắn chính xác vào hồ sơ và được bảo toàn theo lịch sử.
3. Hệ thống có yêu cầu kiểm soát truy cập và truy vết trách nhiệm; một định danh hồ sơ chung giúp thiết kế Audit Center thống nhất.
4. Chi phí thay đổi ERD trước Migration thấp hơn nhiều so với việc chuyển đổi sau khi đã có dữ liệu production.

## 4. Quy tắc thiết kế nếu được phê duyệt

1. Mỗi hồ sơ nghiệp vụ thuộc phạm vi Audit/Evidence phải có đúng một `business_entities` record.
2. Tạo `business_entities` và dòng hồ sơ PL tương ứng trong cùng transaction.
3. Nếu tạo hồ sơ PL thất bại, rollback cả dòng `business_entities`.
4. Audit event gắn hồ sơ phải lưu `business_entity_id`; sự kiện hệ thống không gắn hồ sơ được phép NULL.
5. Evidence link phải tham chiếu `business_entity_id` hợp lệ.
6. Không xóa cứng hồ sơ đã phát sinh Audit/Evidence; dùng trạng thái hoặc chính sách archive.
7. `module_code` và `entity_type` (nếu lưu) chỉ là thuộc tính phân loại, không thay thế FK.
8. Cần rà soát tất cả bảng PL1–PL5 trong Data Dictionary để thống nhất tên cột và quan hệ 1–1 trước khi tạo Migration.

## 5. Phạm vi ảnh hưởng cần cập nhật

Nếu được phê duyệt, cần cập nhật:
- `ERD_DESIGN.md`
- `DATA_DICTIONARY.md`
- `ERD_PHYSICAL.md`
- Sau đó mới thiết kế Migration theo thứ tự phụ thuộc.

Không cập nhật code PHP, schema.sql hoặc Migration trong bước quyết định kiến trúc này.

## 6. Trạng thái quyết định

**Chờ người phụ trách phê duyệt.**

- [ ] Phê duyệt Phương án C – Supertype/Subtype.
- [ ] Không phê duyệt; yêu cầu giữ Polymorphic + Service Validation.
- [ ] Yêu cầu chỉnh sửa trước khi quyết định.

Chỉ sau khi được phê duyệt mới cập nhật các tài liệu ERD/Data Dictionary theo quyết định này.
