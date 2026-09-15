@echo off
title DPR Excel Watcher

echo =========================================
echo Starting DPR Excel Watcher
echo =========================================
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\agri_ceo_dashboard_be\watch-dpr.ps1"

echo.
echo =========================================
echo Watcher stopped.
echo =========================================
pause