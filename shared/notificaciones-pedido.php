<?php

declare(strict_types=1);

require_once __DIR__ . '/servicio-correo.php';

function obtenerPedidoParaNotificacion(PDO $pdo, int $pedidoId): ?array
{
    $statement = $pdo->prepare(
        "SELECT
            p.id_pedido,
            p.codigo_pedido,
            p.estado,
            p.estado_pago,
            p.metodo_entrega,
            p.total,
            p.nombre_cliente,
            p.email_cliente,
            p.comuna_entrega,
            p.region_entrega,
            c.nombre AS cliente_nombre,
            c.email AS cliente_email
         FROM pedidos p
         LEFT JOIN clientes c
            ON c.id_cliente = p.id_cliente
         WHERE p.id_pedido = :id_pedido"
    );
    $statement->execute(['id_pedido' => $pedidoId]);

    $pedido = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($pedido) ? $pedido : null;
}

function obtenerHorarioRetiroNotificacion(PDO $pdo): string
{
    $statement = $pdo->query(
        "SELECT horario_atencion
         FROM configuracion_tienda
         ORDER BY id_configuracion ASC
         LIMIT 1"
    );

    $horario = $statement->fetchColumn();

    return is_scalar($horario) ? trim((string) $horario) : '';
}

function emailPedidoNotificacion(array $pedido): string
{
    $emailPedido = trim((string) ($pedido['email_cliente'] ?? ''));

    if (filter_var($emailPedido, FILTER_VALIDATE_EMAIL)) {
        return $emailPedido;
    }

    $emailCliente = trim((string) ($pedido['cliente_email'] ?? ''));

    if (filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        return $emailCliente;
    }

    throw new RuntimeException(
        'El pedido no tiene un correo de cliente válido para notificaciones.'
    );
}

function nombrePedidoNotificacion(array $pedido): string
{
    $nombre = trim((string) ($pedido['nombre_cliente'] ?? ''));

    if ($nombre !== '') {
        return $nombre;
    }

    $nombre = trim((string) ($pedido['cliente_nombre'] ?? ''));

    return $nombre !== '' ? $nombre : 'Cliente Coratto';
}

function montoPedidoNotificacion(mixed $monto): string
{
    return '$' . number_format((int) round((float) $monto), 0, ',', '.');
}

function detallePedidoCorreoHtml(array $filas): string
{
    $html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 22px;background:#f3e5d2;border-radius:12px;">';

    foreach ($filas as $etiqueta => $valor) {
        $html .= '<tr><td style="padding:12px 18px;color:#6f5d52;font-size:13px;border-bottom:1px solid #e7d5bd;">'
            . correoEscaparHtml($etiqueta)
            . '</td><td align="right" style="padding:12px 18px;color:#4a3025;font-size:14px;font-weight:700;border-bottom:1px solid #e7d5bd;">'
            . correoEscaparHtml($valor)
            . '</td></tr>';
    }

    return $html . '</table>';
}

function comentarioCorattoCorreoHtml(?string $comentario): string
{
    $comentario = trim((string) $comentario);

    if ($comentario === '') {
        return '';
    }

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:22px 0 0;border:1px solid #e7d5bd;border-radius:12px;">'
        . '<tr><td style="padding:15px 18px;">'
        . '<div style="margin:0 0 7px;color:#4a3025;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Comentarios Coratto</div>'
        . '<div style="color:#655146;font-size:14px;line-height:1.6;">'
        . nl2br(correoEscaparHtml($comentario), false)
        . '</div></td></tr></table>';
}

function comentarioCorattoCorreoTexto(?string $comentario): string
{
    $comentario = trim((string) $comentario);

    return $comentario === ''
        ? ''
        : "\n\nComentarios Coratto\n" . $comentario;
}

