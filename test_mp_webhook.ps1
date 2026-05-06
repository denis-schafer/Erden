# Script para probar webhook de MercadoPago
# Uso: .\test_mp_webhook.ps1

Write-Host "=== PRUEBA DE WEBHOOK MERCADOPAGO ===" -ForegroundColor Green
Write-Host ""

# 1. Verificar que cloudflared esté tunelizando
Write-Host "1. Verificando conexión a cloudflared..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "https://shakira-peace-workshop-bidding.trycloudflare.com/mp/webhook" -Method POST -ContentType "application/json" -Body '{"test":"1"}' -UseBasicParsing -ErrorAction SilentlyContinue
    Write-Host "   ✅ Endpoint accesible (Status: $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "   ❌ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "   Verifica que cloudflared esté corriendo" -ForegroundColor Yellow
}

Write-Host ""

# 2. Simular un webhook (ajusta los valores con datos reales)
Write-Host "2. Para simular un webhook, usa este JSON en Insomnia/Postman:" -ForegroundColor Yellow
Write-Host ""
Write-Host "   URL: https://shakira-peace-workshop-bidding.trycloudflare.com/mp/webhook?company_db=TU_DB_REAL" -ForegroundColor Cyan
Write-Host "   Method: POST" -ForegroundColor Cyan
Write-Host "   Headers: Content-Type: application/json" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Body (JSON):" -ForegroundColor Cyan
Write-Host '   {' -ForegroundColor Gray
Write-Host '     "action": "payment.created",' -ForegroundColor Gray
Write-Host '     "data": {' -ForegroundColor Gray
Write-Host '         "id": "PAYMENT_ID_REAL",' -ForegroundColor Gray
Write-Host '         "status": "approved"' -ForegroundColor Gray
Write-Host '     },' -ForegroundColor Gray
Write-Host '     "type": "payment"' -ForegroundColor Gray
Write-Host '   }' -ForegroundColor Gray
Write-Host ""

# 3. Instrucciones para obtener payment_id real
Write-Host "3. Cómo obtener un payment_id real:" -ForegroundColor Yellow
Write-Host "   a) Genera un QR desde el POS" -ForegroundColor Gray
Write-Host "   b) Paga con la app de MercadoPago (usando credenciales de prueba)" -ForegroundColor Gray
Write-Host "   c) Revisa el log: Get-Content storage\logs\laravel.log | Select-String 'init_point'" -ForegroundColor Gray
Write-Host "   d) El init_point tiene el preference_id, NO el payment_id" -ForegroundColor Gray
Write-Host "   e) Para obtener el payment_id, consulta:" -ForegroundColor Gray
Write-Host "      GET https://api.mercadopago.com/v1/payments/PAYMENT_ID" -ForegroundColor Cyan
Write-Host "      (necesitas el access_token de la empresa)" -ForegroundColor Gray

Write-Host ""

# 4. Verificar logs después de una prueba real
Write-Host "4. Después de hacer un pago real, revisa los logs:" -ForegroundColor Yellow
Write-Host "   Get-Content storage\logs\laravel.log -Tail 100 | Select-String 'MP Webhook'" -ForegroundColor Cyan

Write-Host ""
Write-Host "=== FIN DEL SCRIPT ===" -ForegroundColor Green
