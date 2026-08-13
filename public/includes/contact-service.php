<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/servicio-correo.php';
function verifyRecaptcha(string $token): bool
{
    $secret = trim((string) env('RECAPTCHA_SECRET_KEY', ''));

    if ($secret === '' || $token === '') {
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'secret' => $secret,
                'response' => $token,
            ]),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify',
        false,
        $context
    );

    $decoded = is_string($response)
        ? json_decode($response, true)
        : null;
    if (!is_array($decoded) || ($decoded['success'] ?? false) !== true) {
        return false;
    }

    $hostname = strtolower(trim((string) ($decoded['hostname'] ?? '')));

    $allowedHosts = [
        'corattopet.cl',
        'www.corattopet.cl',
        'test.corattopet.cl',
        'localhost',
        '127.0.0.1',
    ];

    return in_array($hostname, $allowedHosts, true);
}

function smtpRead($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function smtpCommand($socket, string $command, array $codes): void
{
    fwrite($socket, $command . "\r\n");

    $response = smtpRead($socket);
    $responseCode = (int) substr($response, 0, 3);

    if (!in_array($responseCode, $codes, true)) {
        throw new RuntimeException(
            'SMTP command failed. Code: '
            . $responseCode
            . ' Response: '
            . trim($response)
        );
    }
}

function sendContactEmail(array $data): void
{
    $destinatario = trim((string) env('CORATTO_CONTACT_EMAIL', ''));

    if ($destinatario === '') {
        throw new RuntimeException(
            'El correo de contacto de Coratto no está configurado.'
        );
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            'El correo de contacto de Coratto no es válido.'
        );
    }

    $nombre = trim((string) ($data['nombre'] ?? ''));
    $correo = trim((string) ($data['correo'] ?? ''));
    $telefono = trim((string) ($data['telefono'] ?? ''));
    $asunto = trim((string) ($data['asunto'] ?? ''));
    $mensaje = trim((string) ($data['mensaje'] ?? ''));

    $asuntoCorreo = '[Contacto Coratto] ' . $asunto;

    $cuerpoTexto =
        "Nueva consulta desde el sitio web de Coratto Pet\n\n"
        . "Nombre: {$nombre}\n"
        . "Correo: {$correo}\n"
        . "Teléfono: {$telefono}\n"
        . "Asunto: {$asunto}\n"
        . "Fecha: " . date('d-m-Y H:i') . "\n\n"
        . "Mensaje:\n"
        . $mensaje;

    $contenidoHtml =
        '<p style="margin:0 0 22px;font-size:15px;line-height:1.65;color:#5f4a3e;">'
        . 'Has recibido una nueva consulta desde el formulario de contacto de '
        . '<strong style="color:#4a3025;">Coratto Pet</strong>.'
        . '</p>'

        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" '
        . 'style="width:100%;margin:0 0 24px;border-collapse:separate;border-spacing:0;">'

        . '<tr>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#8d6c51;font-size:12px;font-weight:700;width:115px;">NOMBRE</td>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#3a2b23;font-size:14px;font-weight:700;">'
        . correoEscaparHtml($nombre)
        . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#8d6c51;font-size:12px;font-weight:700;">CORREO</td>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#3a2b23;font-size:14px;">'
        . correoEscaparHtml($correo)
        . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#8d6c51;font-size:12px;font-weight:700;">TELÉFONO</td>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #eadcc9;color:#3a2b23;font-size:14px;">'
        . correoEscaparHtml($telefono)
        . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:12px 14px;color:#8d6c51;font-size:12px;font-weight:700;">ASUNTO</td>'
        . '<td style="padding:12px 14px;color:#3a2b23;font-size:14px;font-weight:700;">'
        . correoEscaparHtml($asunto)
        . '</td>'
        . '</tr>'

        . '</table>'

        . '<div style="padding:20px 22px;border:1px solid #e7d5bd;border-radius:14px;background:#f8eee1;">'
        . '<div style="margin-bottom:8px;color:#b77a2c;font-size:11px;font-weight:700;letter-spacing:1.2px;">MENSAJE DEL CLIENTE</div>'
        . '<div style="color:#4a382e;font-size:15px;line-height:1.7;">'
        . nl2br(correoEscaparHtml($mensaje))
        . '</div>'
        . '</div>'

        . '<p style="margin:20px 0 0;color:#8a7466;font-size:12px;line-height:1.5;">'
        . 'Recibido el '
        . correoEscaparHtml(date('d-m-Y \a \l\a\s H:i'))
        . '. Puedes responder directamente a este correo para contactar al cliente.'
        . '</p>';

    $cuerpoHtml = construirCorreoHtmlCoratto(
        'Nueva consulta desde el sitio web',
        $contenidoHtml
    );

    enviarCorreoTransaccional(
        $destinatario,
        $asuntoCorreo,
        $cuerpoTexto,
        $correo,
        $cuerpoHtml
    );
}
