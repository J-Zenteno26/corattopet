<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
requireAuthentication();
header('Location: ' . appUrl('admin/marcas/index.php'), true, 302);
exit;
