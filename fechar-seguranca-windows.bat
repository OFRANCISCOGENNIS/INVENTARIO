@echo off
REM ============================================================
REM  Fecha o aplicativo "Seguranca do Windows"
REM  Tenta varios metodos para garantir o encerramento
REM ============================================================

REM --- Solicita permissao de administrador ---
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Solicitando permissao de administrador...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

echo Fechando a Seguranca do Windows...
echo.

REM --- Metodo 1: pelo nome do processo (interface do app) ---
taskkill /IM SecHealthUI.exe /F >nul 2>&1

REM --- Metodo 2: pela janela com o titulo em portugues ---
taskkill /FI "WINDOWTITLE eq Seguranca do Windows*" /F >nul 2>&1

REM --- Metodo 3: pela janela com o titulo em ingles ---
taskkill /FI "WINDOWTITLE eq Windows Security*" /F >nul 2>&1

REM --- Verifica se ainda esta em execucao ---
tasklist /FI "IMAGENAME eq SecHealthUI.exe" 2>nul | find /I "SecHealthUI.exe" >nul
if %ERRORLEVEL%==0 (
    echo AVISO: O aplicativo ainda pode estar em execucao.
    echo Tente fechar manualmente ou reinicie o computador.
) else (
    echo Aplicativo encerrado com sucesso.
)

echo.
pause
