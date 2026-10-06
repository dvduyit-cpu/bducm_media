$ErrorActionPreference = 'Stop'
$taskRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
Push-Location $taskRoot
try {
    if (!(Get-Process laragon -ErrorAction SilentlyContinue)) { throw 'Open Laragon and click Start All first.' }
    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Laravel configuration check failed.' }
    & php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'MySQL is not ready. In Laragon, click Start All.' }
    $taskStatus = & curl.exe --noproxy '*' --silent --output NUL --write-out '%{http_code}' 'http://bdu-media.localhost/login'
    if ($taskStatus -ne '200') { throw 'Apache is not ready. In Laragon, click Start All or Reload.' }
    Write-Host 'BDU Media: http://bdu-media.localhost'
    Write-Host 'MySQL Laragon: 127.0.0.1:3306 / bdu_cm_media'
} finally { Pop-Location }