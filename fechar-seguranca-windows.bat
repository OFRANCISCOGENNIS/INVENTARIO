@echo off
REM ============================================================
REM  Fecha o aplicativo "Seguranca do Windows"
REM  (Windows Security / SecHealthUI.exe)
REM ============================================================

echo Fechando a Seguranca do Windows...

REM Encerra a interface do aplicativo Seguranca do Windows
taskkill /IM SecHealthUI.exe /F >nul 2>&1

if %ERRORLEVEL%==0 (
    echo Aplicativo encerrado com sucesso.
) else (
    echo O aplicativo nao estava em execucao ou ja foi fechado.
)

echo.
pause
