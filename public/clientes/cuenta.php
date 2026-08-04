<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
if(!isset($_SESSION['id_cliente'])){header('Location: '.appUrl('public/clientes/login.php'),true,302);exit;}
renderPublicPageStart('Mi cuenta | Coratto Pet','Tu cuenta de cliente Coratto Pet.','cuenta');
?><main id="contenido"><div class="public-shell"><section class="public-hero-card"><span class="public-eyebrow">MI CUENTA</span><h1>Hola, <?=e((string)($_SESSION['cliente_nombre']??''))?></h1><p><?=e((string)($_SESSION['cliente_email']??''))?></p><p>Próximamente podrás revisar tus pedidos y preferencias desde este espacio.</p><form method="post" action="<?=e(appUrl('public/clientes/logout.php'))?>"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="button" type="submit">Cerrar sesión</button></form></section></div></main><?php renderPublicPageEnd(); ?>
