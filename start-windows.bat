@echo off
setlocal

set "ROOT=%~dp0"
set "PHP=%ROOT%runtime\windows\php\php.exe"
set "PHP_INI=%ROOT%runtime\windows\php\php.ini"
set "OPCACHE=%ROOT%runtime\windows\php\opcache"
set "LOGS=%ROOT%logs"
set "API=%ROOT%api"
set "DB_CHECK=%ROOT%scripts\check-database.php"

echo ========================================
echo          Generic SQL API
echo ========================================
echo.

REM ============================================================
REM PHP Runtime
REM ============================================================

if not exist "%PHP%" (
    echo [FAILED] PHP runtime not found.
    echo Expected: %PHP%
    echo.
    pause
    exit /b 1
)

if not exist "%PHP_INI%" (
    echo [FAILED] php.ini not found.
    echo Expected: %PHP_INI%
    echo.
    pause
    exit /b 1
)

echo [OK] PHP Runtime
echo [OK] PHP Configuration
echo.

REM ============================================================
REM Create Runtime Directories
REM ============================================================

if not exist "%OPCACHE%" (
    mkdir "%OPCACHE%"
)

if not exist "%LOGS%" (
    mkdir "%LOGS%"
)

echo [OK] Runtime Directories
echo.

REM ============================================================
REM PHP ODBC Extension
REM ============================================================

"%PHP%" -c "%PHP_INI%" -m | findstr /i "odbc" >nul

if errorlevel 1 (
    echo [FAILED] PHP ODBC extension not available.
    echo.
    echo Please check the PHP ODBC configuration.
    echo.
    echo API startup aborted.
    pause
    exit /b 1
)

echo [OK] PHP ODBC
echo.

REM ============================================================
REM Database Connection
REM ============================================================

if not exist "%DB_CHECK%" (
    echo [FAILED] Database check script not found.
    echo Expected: %DB_CHECK%
    echo.
    echo API startup aborted.
    pause
    exit /b 1
)

echo Checking database connection...

"%PHP%" ^
    -c "%PHP_INI%" ^
    -d "error_log=%LOGS%\php_errors.log" ^
    "%DB_CHECK%"

set "DB_STATUS=%ERRORLEVEL%"

if not "%DB_STATUS%"=="0" (
    echo.
    echo [FAILED] Database connection failed.
    echo.
    echo Please configure:
    echo database/config/database.json
    echo.
    echo Refer to the documentation for database configuration.
    echo.

    powershell -NoProfile -Command ^
        "$wshell = New-Object -ComObject WScript.Shell; $wshell.Popup('Database connection failed.`n`nPlease configure database.json and refer to the documentation.', 0, 'Generic SQL API', 16)"

    echo.
    echo API startup aborted.
    pause
    exit /b 1
)

echo.
echo [OK] Database Connected
echo.

REM ============================================================
REM API Directory
REM ============================================================

echo Checking API directory...

if not exist "%API%\" (
    echo [FAILED] API directory not found.
    echo Expected: %API%
    echo.
    echo API startup aborted.
    pause
    exit /b 1
)

echo [OK] API Directory
echo.

REM ============================================================
REM Find Available Port
REM ============================================================

set "PORT=8000"
set "MAX_PORT=8100"

echo Checking available port...

:CHECK_PORT

netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul

if errorlevel 1 (
    echo [OK] Port %PORT% Available
    echo.
    goto START_API
)

echo [INFO] Port %PORT% is already in use.

set /a PORT+=1

if %PORT% GTR %MAX_PORT% (
    echo.
    echo [FAILED] No available port found between 8000 and 8100.
    echo.
    echo API startup aborted.
    pause
    exit /b 1
)

goto CHECK_PORT


REM ============================================================
REM Start API
REM ============================================================

:START_API

echo ========================================
echo              API Ready
echo ========================================
echo.
echo API: http://localhost:%PORT%
echo.
echo Starting API...
echo.

"%PHP%" ^
    -c "%PHP_INI%" ^
    -d "opcache.file_cache=%OPCACHE%" ^
    -d "error_log=%LOGS%\php_errors.log" ^
    -S localhost:%PORT% ^
    -t "%API%"

echo.
echo ========================================
echo              API Stopped
echo ========================================
echo.

pause