<?php
// This is the fixed callback method section

        // Decodificar state (base64 JSON con companyId, codeVerifier y redirectUri)
        $companyId = null;
        $codeVerifier = null;
        $redirectUriFromState = null;
        
        if ($stateParam) {
            try {
                $stateData = json_decode(base64_decode($stateParam), true);
                $companyId = $stateData['companyId'] ?? null;
                $codeVerifier = $stateData['codeVerifier'] ?? null;
                $redirectUriFromState = $stateData['redirectUri'] ?? null;
            } catch (\Exception $e) {
                Log::warning('[MercadoPago] Error decoding state', ['error' => $e->getMessage()]);
            }
        }
        
        // Resto del código igual, pero usar $redirectUriFromState si está disponible
        // ...
