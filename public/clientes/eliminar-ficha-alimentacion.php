<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

try {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException('La sesión del formulario expiró.');
    }
    $pdo = database();
    $cliente = exigirClientePublico($pdo, 'public/clientes/fichas-alimentacion.php');
    $idFicha = filter_var($_POST['id_ficha'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idFicha === false) {
        throw new InvalidArgumentException('La ficha indicada no es válida.');
    }
    $statement = $pdo->prepare(
        "DELETE FROM fichas_alimentacion_clientes
         WHERE id_ficha = :id_ficha AND id_cliente = :id_cliente"
    );
    $statement->execute(['id_ficha' => (int) $idFicha, 'id_cliente' => (int) $cliente['id_cliente']]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('La ficha no existe o no pertenece a tu cuenta.');
    }
    $_SESSION['cliente_fichas_estado'] = ['tipo' => 'ok', 'mensaje' => 'La ficha fue eliminada.'];
} catch (InvalidArgumentException $exception) {
    $_SESSION['cliente_fichas_estado'] = ['tipo' => 'error', 'mensaje' => $exception->getMessage()];
} catch (Throwable $exception) {
    error_log('Customer feeding sheet delete error: ' . $exception->getMessage());
    $_SESSION['cliente_fichas_estado'] = ['tipo' => 'error', 'mensaje' => 'No pudimos eliminar la ficha en este momento.'];
}

header('Location: ' . appUrl('public/clientes/fichas-alimentacion.php'), true, 303);
exit;
