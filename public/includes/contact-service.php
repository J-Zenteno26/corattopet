<?php
declare(strict_types=1);

function verifyRecaptcha(string $token): bool
{
    $secret = trim((string) env('RECAPTCHA_SECRET_KEY', ''));
    if ($secret === '' || $token === '') return false;
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query(['secret' => $secret, 'response' => $token]), 'timeout' => 8, 'ignore_errors' => true]]);
    $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    return is_array($decoded) && ($decoded['success'] ?? false) === true;
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
    if (!in_array((int) substr($response, 0, 3), $codes, true)) throw new RuntimeException('SMTP command failed.');
}

function sendContactEmail(array $data): void
{
    $host = trim((string) env('SMTP_HOST', ''));
    $port = (int) env('SMTP_PORT', '587');
    $username = trim((string) env('SMTP_USERNAME', ''));
    $password = (string) env('SMTP_PASSWORD', '');
    $encryption = strtolower(trim((string) env('SMTP_ENCRYPTION', 'tls')));
    $fromEmail = trim((string) env('SMTP_FROM_EMAIL', ''));
    $fromName = preg_replace('/[\r\n]+/', ' ', trim((string) env('SMTP_FROM_NAME', 'Coratto Pet')));
    $to = trim((string) env('CORATTO_CONTACT_EMAIL', ''));
    foreach ([$host, $username, $password, $fromEmail, $to] as $required) if ($required === '') throw new RuntimeException('SMTP configuration incomplete.');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP email invalid.');

    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errorCode, $errorMessage, 10, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) throw new RuntimeException('SMTP connection failed.');
    stream_set_timeout($socket, 10);
    try {
        if ((int) substr(smtpRead($socket), 0, 3) !== 220) throw new RuntimeException('SMTP greeting failed.');
        smtpCommand($socket, 'EHLO corattopet.cl', [250]);
        if ($encryption === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed.');
            smtpCommand($socket, 'EHLO corattopet.cl', [250]);
        }
        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode($username), [334]);
        smtpCommand($socket, base64_encode($password), [235]);
        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);
        $subject = preg_replace('/[\r\n]+/', ' ', (string) $data['asunto']);
        $body = "Nombre: {$data['nombre']}\nCorreo: {$data['correo']}\nTeléfono: {$data['telefono']}\nAsunto: {$data['asunto']}\nFecha: " . date('c') . "\n\n{$data['mensaje']}";
        $headers = ['From: ' . $fromName . ' <' . $fromEmail . '>', 'Reply-To: ' . $data['correo'], 'To: ' . $to, 'Subject: =?UTF-8?B?' . base64_encode('[Contacto Coratto] ' . $subject) . '?=', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $body));
        smtpCommand($socket, $payload . "\r\n.", [250]);
        smtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
