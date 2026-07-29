@echo off
REM ============================================================
REM  Fecha o aplicativo "Seguranca do Windows" (usuario normal)
REM ============================================================

echo Fechando a Seguranca do Windows...

taskkill /IM SecHealthUI.exe /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq Seguranca do Windows*" /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq Windows Security*" /F >nul 2>&1

echo Concluido.
timeout /t 2 >nul
