# CraftOffice Suite - Automatic WASM Rebuild Script (PowerShell)
# Compiles WordCraft, GridCraft, PdfCraft, and DeckCraft to WebAssembly and copies assets to craftoffice/editor/wasm/

$ErrorActionPreference = "Stop"

$env:PATH = "C:\Users\marcos_1728\.cargo\bin;" + ($env:PATH -split ';' | Where-Object { $_ -notlike '*coreutils*' }) -join ';'

$BaseDir = "C:\Users\marcos_1728\PhpstormProjects"
$TargetDir = "$BaseDir\craftoffice\editor\wasm"

if (-not (Test-Path $TargetDir)) {
    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Building WordCraft WASM..." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Set-Location "$BaseDir\wordcraft\apps\wordcraft-web"
trunk build --release
Copy-Item "..\..\dist\web\wordcraft-web-*.js" "$TargetDir\wordcraft-web.js" -Force
Copy-Item "..\..\dist\web\wordcraft-web-*_bg.wasm" "$TargetDir\wordcraft-web_bg.wasm" -Force

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Building GridCraft WASM..." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Set-Location "$BaseDir\gridcraft\apps\gridcraft-web"
trunk build --release
Copy-Item "..\..\dist\web\gridcraft-web-*.js" "$TargetDir\gridcraft-web.js" -Force
Copy-Item "..\..\dist\web\gridcraft-web-*_bg.wasm" "$TargetDir\gridcraft-web_bg.wasm" -Force

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Building PdfCraft WASM..." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Set-Location "$BaseDir\pdfcraft\apps\pdfcraft-web"
trunk build --release
Copy-Item "..\..\dist\web\pdfcraft-web-*.js" "$TargetDir\pdfcraft-web.js" -Force
Copy-Item "..\..\dist\web\pdfcraft-web-*_bg.wasm" "$TargetDir\pdfcraft-web_bg.wasm" -Force

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Building DeckCraft WASM..." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Set-Location "$BaseDir\deckcraft\apps\deckcraft-web"
trunk build --release
Copy-Item "..\..\dist\web\deckcraft-web-*.js" "$TargetDir\deckcraft-web.js" -Force
Copy-Item "..\..\dist\web\deckcraft-web-*_bg.wasm" "$TargetDir\deckcraft-web_bg.wasm" -Force

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " SUCCESS! All 4 WASM engines recompiled! " -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Set-Location "$BaseDir\craftoffice"
