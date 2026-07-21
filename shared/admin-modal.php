<?php

declare(strict_types=1);

$adminModalConfig = isset($adminModal) && is_array($adminModal) ? $adminModal : null;
?>
<div class="admin-modal" data-admin-modal aria-hidden="true">
    <div class="admin-modal__overlay" data-admin-modal-overlay></div>
    <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title" aria-describedby="admin-modal-message" tabindex="-1">
        <button class="admin-modal__close" type="button" data-admin-modal-close aria-label="Cerrar mensaje">×</button>
        <div class="admin-modal__header">
            <span class="admin-modal__icon" data-admin-modal-icon aria-hidden="true"></span>
            <h2 class="admin-modal__title" id="admin-modal-title" data-admin-modal-title></h2>
        </div>
        <div class="admin-modal__body">
            <p class="admin-modal__message" id="admin-modal-message" data-admin-modal-message></p>
            <p class="admin-modal__reference" data-admin-modal-reference-wrap hidden>Referencia: <strong data-admin-modal-reference></strong></p>
            <div class="admin-modal__detail" data-admin-modal-detail-wrap hidden><strong>Detalle técnico</strong><p data-admin-modal-detail></p></div>
        </div>
        <div class="admin-modal__actions">
            <button class="admin-modal__button admin-modal__button--secondary" type="button" data-admin-modal-secondary hidden></button>
            <a class="admin-modal__button admin-modal__button--primary" data-admin-modal-primary href="#"></a>
        </div>
    </div>
</div>
<?php if ($adminModalConfig !== null): ?>
<script id="admin-modal-auto-config" type="application/json"><?= json_encode($adminModalConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
