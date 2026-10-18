MSTAR PC AUDIT TOOL - WINDOWS 7 / 10 / 11 (NODE.JS)
===================================================

Mot bo PC_Audit.js dung chung cho Windows 7, Windows 10 va Windows 11.

KIEM TRA DUNG PHIEN BAN NODE.JS
- Windows 7: yeu cau chinh xac Node.js v12.22.12.
- Windows 10/11: yeu cau chinh xac Node.js v22.16.0.
- BAT chi bo qua cai dat neu node --version khop chinh xac phien ban yeu cau.
- Neu chua co Node.js hoac sai phien ban, BAT thu tai va cai MSI phu hop.
- Neu cai ban cu de hon khong thanh cong, go Node.js khong dung phien ban trong Control Panel, sau do chay lai BAT bang Run as administrator.

CAI DAT VA CHAY
1. Giai nen PC_Audit.js, PC_Audit.bat va README vao cung mot thu muc.
2. Chay PC_Audit.bat bang quyen Administrator.
3. Neu Windows 7 khong tai MSI do TLS/chung chi, BAT se mo URL MSI chinh thuc de cai thu cong.
4. JSON duoc luu vao thu muc output truoc khi gui len Audit Server.

CAP NHAT MAY DA TON TAI
- Server dung Audit Code + Ten may tinh (computer_name) lam khoa nhan dien ban ghi inventory hien tai.
- Chay Audit lai tren cung may va cung Audit Code se cap nhat ban ghi hien co, thay the cac thanh phan RAM, Storage, Monitor, Software... thay vi tao them mot ban ghi PC moi.
- Neu doi Audit Code/chi nhanh, Server co the xem day la mot phan bo khac.

YEU CAU
- Windows 7 SP1, Windows 10 hoac Windows 11.
- Internet/HTTPS toi may chu Audit.
- Quyen Administrator duoc khuyen nghi de doc WMI/Registry.

LUU Y BAO MAT
Node.js 12 da het ho tro bao mat va chi danh cho Windows 7 legacy. Mot so truong nhu Secure Boot hoac serial RAM co the khong duoc firmware/may ao cung cap.

CAU HINH CAP NHAT DINH KY LEN SERVER
- Chay PC_Audit.bat bang Run as administrator va chon [2] Cai dat lich tu dong gui Server.
- Nhap Audit Code, nguoi dung va thong tin lien he; chon chay moi ngay (nhap gio HH:MM) hoac moi gio (chon khoang 1-23 gio).
- Cong cu tao Windows Scheduled Task chay nen voi tai khoan SYSTEM; may can duoc bat va co ket noi mang tai thoi diem chay.
- Moi lan chay se validate Audit Code, thu thap lai du lieu, luu JSON trong output va gui cap nhat len Server. Cung Audit Code + ten may tinh se cap nhat ban ghi inventory hien tai.
- Log chay nen: output\scheduled.log. JSON: output\pc-audit-*.json.
- Chon [3] de go lich. Cau hinh lich co chua Audit Code, gioi han quyen truy cap thu muc cong cu va khong chia se thu muc nay cho nguoi khong duoc phep.
- Day la Windows Task Scheduler, khong phai Windows Service thuc thu. Khong chay duoc khi may tat; khi cai lich can Run as administrator.
