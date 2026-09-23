@echo off
REM ========================================================
REM  เปิด POS ในโหมดพิมพ์อัตโนมัติ (ไม่เด้งกล่องยืนยันพิมพ์)
REM  ใช้ profile แยก จะได้ไม่กระทบ Chrome ปกติที่เปิดอยู่
REM  ตั้งเครื่องปรินท์สลิปเป็น Default Printer ของ Windows ก่อนใช้งาน
REM ========================================================

set CHROME="C:\Program Files\Google\Chrome\Application\chrome.exe"
set URL=https://posconsert.test/pos
set PROFILE=%LOCALAPPDATA%\PosKioskProfile

if not exist %CHROME% (
    echo ไม่พบ Chrome ที่ %CHROME%
    echo กรุณาแก้ path ในไฟล์นี้ให้ตรงกับเครื่อง
    pause
    exit /b 1
)

start "" %CHROME% --kiosk-printing --user-data-dir="%PROFILE%" --no-first-run --disable-features=PrintCompositorLPAC %URL%