function pedidoAdmiteNotificacionEstado(PDO $pdo, int $pedidoId, string $estado): bool
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null || (string) $pedido['estado_pago'] !== 'pagado') {
        return false;
    }

    $metodoEntrega = (string) $pedido['metodo_entrega'];

    return match ($estado) {
        'en_preparacion', 'entregado' => true,
        'enviado' => $metodoEntrega === 'despacho',
        'listo_para_retiro' => $metodoEntrega === 'retiro_en_tienda',
        default => false,
    };
}

function notificarPedidoRecibido(PDO $pdo, int $pedidoId): void
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('No se encontró el pedido para enviar la notificación.');
    }

    if ((string) $pedido['estado_pago'] !== 'pagado') {
        throw new RuntimeException(
            'No se puede enviar la notificación de pedido recibido sin pago aprobado.'
        );
    }

    $nombre = nombrePedidoNotificacion($pedido);
    $email = emailPedidoNotificacion($pedido);
    $codigo = (string) $pedido['codigo_pedido'];
    $total = montoPedidoNotificacion($pedido['total']);
    $esRetiro = (string) $pedido['metodo_entrega'] === 'retiro_en_tienda';

    if ($esRetiro) {
        $mensajeEntrega = implode("\n", [
            'Elegiste retiro en tienda.',
            'Prepararemos tu compra y te avisaremos cuando esté lista para retiro.',
            'Por favor, espera nuestra confirmación antes de acercarte a la tienda.',
        ]);
    } else {
        $destino = trim(
            implode(', ', array_filter([
                trim((string) ($pedido['comuna_entrega'] ?? '')),
                trim((string) ($pedido['region_entrega'] ?? '')),
            ]))
        );

        $mensajeEntrega = 'Elegiste despacho a domicilio.'
            . ($destino !== '' ? "\nDestino: " . $destino . '.' : '')
            . "\nComenzaremos a preparar tu pedido y te mantendremos informado sobre su avance.";
    }

    $cuerpo = implode("\n", [
        'Hola ' . $nombre . ',',
        '',
        '¡Recibimos tu pedido correctamente!',
        '',
        'Pedido: ' . $codigo,
        'Total: ' . $total,
        '',
        $mensajeEntrega,
        '',
        'Gracias por comprar en Coratto Pet.',
        '',
        'Coratto Pet',
    ]);

    $mensajeEntregaHtml = nl2br(correoEscaparHtml($mensajeEntrega), false);
    $cuerpoHtml = construirCorreoHtmlCoratto(
        '¡Recibimos tu pedido!',
        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola ' . correoEscaparHtml($nombre) . ',</p>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;">Tu compra fue recibida correctamente y ya comenzaremos a prepararla.</p>'
        . detallePedidoCorreoHtml(['Pedido' => $codigo, 'Total' => $total])
        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:14px;line-height:1.6;">'
        . $mensajeEntregaHtml
        . '</div>'
    );

    enviarCorreoTransaccional(
        $email,
        'Pedido recibido · ' . $codigo,
        $cuerpo,
        null,
        $cuerpoHtml
    );
}

