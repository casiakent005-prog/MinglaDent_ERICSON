@echo off
setlocal
rem Run locally from Windows/XAMPP; this file does not expose a web migration endpoint.
set "REGISTRATION_PHP="
if exist "%~dp0..\..\..\php\php.exe" set "REGISTRATION_PHP=%~dp0..\..\..\php\php.exe"
if not defined REGISTRATION_PHP if exist "C:\xampp\php\php.exe" set "REGISTRATION_PHP=C:\xampp\php\php.exe"
if not defined REGISTRATION_PHP for %%I in (php.exe) do set "REGISTRATION_PHP=%%~$PATH:I"
if not defined REGISTRATION_PHP (
    echo PHP was not found. Run migrate_registration.php with your XAMPP php.exe.
    if /i not "%~1"=="--no-pause" pause
    exit /b 1
)
echo Applying the registration database upgrade. Existing accounts and records are preserved.
echo Use the same database environment settings as Apache. Back up your database before upgrading.
"%REGISTRATION_PHP%" "%~dp0migrate_registration.php"
set "REGISTRATION_RESULT=%ERRORLEVEL%"
if not "%REGISTRATION_RESULT%"=="0" (
    echo Upgrade stopped. Keep the error above for troubleshooting; do not run setup.php.
) else (
    echo Upgrade complete. Reload the registration page and submit your application again.
)
if /i not "%~1"=="--no-pause" pause
exit /b %REGISTRATION_RESULT%
