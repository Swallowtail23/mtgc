@echo off
setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
for %%I in ("%SCRIPT_DIR%.") do set "SCRIPT_DIR=%%~fI\"
for %%I in ("%SCRIPT_DIR%..") do set "PROJECT_ROOT=%%~fI"
set "COMPOSE_FILE=%SCRIPT_DIR%docker-compose.yml"
set "ENV_FILE=%SCRIPT_DIR%.env"

call :DetectDocker
call :DetectCompose

echo [INFO] Using compose command: %COMPOSE_CMD%
echo [INFO] Using container command: docker

set /p BASE_PARENT="Enter base directory for card images/config/logs (e.g. C:\\data - recommend 25-40GB): "
if "%BASE_PARENT%"=="" (
    echo [ERROR] Base directory is required. Aborting.
    exit /b 1
)

set /p WEB_PORT="Enter port for the web UI (e.g. 8082): "
if "%WEB_PORT%"=="" set WEB_PORT=8082

if "%BASE_PARENT:~-1%"=="\" set "BASE_PARENT=%BASE_PARENT:~0,-1%"
set "BASE_DIR=%BASE_PARENT%\mtgc"

call :EnsureDir "%BASE_DIR%\cardimg"
call :EnsureDir "%BASE_DIR%\config"
call :EnsureDir "%BASE_DIR%\logs"

call :WriteEnv "%ENV_FILE%"

set DO_DB_SETUP=1
for /f "tokens=*" %%I in ('docker volume ls --format {{.Name}} ^| findstr /I "mtgc_db-data"') do set DO_DB_SETUP=0
if %DO_DB_SETUP%==1 (
    echo Fresh install detected ^- generating mtg_new.ini from template...
    copy /Y "%PROJECT_ROOT%\setup\mtg_new.ini" "%BASE_DIR%\config\mtg_new.ini" >nul
    call :SanitizeIni "%BASE_DIR%\config\mtg_new.ini"
) else (
    echo Existing install detected ^- keeping mtg_new.ini unchanged.
)

if not exist "%BASE_DIR%\config\php_custom.ini" (
    echo Creating php config file from template...
    copy /Y "%PROJECT_ROOT%\setup\php_custom.ini" "%BASE_DIR%\config\php_custom.ini" >nul
)

call :CopyScripts

call :GetComposeValue MYSQL_DATABASE DB_NAME
call :GetComposeValue MYSQL_USER DB_USER
call :GetComposeValue MYSQL_PASSWORD DB_PASS
set DB_SERVER=db
call :UpdateIni "%BASE_DIR%\config\mtg_new.ini" "%DB_SERVER%" "%DB_USER%" "%DB_PASS%" "%DB_NAME%"

pushd "%SCRIPT_DIR%"
    %COMPOSE_CMD% up --build -d
popd

echo Waiting for MySQL to be available...
call :WaitForMySQL

call :SetContainerPerms

if %DO_DB_SETUP%==1 (
    call :RunUserSetup
) else (
    echo MySQL is available. Skipping user/admin setup ^- database volume already exists.
    set /p RERUN="Do you want to re-run user setup anyway? (y/N): "
    if /I "%RERUN%"=="Y" call :RunUserSetup
)

call :RunBulkIfNeeded
call :FinalizeConfigPerms
call :ClearMaintenance

echo ✅ Setup complete. You can now log in via http://localhost:%WEB_PORT%
exit /b 0

:DetectDocker
where docker >nul 2>&1 || (
    echo [ERROR] Docker CLI not found in PATH.
    exit /b 1
)
exit /b 0

:DetectCompose
docker compose version >nul 2>&1
if not errorlevel 1 (
    set "COMPOSE_CMD=docker compose"
) else (
    where docker-compose >nul 2>&1 || (
        echo [ERROR] Neither "docker compose" nor docker-compose is available.
        exit /b 1
    )
    set "COMPOSE_CMD=docker-compose"
)
exit /b 0

:EnsureDir
if not exist %1 (
    mkdir %1 >nul
)
exit /b 0

:WriteEnv
> %1 echo BASE_DIR=%BASE_DIR%
>> %1 echo WEB_PORT=%WEB_PORT%
exit /b 0

:SanitizeIni
powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-Content '%~1') ^| %% { ($_ -replace '\s+//.*$', '').TrimEnd() } ^| Set-Content '%~1'"
exit /b 0

