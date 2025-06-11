@echo off
setlocal

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

endlocal
