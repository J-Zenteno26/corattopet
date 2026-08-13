<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

function correoSmtpLeer($socket): string
{
    $respuesta = '';

    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;

        if (strlen($linea) < 4 || $linea[3] === ' ') {
            break;
        }
    }

    return $respuesta;
}

function correoSmtpComando($socket, string $comando, array $codigosEsperados): void
{
    fwrite($socket, $comando . "\r\n");

    $respuesta = correoSmtpLeer($socket);
    $codigo = (int) substr($respuesta, 0, 3);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException(
            'El servidor SMTP rechazó una operación. Código: ' . $codigo
        );
    }
}

function correoSanitizarCabecera(string $valor): string
{
    return trim((string) preg_replace('/[\r\n]+/', ' ', $valor));
}

function correoEscaparHtml(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function construirCorreoHtmlCoratto(string $titulo, string $contenidoHtml): string
{
    return '<!doctype html>'
        . '<html lang="es"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#f7f0e7;font-family:Arial,Helvetica,sans-serif;color:#3a2b23;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f7f0e7;padding:28px 14px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fffaf3;border:1px solid #e7d5bd;border-radius:18px;overflow:hidden;">'
        . '<tr><td style="padding:26px 30px;background:#4a3025;color:#ffffff;">'
        . '<div style="font-size:12px;letter-spacing:1.6px;color:#e9bd70;font-weight:700;">CORATTO PET</div>'
        . '<div style="margin-top:7px;font-family:Georgia,Times New Roman,serif;font-size:28px;line-height:1.15;font-weight:700;">'
        . correoEscaparHtml($titulo)
        . '</div></td></tr>'
        . '<tr><td style="padding:30px;">'
        . $contenidoHtml
        . '<p style="margin:24px 0 0;font-size:14px;line-height:1.5;color:#79685e;">Con cariño,<br><strong style="color:#4a3025;">Coratto Pet</strong></p>'
        . '</td></tr></table>'
        . '</td></tr></table>'
        . '</body></html>';
}

function enviarCorreoTransaccional(
    string $destinatario,
    string $asunto,
    string $cuerpo,
    ?string $responderA = null,
    ?string $cuerpoHtml = null
): void {
    $host = trim((string) env('SMTP_HOST', ''));
    $port = (int) env('SMTP_PORT', '465');
    $username = trim((string) env('SMTP_USERNAME', ''));
    $password = (string) env('SMTP_PASSWORD', '');
    $encryption = strtolower(trim((string) env('SMTP_ENCRYPTION', 'ssl')));
    $fromEmail = trim((string) env('SMTP_FROM_EMAIL', ''));
    $fromName = correoSanitizarCabecera(
        (string) env('SMTP_FROM_NAME', 'Coratto Pet')
    );

    $destinatario = trim($destinatario);
    $asunto = correoSanitizarCabecera($asunto);
    $responderA = $responderA !== null ? trim($responderA) : null;

    foreach ([$host, $username, $password, $fromEmail] as $requerido) {
        if ($requerido === '') {
            throw new RuntimeException('La configuración SMTP está incompleta.');
        }
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo remitente SMTP no es válido.');
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo del destinatario no es válido.');
    }

    if (
        $responderA !== null
        && $responderA !== ''
        && !filter_var($responderA, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('El correo Reply-To no es válido.');
    }

    if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
        throw new RuntimeException('El tipo de cifrado SMTP no es válido.');
    }

    $transport = $encryption === 'ssl' ? 'ssl://' : '';

    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errorCode,
        $errorMessage,
        10,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        throw new RuntimeException(
            'No fue posible conectar al servidor SMTP.'
        );
    }

    stream_set_timeout($socket, 10);

    try {
        $saludo = correoSmtpLeer($socket);

        if ((int) substr($saludo, 0, 3) !== 220) {
            throw new RuntimeException('El servidor SMTP no respondió correctamente.');
        }

        correoSmtpComando($socket, 'EHLO corattopet.cl', [250]);

        if ($encryption === 'tls') {
            correoSmtpComando($socket, 'STARTTLS', [220]);

            if (
                !stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )
            ) {
                throw new RuntimeException('No fue posible activar TLS en SMTP.');
            }

            correoSmtpComando($socket, 'EHLO corattopet.cl', [250]);
        }

        correoSmtpComando($socket, 'AUTH LOGIN', [334]);
        correoSmtpComando($socket, base64_encode($username), [334]);
        correoSmtpComando($socket, base64_encode($password), [235]);

        correoSmtpComando(
            $socket,
            'MAIL FROM:<' . $fromEmail . '>',
            [250]
        );

        correoSmtpComando(
            $socket,
            'RCPT TO:<' . $destinatario . '>',
            [250, 251]
        );

        correoSmtpComando($socket, 'DATA', [354]);

        $cabeceras = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'To: ' . $destinatario,
            'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
            'MIME-Version: 1.0',
        ];

        if ($responderA !== null && $responderA !== '') {
            $cabeceras[] = 'Reply-To: ' . $responderA;
        }

        $cuerpoTexto = str_replace(
            ["\r\n", "\r"],
            "\n",
            $cuerpo
        );

        if ($cuerpoHtml !== null && trim($cuerpoHtml) !== '') {
            $boundary = 'coratto_' . bin2hex(random_bytes(12));

            $cabeceras[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $htmlNormalizado = str_replace(
                ["\r\n", "\r"],
                "\n",
                $cuerpoHtml
            );

            $contenidoMensaje =
                '--' . $boundary . "\n"
                . "Content-Type: text/plain; charset=UTF-8\n"
                . "Content-Transfer-Encoding: 8bit\n\n"
                . $cuerpoTexto . "\n"
                . '--' . $boundary . "\n"
                . "Content-Type: text/html; charset=UTF-8\n"
                . "Content-Transfer-Encoding: 8bit\n\n"
                . $htmlNormalizado . "\n"
                . '--' . $boundary . '--';
        } else {
            $cabeceras[] = 'Content-Type: text/plain; charset=UTF-8';
            $cabeceras[] = 'Content-Transfer-Encoding: 8bit';
            $contenidoMensaje = $cuerpoTexto;
        }

        // SMTP exige escapar las líneas que comienzan con punto.
        $contenidoMensaje = preg_replace(
            '/^\./m',
            '..',
            $contenidoMensaje
        );

        $payload = implode("\r\n", $cabeceras)
            . "\r\n\r\n"
            . str_replace("\n", "\r\n", $contenidoMensaje);

        correoSmtpComando(
            $socket,
            $payload . "\r\n.",
            [250]
        );

        correoSmtpComando($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
