# Script simple para probar el webhook de MercadoPago
# Uso: .\test_mp_webhook_simple.ps1

Write-Host "=== PRUEBA DE WEBHOOK ===" -ForegroundColor Green
Write-Host ""

# 1. Verificar que el endpoint responda
Write-Host "1. Verificando endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "https://shakira-peace-workshop-bidding.trycloudflare.com/mp/webhook" `
        -Method POST `
        -ContentType "application/json" `
        -Body '{"test":"1"}' `
        -UseBasicParsing `
        -ErrorAction SilentlyContinue
    
    if ($response.StatusCode -eq 200) {
        Write-Host "   ✅ Endpoint accesible (200 OK)" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️ Status: $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   ❌ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "   Verifica que cloudflared esté corriendo" -ForegroundColor Yellow
}

Write-Host ""

# 2. Instrucciones para probar con pago real
Write-Host "2. Para probar con un pago real:" -ForegroundColor Yellow
Write-Host "   a) Genera un QR desde el POS" -ForegroundColor Gray
Write-Host "   b) Paga con la app de MercadoPago" -ForegroundColor Gray
Write-Host "   c) Revisa los logs:" -ForegroundColor Gray
Write-Host "      Get-Content storage\logs\laravel.log -Tail 100 | Select-String 'MP Webhook'" -ForegroundColor Cyan

Write-Host ""

# 3. Formato del webhook que envía MercadoPago
Write-Host "3. El webhook debe llegar a:" -ForegroundColor Yellow
Write-Host "   https://shakira-peace-workshop-bidding.trycloudflare.com/mp/webhook?company_db=TU_DB" -ForegroundColor Cyan

Write-Host ""
Write-Host "=== FIN ===" -ForegroundColor Green
