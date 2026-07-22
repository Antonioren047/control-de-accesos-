[CmdletBinding()]
param(
    [string]$OutputDirectory = '',
    [string]$PackageName = 'control-de-accesos-cpanel-produccion',
    [string]$VendorSource = ''
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $root '..\..\outputs'
}
$output = [System.IO.Path]::GetFullPath($OutputDirectory)
$buildRoot = Join-Path $output '.release-build'
$stage = Join-Path $buildRoot $PackageName
$zip = Join-Path $output ($PackageName + '.zip')
$checksum = $zip + '.sha256.txt'

if (Test-Path -LiteralPath $buildRoot) {
    Remove-Item -LiteralPath $buildRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $stage -Force | Out-Null
New-Item -ItemType Directory -Path $output -Force | Out-Null

$excludedDirectories = @('.git', 'tests', 'storage', 'vendor')
$excludedFiles = @('.env', 'phpunit.xml', 'installed.lock')

Get-ChildItem -LiteralPath $root -Force | ForEach-Object {
    if ($_.PSIsContainer -and $_.Name -in $excludedDirectories) {
        return
    }
    if (-not $_.PSIsContainer -and $_.Name -in $excludedFiles) {
        return
    }
    Copy-Item -LiteralPath $_.FullName -Destination $stage -Recurse -Force
}

# Las migraciones contienen solo estructura. Los seeds 003-009 son exclusivamente demos
# y no forman parte del artefacto productivo.
Get-ChildItem -LiteralPath (Join-Path $stage 'database\seeds') -Filter '*.php' |
    Where-Object { $_.Name -notin @('001_catalogs.php', '002_auth_permissions.php') } |
    Remove-Item -Force

Remove-Item -LiteralPath (Join-Path $stage 'scripts\seed.php') -Force -ErrorAction SilentlyContinue

foreach ($relative in @('storage', 'storage\cache', 'storage\cache\sessions', 'storage\evidence', 'storage\logs', 'storage\temp')) {
    New-Item -ItemType Directory -Path (Join-Path $stage $relative) -Force | Out-Null
}
Copy-Item -LiteralPath (Join-Path $root 'storage\.htaccess') -Destination (Join-Path $stage 'storage\.htaccess') -Force
foreach ($relative in @('storage\cache', 'storage\cache\sessions', 'storage\evidence', 'storage\logs', 'storage\temp')) {
    New-Item -ItemType File -Path (Join-Path $stage ($relative + '\.gitkeep')) -Force | Out-Null
}

if ([string]::IsNullOrWhiteSpace($VendorSource)) {
    $vendorCandidates = @(
        (Join-Path $root 'vendor'),
        'C:\xampp-8.1\htdocs\control-de-accesos\vendor'
    )
    $VendorSource = $vendorCandidates |
        Where-Object {
            (Test-Path -LiteralPath (Join-Path $_ 'dompdf\dompdf')) -and
            (Test-Path -LiteralPath (Join-Path $_ 'endroid\qr-code'))
        } |
        Select-Object -First 1
}
if ([string]::IsNullOrWhiteSpace($VendorSource) -or -not (Test-Path -LiteralPath $VendorSource)) {
    throw 'No se encontró un directorio vendor completo con Dompdf y Endroid QR Code.'
}
Copy-Item -LiteralPath $VendorSource -Destination (Join-Path $stage 'vendor') -Recurse -Force

$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) {
    throw 'Composer no está disponible para construir vendor de producción.'
}

Push-Location $stage
try {
    & $composer.Source install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) {
        throw "Composer terminó con código $LASTEXITCODE."
    }
} finally {
    Pop-Location
}

$release = @"
Sistema de Vigilancia - paquete cPanel de producción
Generado (UTC): $([DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ'))

- Dependencias PHP de producción incluidas.
- Sin archivo .env ni bloqueo de instalación.
- Sin sesiones, logs, evidencias o datos locales.
- Sin seeds de demostración.
- El instalador exige una base vacía y crea un único usuario global.

Consulta docs/INSTALACION-CPANEL.md antes de subir el paquete.
"@
Set-Content -LiteralPath (Join-Path $stage 'RELEASE.txt') -Value $release -Encoding UTF8

$required = @(
    'vendor\autoload.php',
    'vendor\dompdf\dompdf',
    'vendor\endroid\qr-code',
    'public\install\index.php',
    'docs\INSTALACION-CPANEL.md',
    'database\seeds\001_catalogs.php',
    'database\seeds\002_auth_permissions.php',
    'storage\.htaccess'
)
foreach ($relative in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $stage $relative))) {
        throw "Falta un componente requerido en el paquete: $relative"
    }
}

$forbidden = @('.env', 'storage\installed.lock', 'tests', 'phpunit.xml', 'database\seeds\003_phase3_demo.php')
foreach ($relative in $forbidden) {
    if (Test-Path -LiteralPath (Join-Path $stage $relative)) {
        throw "El paquete contiene un componente prohibido: $relative"
    }
}

if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}
$tar = Get-Command tar.exe -ErrorAction SilentlyContinue
if (-not $tar) {
    throw 'No se encontró tar.exe para crear un ZIP compatible con cPanel/Linux.'
}
& $tar.Source -a -cf $zip -C $stage .
if ($LASTEXITCODE -ne 0) {
    throw "No fue posible crear el ZIP; tar terminó con código $LASTEXITCODE."
}

$hash = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath $checksum -Value "$hash  $([System.IO.Path]::GetFileName($zip))" -Encoding ASCII

Remove-Item -LiteralPath $buildRoot -Recurse -Force

[pscustomobject]@{
    Package = $zip
    Checksum = $checksum
    Sha256 = $hash
}
