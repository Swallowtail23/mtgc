@echo off
setlocal EnableDelayedExpansion

:: Ensure config mount directory exists
if not exist "opt\mtg" (
    mkdir "opt\mtg"
)

:: If the config file doesn't exist, copy from template
if not exist "opt\mtg\mtg_new.ini" (
    echo Creating editable config file from template...
    copy "setup\mtg_new.ini" "opt\mtg\mtg_new.ini"
)

:: Run Docker Compose
docker-compose up --build -d

:: Wait for DB container to be ready
echo Waiting for MySQL to become available...
:waitloop
docker exec mtgc-web-1 /opt/mtg/wait-for-mysql.sh db >nul 2>&1
if errorlevel 1 (
    timeout /t 2 >nul
    goto waitloop
)

:: Set DB into mtce mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=1 WHERE `key`=1;"

:: Run bulk scripts
echo Running bulk scripts...
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"

:: Prompt for user creation
set /p newuser=Enter username (for display/logging only): 
set /p email=Enter email (used for login): 
set /p password=Enter password:

:: Generate password hash using initial.php
for /f "tokens=*" %%i in ('docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "!newuser!" "!password!"') do (
    set "line=%%i"
    echo !line!
    echo !line! | find "Hashed password:" >nul
    if !errorlevel! == 0 (
        for /f "tokens=3" %%j in ("!line!") do set "HASH=%%j"
    )
)

:: Insert user into DB
docker exec mtgc-db-1 mysql -u root -prootpass -D mtg -e "INSERT INTO users (username, email, password, reg_date, status) VALUES ('!newuser!', '!email!', '!HASH!', NOW(), 1);"

:: Promote to admin
docker exec mtgc-db-1 mysql -u root -prootpass -D mtg -e "UPDATE users SET admin=1 WHERE email='!email!';"

:: Exit mtce mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=0 WHERE `key`=1;"

echo Setup complete. You can now access the application.
endlocal
pause
