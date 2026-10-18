MSTAR PC AUDIT TOOL - WINDOWS 7 (NODE.JS)
=========================================

Goi nay duoc tao tu trang PC Audit Launcher va gan Audit Code cua chi nhanh da chon.

YEU CAU
- Windows 7 SP1 x86 hoac x64.
- Node.js 12.22.12 dung kien truc (x86 cho Windows 32-bit, x64 cho Windows 64-bit).
- Ket noi HTTPS/TLS 1.2 toi may chu Audit.
- Nen chay bang quyen Administrator de WMI/Registry doc duoc nhieu thong tin hon.

CAI NODE.JS
Neu da cai Node.js, chay node -v va kiem tra phien ban. Neu chua co, tai MSI chinh thuc:
  x64: https://nodejs.org/dist/v12.22.12/node-v12.22.12-x64.msi
  x86: https://nodejs.org/dist/v12.22.12/node-v12.22.12-x86.msi
Chay MSI, dong/mo lai CMD, kiem tra node -v phai hien v12.22.12.

CHAY AUDIT
1. De PC_Audit.js va PC_Audit.bat trong cung thu muc.
2. Chay PC_Audit.bat.
3. Nhap thong tin nguoi su dung va xac nhan.
4. JSON duoc ghi truoc khi gui len Server vao thu muc output.
5. Neu gui that bai, giu file JSON de IT kiem tra.

LUU Y: Node.js 12 da het ho tro bao mat; chi dung de tuong thich Windows 7 legacy. Mot so truong nhu Secure Boot hoac serial RAM co the khong duoc firmware/may ao cung cap.
