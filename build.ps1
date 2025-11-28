# CS Sponsored Articles Plugin Build Script
# Creates a Joomla 5 installation package

$pluginName = "sponsoredarticles"
$pluginPrefix = "plg_system"

# Get version from manifest
[xml]$manifest = Get-Content "$pluginName.xml"
$version = $manifest.extension.version

Write-Host "Building CS Sponsored Articles Plugin v$version..." -ForegroundColor Green

# Clean up old packages
Remove-Item "${pluginPrefix}_${pluginName}_v*.zip" -ErrorAction SilentlyContinue

# Function to create ZIP with forward slashes (required for Joomla)
function New-ZipWithForwardSlashes {
    param($SourcePath, $DestinationPath)

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if (Test-Path $DestinationPath) { Remove-Item $DestinationPath -Force }

    $zip = [System.IO.Compression.ZipFile]::Open($DestinationPath, 'Create')

    try {
        Get-ChildItem -Path $SourcePath -Recurse -File | ForEach-Object {
            $relativePath = $_.FullName.Substring($SourcePath.Length + 1)
            $zipPath = $relativePath -replace '\\', '/'
            $entry = $zip.CreateEntry($zipPath)
            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($_.FullName)
            $fileStream.CopyTo($entryStream)
            $fileStream.Close()
            $entryStream.Close()
        }
    } finally {
        $zip.Dispose()
    }
}

# Create temp directory
if (Test-Path "temp_build") { Remove-Item "temp_build" -Recurse -Force }
New-Item -ItemType Directory -Path "temp_build" -Force | Out-Null

# Copy plugin files (exclude build artifacts and git)
$excludeItems = @(".git", ".claude", "temp_build", "*.zip", "build.ps1", "build.bat")
Get-ChildItem -Path "." -Exclude $excludeItems | ForEach-Object {
    if ($_.PSIsContainer) {
        Copy-Item $_.FullName -Destination "temp_build\$($_.Name)" -Recurse -Force
    } else {
        Copy-Item $_.FullName -Destination "temp_build\$($_.Name)" -Force
    }
}

# Create package
$packageName = "${pluginPrefix}_${pluginName}_v${version}.zip"
New-ZipWithForwardSlashes -SourcePath (Resolve-Path "temp_build").Path -DestinationPath $packageName

# Cleanup
Remove-Item "temp_build" -Recurse -Force

Write-Host "Package created: $packageName" -ForegroundColor Green

# Show file size
if (Test-Path $packageName) {
    $fileSize = [math]::Round((Get-Item $packageName).Length / 1KB, 2)
    Write-Host "Package size: $fileSize KB" -ForegroundColor Yellow
}
