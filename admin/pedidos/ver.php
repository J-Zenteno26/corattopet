<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-webpay.php';
require_once __DIR__ . '/includes/funciones-pedidos.php';
require_once __DIR__ . '/includes/validaciones-pedidos.php';
require_once __DIR__ . '/includes/consultas-pedidos.php';

requireAuthentication();
$orderId = idPedidoValido($_GET['id_pedido'] ?? null);
if ($orderId === null) {
    guardarModalAdmin('error', 'No fue posible abrir el pedido', 'El pedido indicado no es válido.');
    header('Location: ' . appUrl('admin/pedidos/index.php'), true, 302);
    exit;
}
try {
    $connection = database();
    $order = obtenerPedido($connection, $orderId);
    $details = obtenerDetallesPedido($connection, $orderId);
    $history = obtenerHistorialPedido($connection, $orderId);
    $webpayPayments = obtenerPagosWebpayPedido($connection, $orderId);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Order detail error', $exception);
    guardarModalAdmin('error', 'No fue posible abrir el pedido', 'No se pudo completar la acción.', ['reference' => $reference]);
    header('Location: ' . appUrl('admin/pedidos/index.php'), true, 302);
    exit;
}
if ($order === null) {
    guardarModalAdmin('error', 'No fue posible abrir el pedido', 'El pedido indicado no existe.');
    header('Location: ' . appUrl('admin/pedidos/index.php'), true, 302);
    exit;
}

$state = consumirEstadoMantenedor('pedido_estado_' . $orderId);
$formValues = ['estado' => (string) $order['estado'], 'observaciones_internas' => (string) ($order['observaciones_internas'] ?? '')];
if (is_array($state['valores'] ?? null)) {
    $formValues = array_merge($formValues, array_intersect_key($state['valores'], $formValues));
}
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$manageableStates = estadosGestionablesPedido($order);
if ($errors !== [] || $generalError !== null) {
    $adminModal = ['type' => 'error', 'title' => 'No fue posible actualizar el pedido', 'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.', 'detail' => resumenErroresFormulario($errors, $generalError), 'reference' => (string) ($state['referencia'] ?? ''), 'primaryText' => 'Aceptar'];
}

$pageTitle = 'Pedido ' . $order['codigo_pedido'];
$activeSection = 'pedidos';
$csrfToken = csrfToken();
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
$ordersCssPath = dirname(__DIR__, 2) . '/public/css/admin-pedidos.css';
$ordersCssVersion = is_file($ordersCssPath) ? (string) filemtime($ordersCssPath) : '1';
$webpayCssPath = dirname(__DIR__, 2) . '/public/css/admin-pedidos-webpay.css';
$webpayCssVersion = is_file($webpayCssPath) ? (string) filemtime($webpayCssPath) : '1';
?>
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-pedidos.css') . '?v=' . $ordersCssVersion) ?>">
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-pedidos-webpay.css') . '?v=' . $webpayCssVersion) ?>">

