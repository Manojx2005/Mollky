@echo off
REM Molkky app launcher. Portable: uses the bundled PHP so no install is needed.
REM Just share this whole folder; double-click to run. Ctrl+C stops the server.
setlocal
cd /d "%~dp0"

set "PORT=8000"
set "URL=http://localhost:%PORT%/home.php"

REM 1) Prefer the bundled PHP (php\php.exe) shipped with this folder.
REM 2) Otherwise fall back to a PHP found on the system PATH.
set "PHP_EXE="
set "PHP_ARGS="
if exist "%~dp0php\php.exe" (
    set "PHP_EXE=%~dp0php\php.exe"
    REM Force an absolute extension_dir so SQLite loads wherever this folder lives.
    set "PHP_ARGS=-c "%~dp0php\php.ini" -d "extension_dir=%~dp0php\ext""
) else (
    where php >nul 2>nul && set "PHP_EXE=php"
)

if not defined PHP_EXE (
    echo.
    echo [!] PHP was not found.
    echo     This copy is missing its bundled "php" folder, and no PHP is installed.
    echo     See README for how to add a portable PHP.
    echo.
    pause
    exit /b 1
)

echo Starting Molkky app on %URL%
echo Using PHP: %PHP_EXE%
echo Press Ctrl+C in this window to stop the server.
echo.

REM Open the browser on the home page, then start the server.
start "" "%URL%"
"%PHP_EXE%" %PHP_ARGS% -S 127.0.0.1:%PORT% -t "%~dp0"
endlocal
