@echo off
cd /d "%~dp0"
set "COMFYUI_3D_URL=http://127.0.0.1:8188"
C:\xampp\php\php.exe workers\labs_worker.php --loop --sleep=2 --worker-id=PC1
pause