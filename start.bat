@echo off
setlocal enabledelayedexpansion
title QuizOnline Launcher
color 0B

echo.
echo  ========================================
echo          QuizOnline Starting...
echo  ========================================
echo.

cd /d "%~dp0"

:: 1. Check if PHP is available
set "PHP_BIN="
where php >nul 2>&1
if not errorlevel 1 (
    set "PHP_BIN=php"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else if exist "D:\xampp\php\php.exe" (
    set "PHP_BIN=D:\xampp\php\php.exe"
) else if exist "C:\php\php.exe" (
    set "PHP_BIN=C:\php\php.exe"
)

if "%PHP_BIN%"=="" (
    color 0C
    echo [ERROR] PHP was not found!
    echo.
    echo Please make sure XAMPP is installed in C:\xampp
    echo or copy this folder into C:\xampp\htdocs\quizonline
    echo.
    pause
    exit /b 1
)

:: 2. Check and start MySQL if XAMPP is present
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    if exist "C:\xampp\mysql_start.bat" (
        echo [1/2] Starting MySQL database from XAMPP...
        start "QuizOnline MySQL" /MIN cmd /c ""C:\xampp\mysql_start.bat""
        timeout /t 3 /nobreak >NUL
    ) else if exist "D:\xampp\mysql_start.bat" (
        echo [1/2] Starting MySQL database from XAMPP...
        start "QuizOnline MySQL" /MIN cmd /c ""D:\xampp\mysql_start.bat""
        timeout /t 3 /nobreak >NUL
    ) else (
        echo [NOTE] Please make sure MySQL is started in XAMPP!
    )
) else (
    echo [1/2] MySQL database is already running.
)

:: 3. Start PHP development server on localhost:8000
set "PORT=8000"
echo [2/2] Starting QuizOnline on http://localhost:%PORT%...
start "QuizOnline PHP Server" /MIN "%PHP_BIN%" -S localhost:%PORT%

timeout /t 2 /nobreak >NUL

:: 4. Open in browser
echo.
echo Opening QuizOnline in your browser...
start "" "http://localhost:%PORT%/frontend/login.html"

echo.
echo ========================================================
echo  Website is running at: http://localhost:%PORT%/frontend/login.html
echo.
echo  Demo Logins:
echo   - Admin:   admin@gmail.com   / admin
echo   - Teacher: teacher@gmail.com / teacher@
echo   - Student: student@gmail.com / student@
echo.
echo  Keep this window open while using QuizOnline.
echo ========================================================
echo.
pause
