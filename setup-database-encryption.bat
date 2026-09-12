@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Generic SQL API - Database Configuration Encryption Setup

echo.
echo ============================================================
echo   Generic SQL API - Database Configuration Encryption Setup
echo ============================================================
echo.

REM ------------------------------------------------------------
REM Paths
REM ------------------------------------------------------------

set "ROOT=%~dp0"

set "PHP=%ROOT%runtime\windows\php\php.exe"
set "PHP_INI=%ROOT%runtime\windows\php\php.ini"

set "GENERATE_KEY=%ROOT%scripts\generate-encryption-key.php"
set "SETUP_ENCRYPTION=%ROOT%scripts\setup-database-encryption.php"
set "DB_CHECK=%ROOT%scripts\check-database.php"

set "DB_CONFIG=%ROOT%database\config\database.json"

set "TEMP_DIR=%TEMP%\generic-sql-api-encryption"
set "KEY_OUTPUT=%TEMP_DIR%\key.txt"

REM ------------------------------------------------------------
REM Check bundled PHP
REM ------------------------------------------------------------

echo [INFO] Checking bundled PHP...

if not exist "%PHP%" (
    echo [FAILED] Bundled PHP was not found:
    echo         %PHP%
    goto :fail
)

if not exist "%PHP_INI%" (
    echo [FAILED] php.ini was not found:
    echo         %PHP_INI%
    goto :fail
)

echo [OK] Bundled PHP found.
echo.

REM ------------------------------------------------------------
REM Check database configuration
REM ------------------------------------------------------------

echo [INFO] Checking database configuration...

if not exist "%DB_CONFIG%" (
    echo [FAILED] database.json was not found:
    echo         %DB_CONFIG%
    goto :fail
)

echo [OK] database.json found.
echo.

REM ------------------------------------------------------------
REM Check setup scripts
REM ------------------------------------------------------------

echo [INFO] Checking encryption scripts...

if not exist "%GENERATE_KEY%" (
    echo [FAILED] Missing:
    echo         %GENERATE_KEY%
    goto :fail
)

if not exist "%SETUP_ENCRYPTION%" (
    echo [FAILED] Missing:
    echo         %SETUP_ENCRYPTION%
    goto :fail
)

echo [OK] Encryption scripts found.
echo.

REM ------------------------------------------------------------
REM Check OpenSSL
REM ------------------------------------------------------------

echo [INFO] Checking PHP OpenSSL extension...

"%PHP%" -c "%PHP_INI%" -m | findstr /I "openssl" >nul

if errorlevel 1 (
    echo [FAILED] PHP OpenSSL extension is not enabled.
    echo.
    echo Check:
    echo     %ROOT%runtime\windows\php\ext\php_openssl.dll
    echo     %PHP_INI%
    goto :fail
)

echo [OK] OpenSSL extension is enabled.
echo.

REM ------------------------------------------------------------
REM Confirm plaintext state before generating a new key
REM ------------------------------------------------------------

echo [INFO] Checking database configuration encryption state...

"%PHP%" -c "%PHP_INI%" "%SETUP_ENCRYPTION%" --check-plaintext

if errorlevel 1 (
    echo [FAILED] Database configuration is not eligible for encryption.
    goto :fail
)

echo [OK] Plaintext database configuration found.
echo.

REM ------------------------------------------------------------
REM Create temporary directory
REM ------------------------------------------------------------

if not exist "%TEMP_DIR%" (
    mkdir "%TEMP_DIR%" >nul 2>&1
)

if not exist "%TEMP_DIR%" (
    echo [FAILED] Unable to create temporary directory.
    goto :fail
)

REM ------------------------------------------------------------
REM Generate encryption key
REM ------------------------------------------------------------

echo [INFO] Generating encryption key...

del /q "%KEY_OUTPUT%" >nul 2>&1

"%PHP%" -c "%PHP_INI%" "%GENERATE_KEY%" > "%KEY_OUTPUT%"

if errorlevel 1 (
    echo [FAILED] Unable to generate encryption key.
    goto :cleanup_fail
)

if not exist "%KEY_OUTPUT%" (
    echo [FAILED] Encryption key was not generated.
    goto :cleanup_fail
)