function notificarPedidoListoParaRetiro(PDO $pdo, int $pedidoId, ?string $comentario = null): void
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('No se encontró el pedido para enviar la notificación.');
    }

    if ((string) $pedido['metodo_entrega'] !== 'retiro_en_tienda') {
        throw new RuntimeException(
            'La notificación de retiro solo corresponde a pedidos con retiro en tienda.'
        );
    }

    if ((string) $pedido['estado'] !== 'listo_para_retiro') {
        throw new RuntimeException(
            'El pedido todavía no está marcado como listo para retiro.'
        );
    }

    if ((string) $pedido['estado_pago'] !== 'pagado') {
        throw new RuntimeException(
            'El pedido no tiene un pago aprobado.'
        );
    }

    $nombre = nombrePedidoNotificacion($pedido);
    $email = emailPedidoNotificacion($pedido);
    $codigo = (string) $pedido['codigo_pedido'];
    $horario = obtenerHorarioRetiroNotificacion($pdo);

    $lineas = [
        'Hola ' . $nombre . ',',
        '',
        '¡Tu pedido está listo para retiro!',
        '',
        'Pedido: ' . $codigo,
        '',
        'Ya puedes acercarte a Coratto Pet para retirarlo.',
        'Recuerda tener a mano tu número de pedido.',
    ];

    if ($horario !== '') {
        $lineas[] = '';
        $lineas[] = 'Horario de retiro: ' . $horario . '.';
    }

    if (trim((string) $comentario) !== '') {
        $lineas[] = '';
        $lineas[] = 'Comentarios Coratto';
        $lineas[] = trim((string) $comentario);
    }

    $lineas[] = '';
    $lineas[] = 'Gracias por comprar en Coratto Pet.';
    $lineas[] = '';
    $lineas[] = 'Coratto Pet';

    $detalleRetiro = 'Ya puedes acercarte a Coratto Pet para retirarlo.<br>Recuerda tener a mano tu número de pedido.';
    if ($horario !== '') {
        $detalleRetiro .= '<br><br><strong style="color:#4a3025;">Horario de retiro:</strong> '
            . correoEscaparHtml($horario) . '.';
    }

    $cuerpoHtml = construirCorreoHtmlCoratto(
        '¡Tu pedido está listo!',
        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola ' . correoEscaparHtml($nombre) . ',</p>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;">Tu pedido ya está preparado y disponible para retiro.</p>'
        . detallePedidoCorreoHtml(['Pedido' => $codigo])
        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:14px;line-height:1.6;">'
        . $detalleRetiro
        . '</div>'
        . comentarioCorattoCorreoHtml($comentario)
    );

    enviarCorreoTransaccional(
        $email,
        'Tu pedido está listo para retiro · ' . $codigo,
        implode("\n", $lineas),
        null,
        $cuerpoHtml
    );
}

function notificarPedidoEnPreparacion(PDO $pdo, int $pedidoId, ?string $comentario = null): void
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('No se encontró el pedido para enviar la notificación.');
    }
    if ((string) $pedido['estado'] !== 'en_preparacion') {
        throw new RuntimeException('El pedido no está en preparación.');
    }
    if ((string) $pedido['estado_pago'] !== 'pagado') {
        throw new RuntimeException('El pedido no tiene un pago aprobado.');
    }

    $nombre = nombrePedidoNotificacion($pedido);
    $email = emailPedidoNotificacion($pedido);
    $codigo = (string) $pedido['codigo_pedido'];
    $esRetiro = (string) $pedido['metodo_entrega'] === 'retiro_en_tienda';
    $detalle = $esRetiro
        ? 'Todavía debes esperar nuestro correo de “Listo para retiro” antes de acercarte.'
        : 'Te avisaremos cuando tu pedido haya sido enviado.';

    $cuerpo = implode("\n", [
        'Hola ' . $nombre . ',',
        '',
        'Comenzamos a preparar tu pedido.',
        '',
        'Pedido: ' . $codigo,
        '',
        $detalle,
        '',
        'Gracias por comprar en Coratto Pet.',
        '',
        'Coratto Pet',
    ]) . comentarioCorattoCorreoTexto($comentario);

    $cuerpoHtml = construirCorreoHtmlCoratto(
        'Estamos preparando tu pedido',
        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola ' . correoEscaparHtml($nombre) . ',</p>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;">En Coratto Pet ya comenzamos a preparar tu compra con mucho cuidado.</p>'
        . detallePedidoCorreoHtml(['Pedido' => $codigo])
        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:14px;line-height:1.6;">'
        . correoEscaparHtml($detalle)
        . '</div>'
        . comentarioCorattoCorreoHtml($comentario)
    );

    enviarCorreoTransaccional(
        $email,
        'Estamos preparando tu pedido · ' . $codigo,
        $cuerpo,
        null,
        $cuerpoHtml
    );
}

