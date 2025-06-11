# Ensure script runs from its own directory (i.e., project root)
Set-Location -Path $PSScriptRoot

# Ensure config directory exists
$iniDir = Join-Path -Path $PSScriptRoot -ChildPath "opt\mtg"
if (-Not (Test-Path $iniDir)) {
    New-Item -Path $iniDir -ItemType Directory -Force | Out-Null
}

# Copy template ini if it doesn't already exist
$src = Join-Path -Path $PSScriptRoot -ChildPath "setup\mtg_new.ini"
$dst = Join-Path -Path $iniDir -ChildPath "mtg_new.ini"

if (-Not (Test-Path $dst)) {
    Write-Host "Creating editable config file from template..."
    Copy-Item -Path $src -Destination $dst
}

# Start docker-compose
docker-compose up --build -d
