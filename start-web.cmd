@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-local.ps1"
if errorlevel 1 (
    echo Mo Laragon va bam Start All, sau do thu lai.
    pause
    exit /b 1
)
start "" "http://bdu-media.localhost"