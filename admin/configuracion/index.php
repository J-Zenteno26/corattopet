<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-configuracion.php';

requireAuthentication();
$values = valoresInicialesConfiguracion();
try {
    $values = obtenerConfiguracionTienda(database());
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Store settings page query error', $exception);
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar la configuración', 'message' => 'No se pudo completar la acción.', 'reference' => $reference, 'primaryText' => 'Aceptar'];
}

$state = consumirEstadoConfiguracion();
if (is_array($state['values'] ?? null)) {
    $values = array_merge($values, $state['values']);
}
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$generalError = is_string($state['generalError'] ?? null) ? $state['generalError'] : null;
$errorReference = is_string($state['reference'] ?? null) ? $state['reference'] : '';
if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error', 'title' => 'No fue posible guardar la configuración',
        'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError), 'reference' => $errorReference, 'primaryText' => 'Aceptar',
    ];
}

$csrfToken = csrfToken();
$pageTitle = 'Configuración de tienda';
$activeSection = 'configuracion';

$ubicacionesChile = [
    'Arica y Parinacota' => ['Arica', 'Camarones', 'General Lagos', 'Putre'],
    'Tarapacá' => ['Alto Hospicio', 'Camiña', 'Colchane', 'Huara', 'Iquique', 'Pica', 'Pozo Almonte'],
    'Antofagasta' => ['Antofagasta', 'Calama', 'María Elena', 'Mejillones', 'Ollagüe', 'San Pedro de Atacama', 'Sierra Gorda', 'Taltal', 'Tocopilla'],
    'Atacama' => ['Alto del Carmen', 'Caldera', 'Chañaral', 'Copiapó', 'Diego de Almagro', 'Freirina', 'Huasco', 'Tierra Amarilla', 'Vallenar'],
    'Coquimbo' => ['Andacollo', 'Canela', 'Combarbalá', 'Coquimbo', 'Illapel', 'La Higuera', 'La Serena', 'Los Vilos', 'Monte Patria', 'Ovalle', 'Paihuano', 'Punitaqui', 'Río Hurtado', 'Salamanca', 'Vicuña'],
    'Valparaíso' => ['Algarrobo', 'Cabildo', 'Calera', 'Calle Larga', 'Cartagena', 'Casablanca', 'Catemu', 'Concón', 'El Quisco', 'El Tabo', 'Hijuelas', 'Isla de Pascua', 'Juan Fernández', 'La Cruz', 'La Ligua', 'Limache', 'Llay-Llay', 'Los Andes', 'Nogales', 'Olmué', 'Panquehue', 'Papudo', 'Petorca', 'Puchuncaví', 'Putaendo', 'Quillota', 'Quilpué', 'Quintero', 'Rinconada', 'San Antonio', 'San Esteban', 'San Felipe', 'Santa María', 'Santo Domingo', 'Valparaíso', 'Villa Alemana', 'Viña del Mar', 'Zapallar'],
    'Metropolitana de Santiago' => ['Alhué', 'Buin', 'Calera de Tango', 'Cerrillos', 'Cerro Navia', 'Colina', 'Conchalí', 'Curacaví', 'El Bosque', 'El Monte', 'Estación Central', 'Huechuraba', 'Independencia', 'Isla de Maipo', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Lampa', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maipú', 'María Pinto', 'Melipilla', 'Ñuñoa', 'Padre Hurtado', 'Paine', 'Pedro Aguirre Cerda', 'Peñaflor', 'Peñalolén', 'Pirque', 'Providencia', 'Pudahuel', 'Puente Alto', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Bernardo', 'San Joaquín', 'San José de Maipo', 'San Miguel', 'San Pedro', 'San Ramón', 'Santiago', 'Talagante', 'Tiltil', 'Vitacura'],
    'Libertador General Bernardo O’Higgins' => ['Chépica', 'Chimbarongo', 'Codegua', 'Coinco', 'Coltauco', 'Doñihue', 'Graneros', 'La Estrella', 'Las Cabras', 'Litueche', 'Lolol', 'Machalí', 'Malloa', 'Marchigüe', 'Mostazal', 'Nancagua', 'Navidad', 'Olivar', 'Palmilla', 'Paredones', 'Peralillo', 'Peumo', 'Pichidegua', 'Pichilemu', 'Placilla', 'Pumanque', 'Quinta de Tilcoco', 'Rancagua', 'Rengo', 'Requínoa', 'San Fernando', 'San Vicente', 'Santa Cruz'],
    'Maule' => ['Cauquenes', 'Chanco', 'Colbún', 'Constitución', 'Curepto', 'Curicó', 'Empedrado', 'Hualañé', 'Licantén', 'Linares', 'Longaví', 'Maule', 'Molina', 'Parral', 'Pelarco', 'Pelluhue', 'Pencahue', 'Rauco', 'Retiro', 'Río Claro', 'Romeral', 'Sagrada Familia', 'San Clemente', 'San Javier', 'San Rafael', 'Talca', 'Teno', 'Vichuquén', 'Villa Alegre', 'Yerbas Buenas'],
    'Ñuble' => ['Bulnes', 'Chillán', 'Chillán Viejo', 'Cobquecura', 'Coelemu', 'Coihueco', 'El Carmen', 'Ninhue', 'Ñiquén', 'Pemuco', 'Pinto', 'Portezuelo', 'Quillón', 'Quirihue', 'Ránquil', 'San Carlos', 'San Fabián', 'San Ignacio', 'San Nicolás', 'Treguaco', 'Yungay'],
    'Biobío' => ['Alto Biobío', 'Antuco', 'Arauco', 'Cabrero', 'Cañete', 'Chiguayante', 'Concepción', 'Contulmo', 'Coronel', 'Curanilahue', 'Florida', 'Hualpén', 'Hualqui', 'Laja', 'Lebu', 'Los Álamos', 'Los Ángeles', 'Lota', 'Mulchén', 'Nacimiento', 'Negrete', 'Penco', 'Quilaco', 'Quilleco', 'San Pedro de la Paz', 'San Rosendo', 'Santa Bárbara', 'Santa Juana', 'Talcahuano', 'Tirúa', 'Tomé', 'Tucapel', 'Yumbel'],
    'La Araucanía' => ['Angol', 'Carahue', 'Cholchol', 'Collipulli', 'Cunco', 'Curacautín', 'Curarrehue', 'Ercilla', 'Freire', 'Galvarino', 'Gorbea', 'Lautaro', 'Loncoche', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Melipeuco', 'Nueva Imperial', 'Padre Las Casas', 'Perquenco', 'Pitrufquén', 'Pucón', 'Purén', 'Renaico', 'Saavedra', 'Temuco', 'Teodoro Schmidt', 'Toltén', 'Traiguén', 'Victoria', 'Vilcún', 'Villarrica'],
    'Los Ríos' => ['Corral', 'Futrono', 'La Unión', 'Lago Ranco', 'Lanco', 'Los Lagos', 'Máfil', 'Mariquina', 'Paillaco', 'Panguipulli', 'Río Bueno', 'Valdivia'],
    'Los Lagos' => ['Ancud', 'Calbuco', 'Castro', 'Chaitén', 'Chonchi', 'Cochamó', 'Curaco de Vélez', 'Dalcahue', 'Fresia', 'Frutillar', 'Futaleufú', 'Hualaihué', 'Llanquihue', 'Los Muermos', 'Maullín', 'Osorno', 'Palena', 'Puerto Montt', 'Puerto Octay', 'Puerto Varas', 'Puqueldón', 'Purranque', 'Puyehue', 'Queilén', 'Quellón', 'Quemchi', 'Quinchao', 'Río Negro', 'San Juan de la Costa', 'San Pablo'],
    'Aysén del General Carlos Ibáñez del Campo' => ['Aysén', 'Chile Chico', 'Cisnes', 'Cochrane', 'Coyhaique', 'Guaitecas', 'Lago Verde', 'O’Higgins', 'Río Ibáñez', 'Tortel'],
    'Magallanes y de la Antártica Chilena' => ['Antártica', 'Cabo de Hornos', 'Laguna Blanca', 'Natales', 'Porvenir', 'Primavera', 'Punta Arenas', 'Río Verde', 'San Gregorio', 'Timaukel', 'Torres del Paine'],
];

$input = static function (string $name, string $label, array $values, array $errors, string $type = 'text', int $max = 255, string $placeholder = ''): void {
    $invalid = isset($errors[$name]);
    ?>
    <div class="admin-field<?= $invalid ? ' admin-field--invalid' : '' ?>">
        <label for="<?= escape($name) ?>"><?= escape($label) ?><?= $name === 'nombre_tienda' ? ' *' : '' ?></label>
        <input id="<?= escape($name) ?>" name="<?= escape($name) ?>" type="<?= escape($type) ?>" maxlength="<?= $max ?>" value="<?= escape((string) $values[$name]) ?>" placeholder="<?= escape($placeholder) ?>" <?= $name === 'nombre_tienda' ? 'required' : '' ?> <?= $invalid ? 'aria-invalid="true" aria-describedby="' . escape($name) . '-error"' : '' ?>>
        <?php if ($invalid): ?><span class="admin-field__error" id="<?= escape($name) ?>-error"><?= escape((string) $errors[$name]) ?></span><?php endif; ?>
    </div><?php
};
$textarea = static function (string $name, string $label, array $values, array $errors, int $max, string $placeholder = ''): void {
    $invalid = isset($errors[$name]);
    ?>
    <div class="admin-field admin-field--full<?= $invalid ? ' admin-field--invalid' : '' ?>">
        <label for="<?= escape($name) ?>"><?= escape($label) ?></label>
        <textarea id="<?= escape($name) ?>" name="<?= escape($name) ?>" maxlength="<?= $max ?>" rows="3" placeholder="<?= escape($placeholder) ?>" <?= $invalid ? 'aria-invalid="true" aria-describedby="' . escape($name) . '-error"' : '' ?>><?= escape((string) $values[$name]) ?></textarea>
        <?php if ($invalid): ?><span class="admin-field__error" id="<?= escape($name) ?>-error"><?= escape((string) $errors[$name]) ?></span><?php endif; ?>
    </div><?php
};
$switch = static function (string $name, string $label, string $help, array $values): void { ?>
    <div class="admin-setting-toggle"><div><strong><?= escape($label) ?></strong><span><?= escape($help) ?></span></div><label class="admin-switch" for="<?= escape($name) ?>"><input id="<?= escape($name) ?>" name="<?= escape($name) ?>" type="checkbox" value="1" <?= !empty($values[$name]) ? 'checked' : '' ?>><span class="admin-switch__track" aria-hidden="true"></span><span class="admin-switch__label">Sí</span></label></div><?php
};
$select = static function (string $name, string $label, array $options, array $values, array $errors, string $placeholder): void {
    $current = (string) ($values[$name] ?? '');
    $invalid = isset($errors[$name]);
    ?>
    <div class="admin-field admin-settings-select<?= $invalid ? ' admin-field--invalid' : '' ?>">
        <label for="<?= escape($name) ?>"><?= escape($label) ?></label>
        <select id="<?= escape($name) ?>" name="<?= escape($name) ?>" <?= $invalid ? 'aria-invalid="true" aria-describedby="' . escape($name) . '-error"' : '' ?>>
            <option value=""><?= escape($placeholder) ?></option>
            <?php if ($current !== '' && !in_array($current, $options, true)): ?><option value="<?= escape($current) ?>" selected><?= escape($current) ?></option><?php endif; ?>
            <?php foreach ($options as $option): ?><option value="<?= escape($option) ?>" <?= $current === $option ? 'selected' : '' ?>><?= escape($option) ?></option><?php endforeach; ?>
        </select>
        <?php if ($invalid): ?><span class="admin-field__error" id="<?= escape($name) ?>-error"><?= escape((string) $errors[$name]) ?></span><?php endif; ?>
    </div><?php
};

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header admin-settings-page-header"><div><span class="admin-settings-page-header__eyebrow">Administración de tienda</span><h1 class="admin-page-title admin-page-title--paw">Configuración</h1><p>Centraliza la identidad, el contacto y la operación comercial de Coratto Pet.</p></div></header>

    <section class="admin-settings-hero" aria-labelledby="settings-title">
        <div class="admin-settings-hero__identity"><span class="admin-settings-hero__eyebrow">Identidad de tienda</span><h2 id="settings-title"><?= escape((string) $values['nombre_tienda']) ?></h2><p><?= escape((string) ($values['descripcion_breve'] ?: 'Datos generales, contacto y operación comercial en un solo lugar.')) ?></p><span class="admin-settings-hero__seal" aria-hidden="true">CP</span></div>
        <dl><div><dt><span>Estado operativo</span>Modo</dt><dd class="admin-settings-status admin-settings-status--<?= escape((string) $values['modo_tienda']) ?>"><?= escape(ucfirst((string) $values['modo_tienda'])) ?></dd></div><div><dt><span>Canal principal</span>Contacto</dt><dd><?= escape((string) ($values['email_contacto'] ?: $values['whatsapp_principal'] ?: 'Por definir')) ?></dd></div><div><dt><span>Precios y ventas</span>Moneda</dt><dd><?= escape((string) $values['moneda']) ?></dd></div></dl>
    </section>

    <form class="admin-settings-form" method="post" action="<?= escape(appUrl('admin/configuracion/guardar.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <div class="admin-settings-grid">
            <section class="admin-settings-card" aria-labelledby="general-title"><header><span>01</span><div><h2 id="general-title">Datos generales</h2><p>Nombre, identidad y presentación de la tienda.</p></div></header><div class="admin-form-grid">
                <?php $input('nombre_tienda', 'Nombre de la tienda', $values, $errors, 'text', 120, 'Coratto Pet'); $input('razon_social', 'Razón social', $values, $errors, 'text', 160); $input('rut_empresa', 'RUT empresa', $values, $errors, 'text', 20); $textarea('descripcion_breve', 'Descripción breve', $values, $errors, 1000); $textarea('mensaje_principal', 'Mensaje principal del sitio', $values, $errors, 1500); $textarea('mensaje_secundario', 'Texto secundario del sitio', $values, $errors, 1500); ?>
            </div></section>

            <section class="admin-settings-card" aria-labelledby="contact-title"><header><span>02</span><div><h2 id="contact-title">Contacto</h2><p>Canales y datos para atender a tus clientes.</p></div></header><div class="admin-form-grid">
                <?php $input('email_contacto', 'Email de contacto', $values, $errors, 'email', 160, 'hola@corattopet.cl'); $input('whatsapp_principal', 'WhatsApp principal', $values, $errors, 'text', 40); $input('telefono_secundario', 'Teléfono secundario', $values, $errors, 'text', 40); $select('region', 'Región', array_keys($ubicacionesChile), $values, $errors, 'Selecciona una región'); $select('comuna', 'Comuna', $ubicacionesChile[(string) $values['region']] ?? [], $values, $errors, 'Selecciona una comuna'); $textarea('direccion', 'Dirección', $values, $errors, 500); $textarea('horario_atencion', 'Horario de atención', $values, $errors, 1000); ?>
            </div></section>

            <section class="admin-settings-card" aria-labelledby="delivery-title"><header><span>03</span><div><h2 id="delivery-title">Entrega y retiro</h2><p>Opciones operativas y montos base.</p></div></header>
                <div class="admin-settings-toggles"><?php $switch('permite_retiro', 'Retiro en tienda', 'Habilita la coordinación de retiros.', $values); $switch('permite_despacho', 'Despacho', 'Indica que la tienda realiza despachos.', $values); ?></div><div class="admin-form-grid">
                <?php $textarea('texto_retiro', 'Texto de retiro en tienda', $values, $errors, 1500); $textarea('texto_despacho', 'Texto de despacho', $values, $errors, 1500); $input('monto_minimo_compra', 'Monto mínimo de compra', $values, $errors, 'number', 12); $input('costo_despacho_base', 'Costo despacho base', $values, $errors, 'number', 12); $input('despacho_gratis_desde', 'Despacho gratis desde', $values, $errors, 'number', 12); $textarea('tiempo_preparacion', 'Tiempo estimado de preparación', $values, $errors, 500); ?>
            </div></section>

            <section class="admin-settings-card" aria-labelledby="social-title"><header><span>04</span><div><h2 id="social-title">Redes sociales</h2><p>Guarda una URL completa o el usuario de cada red.</p></div></header><div class="admin-form-grid">
                <?php $input('instagram', 'Instagram', $values, $errors, 'text', 255, '@corattopet'); $input('facebook', 'Facebook', $values, $errors, 'text', 255); $input('tiktok', 'TikTok', $values, $errors, 'text', 255); $input('sitio_externo', 'Sitio web externo', $values, $errors, 'text', 255); ?>
            </div></section>

            <section class="admin-settings-card" aria-labelledby="messages-title"><header><span>05</span><div><h2 id="messages-title">Mensajes del sitio</h2><p>Textos preparados para las futuras experiencias públicas.</p></div></header><div class="admin-form-grid">
                <?php $textarea('mensaje_banner', 'Mensaje banner superior', $values, $errors, 1000); $textarea('mensaje_home', 'Mensaje home', $values, $errors, 2000); $textarea('mensaje_checkout', 'Mensaje checkout', $values, $errors, 1500); $textarea('mensaje_post_compra', 'Mensaje post compra', $values, $errors, 2000); $textarea('mensaje_fraccionados', 'Mensaje productos fraccionados', $values, $errors, 2000, 'Los alimentos fraccionados permiten probar nuevas opciones premium...'); ?>
            </div></section>

            <section class="admin-settings-card" aria-labelledby="commercial-title"><header><span>06</span><div><h2 id="commercial-title">Parámetros comerciales</h2><p>Preferencias que usará la tienda más adelante.</p></div></header><div class="admin-form-grid">
                <?php $input('moneda', 'Moneda', $values, $errors, 'text', 10, 'CLP'); ?>
                <div class="admin-field<?= isset($errors['modo_tienda']) ? ' admin-field--invalid' : '' ?>"><label for="modo_tienda">Modo tienda</label><select id="modo_tienda" name="modo_tienda" <?= isset($errors['modo_tienda']) ? 'aria-invalid="true" aria-describedby="modo_tienda-error"' : '' ?>><option value="activa" <?= $values['modo_tienda'] === 'activa' ? 'selected' : '' ?>>Activa</option><option value="mantenimiento" <?= $values['modo_tienda'] === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option></select><?php if (isset($errors['modo_tienda'])): ?><span class="admin-field__error" id="modo_tienda-error"><?= escape((string) $errors['modo_tienda']) ?></span><?php endif; ?></div>
                <div class="admin-settings-toggles admin-field--full"><?php $switch('iva_incluido', 'IVA incluido', 'Los precios configurados incluyen IVA.', $values); $switch('mostrar_stock', 'Mostrar stock', 'Permite informar existencias en la tienda.', $values); $switch('mostrar_sin_stock', 'Mostrar productos sin stock', 'Mantiene visibles productos agotados.', $values); $switch('permitir_venta_sin_stock', 'Permitir venta sin stock', 'Autoriza ventas aunque no haya existencias.', $values); ?></div>
            </div></section>
        </div>
        <div class="admin-settings-actions"><p><strong>Una sola configuración</strong><span>Los cambios quedarán disponibles para futuras integraciones del sitio.</span></p><div><a class="admin-button" href="<?= escape(appUrl('admin/configuracion/index.php')) ?>">Cancelar cambios</a><button class="admin-button admin-button--primary" type="submit">Guardar configuración</button></div></div>
    </form>
</main>
<script>
    (() => {
        const region = document.getElementById('region');
        const comuna = document.getElementById('comuna');
        const locations = <?= json_encode($ubicacionesChile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
        if (!region || !comuna) return;
        const placeholder = 'Selecciona una comuna';
        region.addEventListener('change', () => {
            comuna.replaceChildren(new Option(placeholder, ''));
            for (const name of locations[region.value] || []) comuna.add(new Option(name, name));
            comuna.value = '';
        });
    })();
</script>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
