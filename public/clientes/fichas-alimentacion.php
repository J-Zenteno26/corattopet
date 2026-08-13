<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$cliente = exigirClientePublico($pdo, 'public/clientes/fichas-alimentacion.php');
$fichas = fichasAlimentacionCliente($pdo, (int) $cliente['id_cliente']);
$estado = $_SESSION['cliente_fichas_estado'] ?? null;
unset($_SESSION['cliente_fichas_estado']);

renderPublicPageStart('Mis fichas de alimentación | Coratto Pet', 'Revisa las recomendaciones guardadas para tus mascotas.', 'cuenta');
?>
<main id="contenido" class="customer-area customer-feeding-area">
    <section class="customer-shell">
        <header class="customer-section-heading">
            <div>
                <a class="customer-back" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">← Mi cuenta</a>
                <span>ALIMENTACIÓN</span>
                <h1>Mis fichas de alimentación</h1>
                <p>Consulta las recomendaciones que guardaste desde la calculadora nutricional.</p>
            </div>
            <a class="customer-secondary-action" href="<?= e(appUrl('public/calculadora.php')) ?>">Nueva recomendación</a>
        </header>

        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a>
            <a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a>
            <a class="active" href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a>
            <a href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a>
            <a href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>

        <?php if (is_array($estado)): ?>
            <div class="customer-feedback customer-feedback--<?= e((string) ($estado['tipo'] ?? 'ok')) ?>" role="status">
                <?= e((string) ($estado['mensaje'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <?php if ($fichas === []): ?>
            <section class="customer-empty">
                <h2>Aún no tienes fichas guardadas</h2>
                <p>Calcula una recomendación y guarda el alimento que quieras conservar como referencia.</p>
                <a class="customer-primary-link" href="<?= e(appUrl('public/calculadora.php')) ?>">Ir a la calculadora</a>
            </section>
        <?php else: ?>
            <section class="customer-orders-history customer-feeding-history">
                <header><div><span>HISTORIAL</span><h2>Recomendaciones guardadas</h2><p>Consulta cada pauta tal como fue guardada, sin recalcular sus valores.</p></div><strong><?= count($fichas) ?> <?= count($fichas) === 1 ? 'ficha' : 'fichas' ?></strong></header>
                <div class="customer-orders-table-wrap">
                    <table class="customer-orders-table customer-feeding-table">
                        <thead><tr><th>Mascota</th><th>Fecha</th><th>Alimento</th><th>Porción diaria</th><th aria-label="Acciones"></th></tr></thead>
                        <tbody>
                        <?php foreach ($fichas as $ficha):
                            $snapshot = $ficha['snapshot'];
                            $profile = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
                            $food = is_array($snapshot['food'] ?? null) ? $snapshot['food'] : [];
                        ?>
                            <tr>
                                <td><strong><?= e((string) ($profile['petName'] ?: 'Mascota sin nombre')) ?></strong></td>
                                <td><?= e(fechaCliente($ficha['creado_en'], 'd-m-Y')) ?></td>
                                <td><?= e((string) ($food['name'] ?? 'Recomendación guardada')) ?></td>
                                <td class="customer-orders-table__total"><?= e((string) ($food['gramsDay'] ?? 0)) ?> g</td>
                                <td><a class="customer-table-action" href="<?= e(appUrl('public/clientes/ficha-alimentacion.php?id=' . (int) $ficha['id_ficha'])) ?>">Ver ficha</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="customer-orders-mobile customer-feeding-mobile">
                    <?php foreach ($fichas as $ficha):
                        $snapshot = $ficha['snapshot'];
                        $profile = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
                        $food = is_array($snapshot['food'] ?? null) ? $snapshot['food'] : [];
                    ?>
                        <details class="customer-order-accordion">
                            <summary><div><span><?= e(fechaCliente($ficha['creado_en'], 'd-m-Y')) ?></span><strong><?= e((string) ($profile['petName'] ?: 'Mascota sin nombre')) ?></strong></div><div><strong><?= e((string) ($food['gramsDay'] ?? 0)) ?> g/día</strong><span>Ver detalle</span></div></summary>
                            <div class="customer-order-accordion__content"><dl><div><dt>Alimento</dt><dd><?= e((string) ($food['name'] ?? 'Recomendación guardada')) ?></dd></div></dl><a class="customer-primary-link" href="<?= e(appUrl('public/clientes/ficha-alimentacion.php?id=' . (int) $ficha['id_ficha'])) ?>">Abrir ficha completa</a></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
