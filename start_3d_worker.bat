@echo off
cd /d "%~dp0"

set "KND_DB_HOST=srv1710.hstgr.io"
set "KND_DB_USER=u354862096_lilshoot"
set "KND_DB_PASS=Ce140501."
set "KND_DB_NAME=u354862096_kndstore"

set "COMFYUI_3D_URL=http://127.0.0.1:8188"
set "COMFYUI_3D_OUTPUT_ROOT=F:\KND\output"
set "COMFYUI_3D_INPUT_ROOT=C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable\ComfyUI\input"
set "LOCAL_3D_STAGING_DIR=F:\KND\output"
set "COMFYUI_3D_WORKFLOW_FAST=E:\repo\kndstore\comfy-router\workflows\generate fast 3d.json"
set "COMFYUI_3D_WORKFLOW_PREMIUM=E:\repo\kndstore\comfy-router\workflows\3d premium.json"

set "KND_3D_UPLOAD_TOKEN=KND_2026!Secure$Panel#92"
echo KND_3D_UPLOAD_TOKEN=[%KND_3D_UPLOAD_TOKEN%]

python workers\labs_3d_worker.py

pause