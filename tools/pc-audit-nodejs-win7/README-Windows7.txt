MSTAR PC AUDIT TOOL - WINDOWS 7 (NODE.JS)
=========================================

Goi nay duoc tao tu trang PC Audit Launcher va gan Audit Code cua chi nhanh da chon.

YEU CAU
- Windows 7 SP1 x86 hoac x64.
- Chay PC_Audit.bat bang quyen Administrator.
- Can Internet de BAT tu tai Node.js 12.22.12 MSI tu nodejs.org neu chua cai.
- Can ket noi HTTPS/TLS 1.2 toi may chu Audit.

CAI NODE.JS TU DONG
1. Chay PC_Audit.bat bang Run as administrator.
2. BAT tu nhan dien Windows x86/x64, tai MSI chinh thuc va cai Node.js 12.22.12.
3. Neu Windows 7 khong tai duoc do chung chi/TLS, BAT se mo trang MSI chinh thuc de tai thu cong. Cai MSI, sau do chay lai PC_Audit.bat.
4. Neu Node.js 12.22.12 da cai, BAT bo qua cai dat va chay Audit.

CHAY AUDIT
- De PC_Audit.js va PC_Audit.bat trong cung thu muc.
- JSON duoc ghi truoc khi gui len Server vao thu muc output.
- Neu gui that bai, giu file JSON de IT kiem tra.

LUU Y BAO MAT
Node.js 12 da het ho tro bao mat; chi dung cho may Windows 7 legacy. BAT chi tai MSI tu URL chinh thuc va khong bo qua kiem tra chung chi. Mot so truong nhu Secure Boot hoac serial RAM co the khong duoc firmware/may ao cung cap.