function notificarPedidoEnviado(PDO $pdo, int $pedidoId, ?string $comentario = null): void
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('No se encontró el pedido para enviar la notificación.');
    }
    if ((string) $pedido['estado'] !== 'enviado') {
        throw new RuntimeException('El pedido no está marcado como enviado.');
    }
    if ((string) $pedido['metodo_entrega'] !== 'despacho') {
        throw new RuntimeException('La notificación de envío solo corresponde a pedidos con despacho.');
    }
    if ((string) $pedido['estado_pago'] !== 'pagado') {
        throw new RuntimeException('El pedido no tiene un pago aprobado.');
    }

    $nombre = nombrePedidoNotificacion($pedido);
    $email = emailPedidoNotificacion($pedido);
    $codigo = (string) $pedido['codigo_pedido'];
    $detalle = 'Tu pedido ya fue despachado y va en camino.';

    $cuerpo = implode("\n", [
        'Hola ' . $nombre . ',',
        '',
        $detalle,
        '',
        'Pedido: ' . $codigo,
        '',
        'Gracias por comprar en Coratto Pet.',
        '',
        'Coratto Pet',
    ]) . comentarioCorattoCorreoTexto($comentario);

    $cuerpoHtml = construirCorreoHtmlCoratto(
        'Tu pedido va en camino',
        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola ' . correoEscaparHtml($nombre) . ',</p>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;">' . correoEscaparHtml($detalle) . '</p>'
        . detallePedidoCorreoHtml(['Pedido' => $codigo])
        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:14px;line-height:1.6;">Pronto podrás disfrutar tu compra.</div>'
        . comentarioCorattoCorreoHtml($comentario)
    );

    enviarCorreoTransaccional(
        $email,
        'Tu pedido va en camino · ' . $codigo,
        $cuerpo,
        null,
        $cuerpoHtml
    );
}

function notificarPedidoEntregado(PDO $pdo, int $pedidoId, ?string $comentario = null): void
{
    $pedido = obtenerPedidoParaNotificacion($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('No se encontró el pedido para enviar la notificación.');
    }
    if ((string) $pedido['estado'] !== 'entregado') {
        throw new RuntimeException('El pedido no está marcado como entregado.');
    }
    if ((string) $pedido['estado_pago'] !== 'pagado') {
        throw new RuntimeException('El pedido no tiene un pago aprobado.');
    }

    $nombre = nombrePedidoNotificacion($pedido);
    $email = emailPedidoNotificacion($pedido);
    $codigo = (string) $pedido['codigo_pedido'];
    $contacto = 'Si tuviste cualquier inconveniente con la entrega, escríbenos y estaremos felices de ayudarte.';

    $cuerpo = implode("\n", [
        'Hola ' . $nombre . ',',
        '',
        'Tu pedido fue marcado como entregado.',
        '',
        'Pedido: ' . $codigo,
        '',
        'Muchas gracias por elegir Coratto Pet.',
        $contacto,
        '',
        'Coratto Pet',
    ]) . comentarioCorattoCorreoTexto($comentario);

    $cuerpoHtml = construirCorreoHtmlCoratto(
        'Tu pedido fue entregado',
        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola ' . correoEscaparHtml($nombre) . ',</p>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;">Tu pedido fue marcado como entregado. Muchas gracias por elegir Coratto Pet.</p>'
        . detallePedidoCorreoHtml(['Pedido' => $codigo])
        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:14px;line-height:1.6;">'
        . correoEscaparHtml($contacto)
        . '</div>'
        . comentarioCorattoCorreoHtml($comentario)
    );

    enviarCorreoTransaccional(
        $email,
        'Tu pedido fue entregado · ' . $codigo,
        $cuerpo,
        null,
        $cuerpoHtml
    );
}

function registrarErrorNotificacionPedido(
    string $evento,
    int $pedidoId,
    Throwable $exception
): string {
    $referencia = strtoupper(bin2hex(random_bytes(4)));

    error_log(sprintf(
        '[%s] Order notification error (%s, pedido %d): %s',
        $referencia,
        $evento,
        $pedidoId,
        $exception->getMessage()
    ));

    return $referencia;
}
