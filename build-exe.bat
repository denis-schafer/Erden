@echo off
chcp 65001 >nul
REM ==================================
REM  Generar ErdenPrintAgent.exe
REM ==================================
REM  Requisitos:
REM    1. Python 3 instalado (python.org)
REM    2. Ejecutar: pip install pyinstaller requests
REM ==================================

echo.
echo ==================================
echo  ErdenPrintAgent - Build .exe
echo ==================================
echo.

REM Verificar Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Python no encontrado. Instalalo desde https://python.org
    pause
    exit /b 1
)

REM Verificar PyInstaller
pip show pyinstaller >nul 2>&1
if %errorlevel% neq 0 (
    echo [*] Instalando PyInstaller...
    pip install pyinstaller requests
)

echo [*] Generando ErdenPrintAgent.exe...
python -m PyInstaller --onefile --name "ErdenPrintAgent" --console print-agent.py

if %errorlevel% equ 0 (
    echo.
    echo [+] EXITOSO!
    echo     Archivo: dist\ErdenPrintAgent.exe
    
    REM Copiar a storage para descarga local
    if not exist "storage\app\print-agent" mkdir "storage\app\print-agent"
    copy /Y "dist\ErdenPrintAgent.exe" "storage\app\print-agent\ErdenPrintAgent.exe" >nul
    echo     Copiado a: storage\app\print-agent\ErdenPrintAgent.exe
    echo.
    echo Para subir al VPS:
    echo   scp dist\ErdenPrintAgent.exe root@149.50.133.48:/var/www/html/erden/storage/app/print-agent/
    echo.
) else (
    echo [!] Error al generar el .exe
    echo.
    echo Posible causa: pyinstaller no encontrado.
    echo Solucion: ejecuta este comando y luego reintenta:
    echo   python -m pip install pyinstaller requests
)

pause
