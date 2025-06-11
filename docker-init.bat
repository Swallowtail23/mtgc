@echo off
setlocal enabledelayedexpansion

:: Prompt for base directory
set /p BASE_PARENT=Enter base directory for data/config/logs (e.g. C:\Users\name). A "mtgc" subfolder will be created: 

:: Validate input
if "%BASE_PARENT%"=="" (
    echo ❌ Base directory is required. Aborting.
    exit /b 1
)

:: Normalize slashes and append mtgc
set "BASE_PARENT=%BASE_PARENT:/=\%"
set "BASE_DIR=%BASE_PARENT%\mtgc"

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

:: Start Docker containers
docker-compose up --build -d

:: Wait for MySQL to become available
echo ⏳ Waiting for MySQL to be available...
:waitloop
docker exec mtgc-web-1 mysqladmin ping -h"db" --silent >nul 2>&1
if errorlevel 1 (
    timeout /t 2 >nul
    goto waitloop
)

:: Proceed with setup
echo ✅ MySQL is available. Starting initial setup...

:: Put DB into maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=1 WHERE `key`=1;"

:: Run bulk import scripts
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"

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
    "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"
docker exec mtgc-db-1 mysql -u root -prootpass -e ^
    "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"

echo ✅ Initial setup complete. You can now log in via http://localhost:8080

endlocal
