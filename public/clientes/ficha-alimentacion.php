<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$cliente = exigirClientePublico($pdo, 'public/clientes/fichas-alimentacion.php');
$idFicha = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ficha = $idFicha ? fichaAlimentacionCliente($pdo, (int) $cliente['id_cliente'], (int) $idFicha) : null;
if ($ficha === null) {
    http_response_code(404);
    renderPublicPageStart('Ficha no encontrada | Coratto Pet', 'La ficha solicitada no está disponible.', 'cuenta');
    ?><main id="contenido" class="customer-area customer-feeding-area"><section class="customer-shell"><div class="customer-feedback customer-feedback--error">La ficha solicitada no existe o no pertenece a tu cuenta.</div><a class="customer-back" href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">← Volver a mis fichas</a></section></main><?php
    renderPublicPageEnd();
    exit;
}

$snapshot = $ficha['snapshot'];
$profile = $snapshot['profile'] ?? [];
$result = $snapshot['result'] ?? [];
$food = $snapshot['food'] ?? [];
$labels = [
    'perro' => 'Perro', 'gato' => 'Gato', 'female' => 'Hembra', 'male' => 'Macho',
    'puppy' => 'Cachorro', 'kitten' => 'Gatito', 'adult' => 'Adulto', 'senior' => 'Senior',
    'years' => 'años', 'months' => 'meses',
    'small' => 'Pequeño', 'medium' => 'Mediano', 'large' => 'Grande', 'giant' => 'Gigante',
    'thin' => 'Bajo peso', 'ideal' => 'Condición ideal', 'overweight' => 'Sobrepeso', 'obese' => 'Obesidad',
    'low' => 'Baja', 'normal' => 'Moderada', 'high' => 'Alta',
    'mixed' => 'Mestiza', 'defined' => 'Raza definida',
    'healthy' => 'Sin condición informada', 'sensitive' => 'Sensibilidad digestiva o cutánea',
    'medical' => 'Enfermedad o condición médica', 'pregnancy' => 'Gestación o lactancia',
    'none' => 'Ninguno informado', 'pollo' => 'Pollo', 'pescado' => 'Pescado',
    'cordero' => 'Cordero', 'vacuno' => 'Vacuno', 'pavo' => 'Pavo', 'cerdo' => 'Cerdo',
    'grain' => 'Cereales',
];
renderPublicPageStart('Ficha de alimentación | Coratto Pet', 'Detalle de una recomendación nutricional guardada.', 'cuenta');
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-section-heading">
            <div><a class="customer-back" href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">← Mis fichas</a><span>FICHA DE ALIMENTACIÓN</span><h1><?= e((string) ($profile['petName'] ?: 'Mascota sin nombre')) ?></h1><p>Snapshot guardado el <?= e(fechaCliente($ficha['creado_en'], 'd-m-Y H:i')) ?>. Sus valores no se recalculan.</p></div>
            <button class="customer-secondary-action" type="button" onclick="window.print()">Imprimir / guardar PDF</button>
        </header>
        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a><a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a><a class="active" href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a><a href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a><a href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>
        <section class="customer-metrics customer-feeding-metrics" aria-label="Resultado de la recomendación">
            <article><span>Energía diaria</span><strong><?= e((string) ($result['kcalDay'] ?? 0)) ?></strong><small>kcal por día</small></article>
            <article class="is-active"><span>Porción diaria</span><strong><?= e((string) ($food['gramsDay'] ?? 0)) ?> g</strong><small><?= e((string) ($result['meals'] ?? 0)) ?> comidas</small></article>
            <article><span>Por comida</span><strong><?= e((string) ($food['gramsMeal'] ?? 0)) ?> g</strong><small>porción estimada</small></article>
        </section>
        <section class="customer-feeding-content">
            <article class="customer-panel customer-feeding-card"><header><div><span>MASCOTA</span><h2>Perfil calculado</h2></div></header><div class="customer-feeding-data-grid">
                <div><span>Especie</span><strong><?= e($labels[$profile['species'] ?? ''] ?? (string) ($profile['species'] ?? '')) ?></strong></div>
                <div><span>Sexo</span><strong><?= e($labels[$profile['sex'] ?? ''] ?? (string) ($profile['sex'] ?? '')) ?></strong></div>
                <div><span>Edad</span><strong><?= e((string) ($profile['age'] ?? '')) ?> <?= e($labels[$profile['ageUnit'] ?? ''] ?? '') ?></strong></div>
                <div><span>Peso</span><strong><?= e((string) ($profile['weight'] ?? '')) ?> kg</strong></div>
                <div><span>Peso ideal</span><strong><?= ($profile['idealWeight'] ?? null) !== null ? e((string) $profile['idealWeight']) . ' kg' : 'No informado' ?></strong></div>
                <?php if (($profile['species'] ?? '') === 'perro' && !empty($profile['size'])): ?>
                    <div><span>Tamaño adulto</span><strong><?= e($labels[$profile['size']] ?? (string) $profile['size']) ?></strong></div>
                <?php endif; ?>
                <div><span>Etapa</span><strong><?= e($labels[$result['stage'] ?? ''] ?? (string) ($result['stage'] ?? '')) ?></strong></div>
                <div><span>Condición corporal</span><strong><?= e($labels[$profile['bodyCondition'] ?? ''] ?? (string) ($profile['bodyCondition'] ?? '')) ?></strong></div>
                <div><span>Actividad</span><strong><?= e($labels[$profile['activity'] ?? ''] ?? (string) ($profile['activity'] ?? '')) ?></strong></div>
                <div><span>Esterilización</span><strong><?= !empty($profile['sterilized']) ? 'Sí' : 'No' ?></strong></div>
                <div><span>Tipo de raza</span><strong><?= e($labels[$profile['breedType'] ?? ''] ?? (string) ($profile['breedType'] ?? '')) ?></strong></div>
                <div><span>Raza</span><strong><?= e((string) (($profile['breed'] ?? '') ?: 'No informada')) ?></strong></div>
                <div><span>Estado de salud</span><strong><?= e($labels[$profile['health'] ?? ''] ?? (string) ($profile['health'] ?? '')) ?></strong></div>
                <div><span>Componente evitado</span><strong><?= e($labels[$profile['allergy'] ?? ''] ?? (string) ($profile['allergy'] ?? '')) ?></strong></div>
            </div></article>
            <article class="customer-panel customer-feeding-card customer-feeding-food"><header><div><span>ALIMENTO</span><h2><?= e((string) ($food['name'] ?? '')) ?></h2></div></header><div class="customer-feeding-data-grid customer-feeding-data-grid--food">
                <div><span>Marca</span><strong><?= e((string) ($food['brand'] ?? '')) ?></strong></div><div><span>SKU</span><strong><?= e((string) ($food['sku'] ?? '')) ?></strong></div><div><span>Energía usada</span><strong><?= e((string) ($food['kcalKg'] ?? 0)) ?> kcal/kg</strong></div><div><span>Comidas</span><strong><?= e((string) ($result['meals'] ?? 0)) ?> al día</strong></div>
            </div></article>
        </section>
        <aside class="customer-panel customer-feeding-note"><strong>Importante</strong><p>Esta pauta es una estimación orientativa y no reemplaza la evaluación ni recomendación de un médico veterinario.</p></aside>
        <section class="customer-panel customer-feeding-delete"><div><strong>Eliminar esta ficha</strong><p>La ficha se quitará únicamente de tu historial de alimentación.</p></div><form method="post" action="<?= e(appUrl('public/clientes/eliminar-ficha-alimentacion.php')) ?>" onsubmit="return confirm('¿Eliminar esta ficha de alimentación?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id_ficha" value="<?= (int) $ficha['id_ficha'] ?>"><button class="customer-secondary-action customer-feeding-delete__button" type="submit">Eliminar ficha</button></form></section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
