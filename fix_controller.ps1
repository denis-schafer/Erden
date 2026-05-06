# Fix syntax errors in MercadoPagoController.php
$file = "C:\laragon\www\erden\app\Http\Controllers\Pos\MercadoPagoController.php"
$content = Get-Content $file -Raw

# Fix 1: Add missing comma after $authUrl in Http::post calls
$content = $content -replace 'Http::asForm\(\)->post\(\$authUrl, \$requestData\)', 'Http::asForm()->post($authUrl, $requestData)'
$content = $content -replace 'Http::post\(\$authUrl, \$requestData\)', 'Http::post($authUrl, $requestData)'

# Fix 2: Add missing commas in arrays
$content = $content -replace "=> \$accessToken, 'type'", "=> `$accessToken, 'type'"
$content = $content -replace "=> \$expiresAt->toDateTimeString\(\), 'type'", "=> `$expiresAt->toDateTimeString(), 'type'"

Set-Content $file -Value $content -NoNewline
Write-Host "Fixed syntax errors"
