<?php
// Archivo: config/recaptcha.php

/**
 * Validar token reCAPTCHA v3
 *
 * @param string $secretKey Clave secreta de reCAPTCHA
 * @param string $token Token recibido del formulario
 * @return array Resultado de la validación
 */
function validateRecaptcha($secretKey, $token) {
    if (empty($token)) {
        return [
            'success' => false,
            'score' => 0,
            'errors' => ['Token vacío'],
            'message' => 'No se pudo verificar la seguridad del formulario.'
        ];
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';

    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    // Filtrar valores null
    $data = array_filter($data);

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10 // Timeout de 10 segundos
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];

    try {
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === FALSE) {
            error_log("Error al conectar con reCAPTCHA API");
            return [
                'success' => false,
                'score' => 0,
                'errors' => ['Error de conexión'],
                'message' => 'No se pudo verificar con el servidor de seguridad.'
            ];
        }

        $response = json_decode($result, true);

        return [
            'success' => $response['success'] ?? false,
            'score' => $response['score'] ?? 0,
            'errors' => $response['error-codes'] ?? [],
            'action' => $response['action'] ?? '',
            'hostname' => $response['hostname'] ?? '',
            'message' => $response['success'] ? 'Verificación exitosa' : 'Fallo en verificación'
        ];

    } catch (Exception $e) {
        error_log("Excepción en reCAPTCHA: " . $e->getMessage());
        return [
            'success' => false,
            'score' => 0,
            'errors' => ['Excepción'],
            'message' => 'Error en el proceso de verificación.'
        ];
    }
}

/**
 * Verificar si reCAPTCHA es válido según el umbral configurado
 */
function isRecaptchaValid($recaptchaResult) {
    if (!$recaptchaResult['success']) {
        error_log("reCAPTCHA falló: " . implode(', ', $recaptchaResult['errors']));
        return false;
    }

    $score = $recaptchaResult['score'];
    $threshold = defined('RECAPTCHA_SCORE_THRESHOLD') ? RECAPTCHA_SCORE_THRESHOLD : 0.5;

    if ($score < $threshold) {
        error_log("reCAPTCHA score bajo: $score (umbral: $threshold)");
        return false;
    }

    return true;
}