<main class="admin-main admin-order-detail-page" id="contenido-principal">
    <header class="admin-order-detail-top">
        <a class="admin-back-link" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>">← Volver a pedidos</a>

        <div class="admin-order-detail-top__main">
            <div>
                <span class="admin-orders-eyebrow">Información del pedido</span>
                <h1><?= escape((string) $order['codigo_pedido']) ?></h1>
                <p>
                    <?= escape(formatearFechaPedido($order['creado_en'], 'd-m-Y H:i')) ?> hrs
                    · <?= escape((string) ($order['cliente_nombre'] ?: 'Cliente no asociado')) ?>
                    · <?= escape(descripcionEntregaPedido($order['metodo_entrega'])) ?>
                </p>
            </div>

            <div class="admin-order-detail-top__status">
                <div>
                    <span>Pedido</span>
                    <strong class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>">
                        <?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Pago</span>
                    <strong class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>">
                        <?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?>
                    </strong>
                </div>

                <div class="admin-order-detail-top__total">
                    <span>Total</span>
                    <strong><?= escape(formatearDineroPedido($order['total'])) ?></strong>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-order-detail-grid admin-order-detail-grid--refined">
        <div class="admin-order-detail-main">
            <section class="admin-order-card admin-order-products">
                <header>
                    <span>Compra</span>
                    <div>
                        <h2>Productos del pedido</h2>
                        <p>Qué debe preparar el equipo para completar esta compra.</p>
                    </div>
                </header>

                <?php if ($details !== []): ?>
                    <div class="admin-order-products__wrap">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>SKU</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $item): ?>
                                    <tr>
                                        <td data-label="Producto">
                                            <strong><?= escape((string) $item['nombre_producto']) ?></strong>
                                            <?php if ($item['tipo_item'] === 'presentacion'): ?>
                                                <small>
                                                    <?= escape((string) (
                                                        $item['presentacion_nombre']
                                                        ?: (($item['cantidad_gramos'] ?? null)
                                                            ? $item['cantidad_gramos'] . ' g'
                                                            : 'Presentación')
                                                    )) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="SKU"><?= escape((string) ($item['sku'] ?: 'Sin SKU')) ?></td>
                                        <td data-label="Cantidad">
                                            <?= escape((string) $item['cantidad']) ?>
                                            <?= $item['tipo_item'] === 'presentacion' && $item['cantidad_gramos']
                                                ? ' × ' . escape((string) $item['cantidad_gramos']) . ' g'
                                                : '' ?>
                                        </td>
                                        <td data-label="Precio"><?= escape(formatearDineroPedido($item['precio_unitario'])) ?></td>
                                        <td data-label="Subtotal">
                                            <strong><?= escape(formatearDineroPedido($item['subtotal'])) ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="admin-order-inline-empty">Este pedido todavía no tiene productos asociados.</p>
                <?php endif; ?>
            </section>

            <section class="admin-order-card admin-order-info-panel">
                <header>
                    <span>Datos</span>
                    <div>
                        <h2>Cliente y entrega</h2>
                        <p>Información necesaria para contactar al cliente y completar la entrega.</p>
                    </div>
                </header>

                <div class="admin-order-info-grid">
                    <section class="admin-order-info-block" aria-labelledby="customer-info-title">
                        <h3 id="customer-info-title">Datos del cliente</h3>
                        <dl class="admin-order-detail-list">
                            <div>
                                <dt>Nombre</dt>
                                <dd><?= escape((string) ($order['cliente_nombre'] ?: 'Cliente no asociado')) ?></dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd><?= escape((string) ($order['cliente_email'] ?: 'No informado')) ?></dd>
                            </div>
                            <div>
                                <dt>Teléfono</dt>
                                <dd><?= escape((string) ($order['cliente_telefono'] ?: 'No informado')) ?></dd>
                            </div>
                            <div>
                                <dt>RUT</dt>
                                <dd><?= escape((string) ($order['cliente_rut'] ?: 'No informado')) ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="admin-order-info-block" aria-labelledby="delivery-info-title">
                        <h3 id="delivery-info-title">Datos de entrega</h3>
                        <dl class="admin-order-detail-list">
                            <div>
                                <dt>Método</dt>
                                <dd><?= escape(descripcionEntregaPedido($order['metodo_entrega'])) ?></dd>
                            </div>
                            <div>
                                <dt>Dirección</dt>
                                <dd><?= escape((string) (
                                    $order['direccion_entrega']
                                    ?: $order['cliente_direccion']
                                    ?: 'No informada'
                                )) ?></dd>
                            </div>
                            <div>
                                <dt>Comuna</dt>
                                <dd><?= escape((string) (
                                    $order['comuna_entrega']
                                    ?: $order['cliente_comuna']
                                    ?: 'No informada'
                                )) ?></dd>
                            </div>
                            <div>
                                <dt>Región</dt>
                                <dd><?= escape((string) (
                                    $order['region_entrega']
                                    ?: $order['cliente_region']
                                    ?: 'No informada'
                                )) ?></dd>
                            </div>

                            <?php if (!empty($order['observaciones_cliente'])): ?>
                                <div class="admin-order-detail-list__note">
                                    <dt>Observación del cliente</dt>
                                    <dd><?= nl2br(escape((string) $order['observaciones_cliente'])) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </section>
                </div>
            </section>

            <section class="admin-order-card admin-order-history-card">
                <header>
                    <span>Historial</span>
                    <div>
                        <h2>Trazabilidad</h2>
                        <p>Registro de los cambios administrativos realizados sobre este pedido.</p>
                    </div>
                </header>

                <?php if ($history !== []): ?>
                    <ol class="admin-order-history">
                        <?php foreach ($history as $event): ?>
                            <li>
                                <span class="admin-order-history__dot"></span>
                                <div>
                                    <header>
                                        <strong><?= escape((string) ($event['usuario_nombre'] ?: 'Usuario no disponible')) ?></strong>
                                        <time><?= escape(formatearFechaPedido($event['creado_en'])) ?></time>
                                    </header>

                                    <?php if ($event['estado_nuevo'] !== null): ?>
                                        <p>
                                            Estado:
                                            <span><?= escape(etiquetaEstadoPedido((string) $event['estado_anterior'])) ?></span>
                                            →
                                            <strong><?= escape(etiquetaEstadoPedido((string) $event['estado_nuevo'])) ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($event['estado_pago_nuevo'] !== null): ?>
                                        <p>
                                            Pago:
                                            <span><?= escape(etiquetaEstadoPagoPedido((string) $event['estado_pago_anterior'])) ?></span>
                                            →
                                            <strong><?= escape(etiquetaEstadoPagoPedido((string) $event['estado_pago_nuevo'])) ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($event['observacion'])): ?>
                                        <small><?= nl2br(escape((string) $event['observacion'])) ?></small>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="admin-order-inline-empty">Aún no hay cambios registrados para este pedido.</p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="admin-order-detail-side">
            <section class="admin-order-card admin-order-management admin-order-management--primary">
                <header>
                    <span>Acción</span>
                    <div>
                        <h2>Gestión de entrega</h2>
                        <p>Aquí haces avanzar el pedido. El pago se mantiene bajo control de Webpay.</p>
                    </div>
                </header>

                <div class="admin-order-management__intro">
                    <span>Estado actual</span>
                    <strong class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>">
                        <?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?>
                    </strong>
                    <p>
                        <?= escape(resumenEstadoOperativoPedido(
                            (string) $order['estado'],
                            (string) $order['metodo_entrega']
                        )) ?>
                    </p>
                </div>

                <div class="admin-order-management__flow">
                    <span>Flujo de este pedido</span>
                    <strong><?= escape(textoFlujoOperativoPedido($order)) ?></strong>
                    <?php if ((string) $order['metodo_entrega'] === 'retiro_en_tienda'): ?>
                        <small>
                            Al marcar “Listo para retiro”, el cliente recibirá automáticamente la notificación por correo.
                        </small>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= escape(appUrl('admin/pedidos/actualizar-estado.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="id_pedido" value="<?= escape((string) $orderId) ?>">

                    <div class="admin-order-management__form-grid">
                        <div class="admin-field<?= isset($errors['estado']) ? ' admin-field--invalid' : '' ?>">
                        <label for="estado">Nuevo estado del pedido</label>
                        <select id="estado" name="estado">
                            <?php foreach ($manageableStates as $value => $label): ?>
                                <option value="<?= escape($value) ?>" <?= $formValues['estado'] === $value ? 'selected' : '' ?>>
                                    <?= escape($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (isset($errors['estado'])): ?>
                            <span class="admin-field__error"><?= escape((string) $errors['estado']) ?></span>
                        <?php elseif ((string) $order['estado_pago'] !== 'pagado'): ?>
                            <small class="admin-order-management__hint">
                                El pedido podrá avanzar cuando Webpay confirme el pago.
                            </small>
                        <?php endif; ?>
                    </div>

                        <div class="admin-field admin-order-payment-readonly">
                            <span class="admin-order-payment-readonly__label">Estado de pago</span>
                            <div class="admin-order-payment-readonly__value">
                                <span class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>">
                                    <?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?>
                                </span>
                                <small>Solo lectura · gestionado automáticamente por Webpay.</small>
                            </div>
                        </div>
                    </div>

                    <div class="admin-field<?= isset($errors['observaciones_internas']) ? ' admin-field--invalid' : '' ?>">
                        <label for="observaciones_internas">Observaciones internas</label>
                        <textarea
                            id="observaciones_internas"
                            name="observaciones_internas"
                            maxlength="1000"
                            rows="3"
                            placeholder="Notas visibles solo para administración"
                        ><?= escape((string) $formValues['observaciones_internas']) ?></textarea>

                        <?php if (isset($errors['observaciones_internas'])): ?>
                            <span class="admin-field__error"><?= escape((string) $errors['observaciones_internas']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button class="admin-button admin-order-management__submit" type="submit">
                        Guardar estado
                    </button>
                </form>
            </section>

            <?php if ($webpayPayments !== []): ?>
                <?php
                $latestWebpayPayment = $webpayPayments[0];
                $webpayStateLabels = [
                    'creado' => 'Creado',
                    'redirigido' => 'Esperando pago',
                    'autorizado' => 'Pago autorizado',
                    'rechazado' => 'Pago rechazado',
                    'anulado' => 'Anulado',
                    'abandonado' => 'Pago no completado',
                    'error' => 'Error',
                ];
                $latestWebpayState = (string) ($latestWebpayPayment['estado'] ?? '');
                ?>
                <section class="admin-order-card admin-order-webpay">
                    <header>
                        <span>Pago</span>
                        <div>
                            <h2>Webpay</h2>
                            <p>Información financiera y trazabilidad de los intentos de pago.</p>
                        </div>
                    </header>

                    <div class="admin-order-webpay__summary">
                        <div>
                            <span class="admin-order-webpay__status admin-order-webpay__status--<?= escape($latestWebpayState) ?>">
                                <?= escape($webpayStateLabels[$latestWebpayState] ?? ucfirst($latestWebpayState)) ?>
                            </span>
                            <strong><?= escape(formatearDineroPedido($latestWebpayPayment['monto'])) ?></strong>
                        </div>

                        <dl>
                            <div>
                                <dt>Orden Webpay</dt>
                                <dd><?= escape((string) $latestWebpayPayment['buy_order']) ?></dd>
                            </div>
                            <div>
                                <dt>Autorización</dt>
                                <dd><?= escape((string) ($latestWebpayPayment['authorization_code'] ?: 'No disponible')) ?></dd>
                            </div>
                            <div>
                                <dt>Tarjeta</dt>
                                <dd>
                                    <?= !empty($latestWebpayPayment['card_last_four'])
                                        ? '•••• ' . escape((string) $latestWebpayPayment['card_last_four'])
                                        : 'No disponible' ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Tipo de pago</dt>
                                <dd>
                                    <?= !empty($latestWebpayPayment['payment_type_code'])
                                        ? escape(etiquetaTipoPagoWebpay((string) $latestWebpayPayment['payment_type_code']))
                                        : 'No disponible' ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Cuotas</dt>
                                <dd>
                                    <?php if (!empty($latestWebpayPayment['payment_type_code'])): ?>
                                        <?= ((int) ($latestWebpayPayment['installments_number'] ?? 0)) > 0
                                            ? escape((string) $latestWebpayPayment['installments_number'])
                                            : 'Sin cuotas' ?>
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Fecha</dt>
                                <dd>
                                    <?= escape(formatearFechaPedido(
                                        $latestWebpayPayment['transaction_date']
                                        ?: $latestWebpayPayment['actualizado_en']
                                    )) ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Estado Transbank</dt>
                                <dd><?= escape((string) ($latestWebpayPayment['status_transbank'] ?: 'No disponible')) ?></dd>
                            </div>
                            <div>
                                <dt>Código respuesta</dt>
                                <dd>
                                    <?= $latestWebpayPayment['response_code'] !== null
                                        ? escape((string) $latestWebpayPayment['response_code'])
                                        : 'No disponible' ?>
                                </dd>
                            </div>
                        </dl>

                        <?php if (!empty($latestWebpayPayment['mensaje_error'])): ?>
                            <p class="admin-order-webpay__error">
                                <?= escape((string) $latestWebpayPayment['mensaje_error']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (count($webpayPayments) > 1): ?>
                        <div class="admin-order-webpay__attempts">
                            <h3>Historial de intentos</h3>
                            <ol>
                                <?php foreach ($webpayPayments as $index => $webpayPayment): ?>
                                    <?php $paymentState = (string) ($webpayPayment['estado'] ?? ''); ?>
                                    <li>
                                        <span>#<?= escape((string) (count($webpayPayments) - $index)) ?></span>
                                        <strong><?= escape($webpayStateLabels[$paymentState] ?? ucfirst($paymentState)) ?></strong>
                                        <b><?= escape(formatearDineroPedido($webpayPayment['monto'])) ?></b>
                                        <time>
                                            <?= escape(formatearFechaPedido(
                                                $webpayPayment['transaction_date']
                                                ?: $webpayPayment['actualizado_en']
                                            )) ?>
                                        </time>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                </section>
            <section class="admin-order-card admin-order-totals">
                <header>
                    <span>Resumen</span>
                    <div>
                        <h2>Totales</h2>
                    </div>
                </header>

                <dl>
                    <div>
                        <dt>Subtotal</dt>
                        <dd><?= escape(formatearDineroPedido($order['subtotal'])) ?></dd>
                    </div>
                    <div>
                        <dt>Descuento</dt>
                        <dd>− <?= escape(formatearDineroPedido($order['descuento'])) ?></dd>
                    </div>
                    <div>
                        <dt>Despacho</dt>
                        <dd><?= escape(formatearDineroPedido($order['costo_despacho'])) ?></dd>
                    </div>
                    <div class="admin-order-totals__final">
                        <dt>Total</dt>
                        <dd><?= escape(formatearDineroPedido($order['total'])) ?></dd>
                    </div>
                </dl>

                <?php if (!empty($order['metodo_pago']) || !empty($order['referencia_pago'])): ?>
                    <p>
                        <strong><?= escape((string) ($order['metodo_pago'] ?: 'Método no informado')) ?></strong>
                        <?php if (!empty($order['referencia_pago'])): ?>
                            <span>Ref. <?= escape((string) $order['referencia_pago']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>

            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
