@echo off
REM ==================================================================
REM  QueueEase - start the project with PHP's built-in web server.
REM  Uses port 8080, so it works even when XAMPP Apache (port 80)
REM  is busy or is already running another website.
REM  MySQL must be running in the XAMPP Control Panel.
REM ==================================================================
cd /d "%~dp0"

set "PHP_EXE="
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "D:\xampp\php\php.exe" set "PHP_EXE=D:\xampp\php\php.exe"
if not defined PHP_EXE (
    where php >nul 2>nul && set "PHP_EXE=php"
)
if not defined PHP_EXE (
    echo.
    echo  Could not find PHP.
    echo  Install XAMPP to C:\xampp, or open this file in Notepad and
    echo  change the line   set "PHP_EXE="   to the full path of php.exe.
    echo.
    pause
    exit /b 1
)

echo.
echo  Starting QueueEase at http://localhost:8080
echo  Keep this window open while you use the system.
echo  Press Ctrl+C (or close this window) to stop it.
echo.
start "" http://localhost:8080
"%PHP_EXE%" -S localhost:8080 -t . router.php
pause