:CopyScripts
set "SCRIPTS_DEST=%BASE_DIR%\config\scripts"
if not exist "%SCRIPTS_DEST%" mkdir "%SCRIPTS_DEST%"
for %%F in ("%PROJECT_ROOT%\setup\*.sh") do (
    if not exist "%SCRIPTS_DEST%\%%~nxF" copy /Y "%%~F" "%SCRIPTS_DEST%\%%~nxF" >nul
)
for %%F in ("%SCRIPTS_DEST%\*.sh") do attrib -R "%%~F"
exit /b 0

:GetComposeValue
for /f "usebackq delims=" %%V in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-Content '%COMPOSE_FILE%') ^| Where-Object { \$_ -match '%1' } ^| Select-Object -First 1 ^| ForEach-Object { (\$_ -split ':')[1].Trim().Trim(''\"'') }"`) do set "%2=%%V"
exit /b 0

:UpdateIni
powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-Content '%~1') ^| ForEach-Object { \$_ -replace '^DBServer\s*=.*', 'DBServer    = \"%~2\"' } ^| ForEach-Object { \$_ -replace '^DBUser\s*=.*', 'DBUser      = \"%~3\"' } ^| ForEach-Object { \$_ -replace '^DBPass\s*=.*', 'DBPass      = \"%~4\"' } ^| ForEach-Object { \$_ -replace '^DBName\s*=.*', 'DBName      = \"%~5\"' } ^| Set-Content '%~1'"
exit /b 0

:WaitForMySQL
:waitloop
docker exec mtgc_web_1 mysqladmin ping -h"db" --silent >nul 2>&1 && goto :waitdone
echo MySQL is not available yet. Waiting...
timeout /t 2 >nul
goto waitloop
:waitdone
exit /b 0

:SetContainerPerms
docker exec mtgc_web_1 bash -c "chown -R www-data:www-data /mnt/data/cardimg /var/log/mtg && chmod -R u+rwX /mnt/data/cardimg /var/log/mtg"
exit /b 0

:RunUserSetup
echo Starting initial DB setup...
docker exec mtgc_db_1 mysql -u root -prootpass -e "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 1) ON DUPLICATE KEY UPDATE mtce=1;"
docker exec mtgc_db_1 mysql -u root -prootpass -e "TRUNCATE TABLE mtg_new.users;"
set /p ADMIN_EMAIL="Enter email address for admin user: "
set /p ADMIN_USER="Enter desired username (display only): "
set /p ADMIN_PASS="Enter password: "
powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-Content '%BASE_DIR%\config\mtg_new.ini') ^| %% { \$_ -replace '^AdminEmail\s*=.*', 'AdminEmail     = \"%ADMIN_EMAIL%\"' } ^| Set-Content '%BASE_DIR%\config\mtg_new.ini'"
for /f "usebackq tokens=2 delims=:" %%H in (`docker exec mtgc_web_1 php /var/www/mtgnew/setup/initial.php "%ADMIN_USER%" "%ADMIN_PASS%" ^| findstr /C:"Hashed password:"`) do set "HASHED=%%H"
set "HASHED=%HASHED: =%"
docker exec mtgc_db_1 mysql -u root -prootpass -e "INSERT INTO mtg_new.users (username, email, password, reg_date, status) VALUES ('%ADMIN_USER%', '%ADMIN_EMAIL%', '%HASHED%', NOW(), 'active');"
docker exec mtgc_db_1 mysql -u root -prootpass -e "UPDATE mtg_new.users SET admin=1 WHERE username='%ADMIN_USER%';"
docker exec mtgc_db_1 mysql -u root -prootpass -e "INSERT INTO mtg_new.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"
exit /b 0

:RunBulkIfNeeded
docker exec mtgc_web_1 bash -c "test -f /var/log/mtg/scryfall_import_done" >nul 2>&1 && (
    echo Bulk import already completed previously - skipping.
    exit /b 0
)
echo Running bulk Scryfall import - this may take up to 2 hours...
docker exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
docker exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
docker exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
docker exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"
docker exec mtgc_web_1 bash -c "printf 'done\n' > /var/log/mtg/scryfall_import_done"
exit /b 0

:FinalizeConfigPerms
docker exec mtgc_web_1 bash -c "chown -R www-data:www-data /mnt/data/config && chmod -R u+rwX /mnt/data/config"
exit /b 0

:ClearMaintenance
docker exec mtgc_db_1 mysql -u root -prootpass -e "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"
exit /b 0
