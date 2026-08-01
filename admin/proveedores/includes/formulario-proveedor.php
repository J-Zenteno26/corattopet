<?php
$supplierSections = [
    ['key' => 'general', 'title' => 'Datos generales', 'icon' => 'bi-building', 'subtitle' => 'Identificación y ubicación comercial.', 'fields' => [
        ['nombre', 'Nombre', 'text', 160, 'wide'], ['razon_social', 'Razón social', 'text', 180, 'wide'],
        ['rut', 'RUT', 'text', 20, 'short'], ['giro', 'Giro', 'text', 180, ''],
        ['direccion', 'Dirección', 'textarea', 0, 'wide'], ['comuna', 'Comuna', 'text', 100, ''], ['region', 'Región', 'text', 100, ''],
    ]],
    ['key' => 'contact', 'title' => 'Contacto', 'icon' => 'bi-person-lines-fill', 'subtitle' => 'Canales y responsables de coordinación.', 'fields' => [
        ['contacto_principal', 'Contacto principal', 'text', 120, 'wide'], ['telefono', 'Teléfono', 'tel', 40, ''],
        ['email', 'Email', 'email', 160, 'wide'], ['sitio_web', 'Sitio web', 'url', 220, 'wide'],
        ['instagram', 'Instagram', 'text', 120, ''], ['contacto_ventas', 'Contacto ventas', 'text', 160, ''],
        ['contacto_cobranza', 'Contacto cobranza', 'text', 160, 'wide'],
    ]],
    ['key' => 'commercial', 'title' => 'Datos comerciales', 'icon' => 'bi-receipt', 'subtitle' => 'Condiciones operativas y de pago.', 'fields' => [
        ['condicion_pago', 'Condición de pago', 'text', 120, 'wide'], ['plazo_pago_dias', 'Plazo de pago (días)', 'number', 0, 'short'],
        ['metodo_pago', 'Método de pago', 'text', 120, ''], ['dias_despacho', 'Días de despacho', 'text', 160, 'wide'],
        ['monto_minimo_compra', 'Monto mínimo de compra', 'number', 0, 'short'],
    ]],
];
?>
<form class="admin-provider-form" method="post" action="<?= escape($formAction) ?>">
    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
    <?php if (isset($providerId)): ?><input type="hidden" name="id_proveedor" value="<?= (int) $providerId ?>"><?php endif; ?>

    <div class="admin-supplier-grid">
        <?php foreach ($supplierSections as $section): ?>
            <section class="admin-supplier-card admin-supplier-card--<?= escape($section['key']) ?>">
                <header class="admin-supplier-card__header">
                    <span class="admin-supplier-card__icon"><i class="bi <?= escape($section['icon']) ?>" aria-hidden="true"></i></span>
                    <div><h2 class="admin-supplier-card__title"><?= escape($section['title']) ?></h2><p class="admin-supplier-card__subtitle"><?= escape($section['subtitle']) ?></p></div>
                </header>
                <div class="admin-supplier-card__body">
                    <?php foreach ($section['fields'] as [$name, $label, $type, $max, $width]): ?>
                        <div class="admin-field<?= isset($errors[$name]) ? ' admin-field--invalid' : '' ?><?= $width !== '' ? ' admin-supplier-field--' . escape($width) : '' ?>">
                            <label for="<?= escape($name) ?>"><?= escape($label) ?><?= $name === 'nombre' ? ' *' : '' ?></label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea id="<?= escape($name) ?>" name="<?= escape($name) ?>" rows="2"><?= escape((string) $values[$name]) ?></textarea>
                            <?php else: ?>
                                <input id="<?= escape($name) ?>" name="<?= escape($name) ?>" type="<?= escape($type) ?>" <?= $max ? 'maxlength="' . $max . '"' : '' ?> <?= $name === 'nombre' ? 'required' : '' ?> <?= $type === 'number' ? 'min="0" step="' . ($name === 'monto_minimo_compra' ? '0.01' : '1') . '"' : '' ?> value="<?= escape((string) $values[$name]) ?>">
                            <?php endif; ?>
                            <?php if (isset($errors[$name])): ?><span class="admin-field__error"><?= escape($errors[$name]) ?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($section['key'] === 'commercial'): ?>
                        <div class="admin-provider-status"><div><strong>Estado del proveedor</strong><span>Permite utilizarlo en productos e ingresos de stock.</span></div><label class="admin-switch"><input name="activo" type="checkbox" value="1" <?= $values['activo'] ? 'checked' : '' ?>><span class="admin-switch__track" aria-hidden="true"></span><span class="admin-switch__label">Activo</span></label></div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="admin-supplier-card admin-supplier-card--notes">
            <header class="admin-supplier-card__header"><span class="admin-supplier-card__icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span><div><h2 class="admin-supplier-card__title">Observaciones</h2><p class="admin-supplier-card__subtitle">Información útil para el equipo Coratto.</p></div></header>
            <div class="admin-supplier-card__body admin-supplier-card__body--notes">
                <div class="admin-field"><label for="observaciones">Notas internas</label><textarea id="observaciones" name="observaciones" rows="6" placeholder="Acuerdos, horarios, condiciones especiales o datos relevantes."><?= escape((string) $values['observaciones']) ?></textarea></div>
                <div class="admin-supplier-note"><i class="bi bi-info-circle" aria-hidden="true"></i><p><strong>Uso interno</strong> Estas notas solo son visibles para el equipo administrativo.</p></div>
            </div>
        </section>
    </div>

    <div class="admin-provider-form-actions"><a class="admin-button" href="<?= escape(appUrl('admin/proveedores/index.php')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancelar</a><button class="admin-button admin-button--primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= escape($submitLabel) ?></button></div>
</form>