set "KEY_LINE="
set /p "KEY_LINE="<"%KEY_OUTPUT%"

if "!KEY_LINE!"=="" (
    echo [FAILED] Generated encryption key is empty.
    goto :cleanup_fail
)

REM Expected:
REM GENERIC_SQL_API_ENCRYPTION_KEY=xxxxxxxx

if /I not "!KEY_LINE:~0,31!"=="GENERIC_SQL_API_ENCRYPTION_KEY=" (
    echo [FAILED] Invalid encryption key output.
    goto :cleanup_fail
)

set "ENCRYPTION_KEY=!KEY_LINE:~31!"

if "!ENCRYPTION_KEY!"=="" (
    echo [FAILED] Encryption key value is empty.
    goto :cleanup_fail
)

echo [OK] Encryption key generated.
echo.

REM ------------------------------------------------------------
REM Set key for current process
REM ------------------------------------------------------------

set "GENERIC_SQL_API_ENCRYPTION_KEY=!ENCRYPTION_KEY!"

REM ------------------------------------------------------------
REM Persist key for future Windows processes
REM ------------------------------------------------------------

echo [INFO] Saving encryption key to Windows User environment...

powershell.exe -NoProfile -Command ^
    "[Environment]::SetEnvironmentVariable('GENERIC_SQL_API_ENCRYPTION_KEY', $env:GENERIC_SQL_API_ENCRYPTION_KEY, 'User')"

if errorlevel 1 (
    echo [FAILED] Unable to save encryption key.
    goto :cleanup_fail
)

echo [OK] Encryption key saved.
echo.

REM ------------------------------------------------------------
REM Encrypt complete database configuration
REM ------------------------------------------------------------

echo ============================================================
echo   Encrypting Database Configuration
echo ============================================================
echo.

echo [INFO] Reading database.json...
echo [INFO] Encrypting complete configuration using AES-256-GCM...
echo.

"%PHP%" -c "%PHP_INI%" "%SETUP_ENCRYPTION%"

if errorlevel 1 (
    echo.
    echo [FAILED] Unable to encrypt database configuration.
    goto :cleanup_fail
)

echo.
echo [OK] Complete database configuration encrypted successfully.
echo [OK] database.json updated successfully.
echo.

REM ------------------------------------------------------------
REM Test database connection
REM ------------------------------------------------------------

echo ============================================================
echo   Testing Database Connection
echo ============================================================
echo.

if not exist "%DB_CHECK%" (
    echo [FAILED] Database check script was not found:
    echo         %DB_CHECK%
    goto :cleanup_fail
)

"%PHP%" -c "%PHP_INI%" "%DB_CHECK%"

if errorlevel 1 (
    echo.
    echo ============================================================
    echo   DATABASE CONNECTION TEST FAILED
    echo ============================================================
    echo.
    echo The configuration was encrypted, but the database connection
    echo test failed.
    echo.
    goto :cleanup_fail
)

echo.
echo ============================================================
echo   SUCCESS
echo ============================================================
echo.
echo Complete database configuration encryption is configured successfully.
echo.
echo Encrypted configuration:
echo     %DB_CONFIG%
echo.
echo Encryption key:
echo     GENERIC_SQL_API_ENCRYPTION_KEY
echo.
echo Open a new CMD window before starting the API so the saved
echo environment variable is available to new processes.
echo.
echo ============================================================
echo.

goto :cleanup_success


REM ============================================================
REM SUCCESS CLEANUP
REM ============================================================

:cleanup_success

del /q "%KEY_OUTPUT%" >nul 2>&1
rmdir "%TEMP_DIR%" >nul 2>&1

echo [OK] Temporary files cleaned up.
echo.

pause
exit /b 0


REM ============================================================
REM FAILURE CLEANUP
REM ============================================================

:cleanup_fail

del /q "%KEY_OUTPUT%" >nul 2>&1
rmdir "%TEMP_DIR%" >nul 2>&1

echo.
echo ============================================================
echo   SETUP FAILED
echo ============================================================
echo.
pause
exit /b 1


REM ============================================================
REM GENERAL FAILURE
REM ============================================================

:fail

echo.
echo ============================================================
echo   SETUP FAILED
echo ============================================================
echo.

pause
exit /b 1
