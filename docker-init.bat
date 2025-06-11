@echo off
setlocal enabledelayedexpansion

:: Prompt for base directory
set /p BASE_PARENT=Enter base directory for data/config/logs (e.g. C:\Users\name). A "mtgc" subfolder will be created: 

:: Validate input
if "%BASE_PARENT%"=="" (
    echo [ERROR] Base directory is required. Aborting.
    exit /b 1
)

:: Normalize slashes and append mtgc
set "BASE_PARENT=%BASE_PARENT:/=\%"
set "BASE_DIR=%BASE_PARENT%\mtgc"
set "MARKER_FILE=%BASE_DIR%\logs\.scryfall_import_done"

:: Create required directories
mkdir "%BASE_DIR%\cardimg"
mkdir "%BASE_DIR%\config"
mkdir "%BASE_DIR%\logs"

:: Write .env file
(
    echo BASE_DIR=%BASE_DIR%
) > .env

:: Copy placeholder config if not already present
if not exist "%BASE_DIR%\config\mtg_new.ini" (
    echo Creating editable config file from template...
    copy "setup\mtg_new.ini" "%BASE_DIR%\config\mtg_new.ini"
)

:: Set write permissions
attrib -R "%BASE_DIR%\config\mtg_new.ini"

:: Start Docker containers
docker-compose up --build -d

:: Check if db-data volume already exists
set DO_DB_SETUP=1
for /f "tokens=*" %%v in ('docker volume ls --format "{{.Name}}" ^| findstr /i "mtgc_db-data"') do (
    echo Existing DB volume found: %%v
    set DO_DB_SETUP=0
)

:: Wait for MySQL to become available
echo Waiting for MySQL to be available...
:waitloop
docker exec mtgc-web-1 mysqladmin ping -h"db" --silent >nul 2>&1
if errorlevel 1 (
    timeout /t 2 >nul
    goto waitloop
)

:: If new DB, do full setup
if "!DO_DB_SETUP!"=="1" (
    echo MySQL is available. Starting initial setup...

    :: Put DB into maintenance mode
    docker exec mtgc-db-1 mysql -u root -prootpass -e "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 1) ON DUPLICATE KEY UPDATE mtce=1;"

    :: Prompt for user info
    set /p email=Enter email address for admin user: 
    set /p username=Enter desired username (display only): 
    set /p password=Enter password: 

    :: Generate hashed password
    for /f "tokens=*" %%A in ('docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "%username%" "%password%"') do (
        echo %%A | findstr "Hashed password:" >nul
        if not errorlevel 1 (
            for /f "tokens=2 delims=:" %%H in ("%%A") do set hashed=%%H
        )
    )

    :: Trim spaces if any
    set hashed=%hashed: =%

    :: Insert user and admin records
    docker exec mtgc-db-1 mysql -u root -prootpass -e ^
        "INSERT INTO mtg.users (username, email, password, reg_date, status) VALUES ('%username%', '%email%', '%hashed%', NOW(), 'active');"
    docker exec mtgc-db-1 mysql -u root -prootpass -e ^
        "UPDATE mtg.users SET admin=1 WHERE username='%username%';"
    docker exec mtgc-db-1 mysql -u root -prootpass -e ^
        "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"

) else (
    echo MySQL is available. Skipping user/admin setup - database volume already exists.

    :: Backfill .scryfall_import_done if missing
    if not exist "%MARKER_FILE%" (
        echo Existing DB volume but no import marker - assuming import was already run.
        echo done > "%MARKER_FILE%"
    )
)

:: Run bulk import if not already done
if not exist "%MARKER_FILE%" (
    echo Running bulk Scryfall import - this may take up to 2 hours...
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"
    echo done > "%MARKER_FILE%"
) else (
    echo Bulk import already completed previously - skipping.
)

:: Clear DB maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"

echo Setup complete. You can now log in via http://localhost:8080

endlocal
