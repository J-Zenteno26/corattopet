<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__) . '/includes/consultas-publicas.php';
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
    $cliente = exigirClientePublico($pdo, 'public/calculadora.php');
    $payload = json_decode((string) ($_POST['snapshot'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('No se recibió una recomendación válida.');
    }
    $snapshot = validarSnapshotFichaAlimentacion($payload, obtenerProductosCalculadoraPublica($pdo));
    $statement = $pdo->prepare(
        "INSERT INTO fichas_alimentacion_clientes (id_cliente, id_producto, snapshot)
         VALUES (:id_cliente, :id_producto, CAST(:snapshot AS JSONB))
         RETURNING id_ficha"
    );
    $statement->execute([
        'id_cliente' => (int) $cliente['id_cliente'],
        'id_producto' => (int) $snapshot['food']['productId'],
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    $idFicha = (int) $statement->fetchColumn();
    header('Location: ' . appUrl('public/clientes/ficha-alimentacion.php?id=' . $idFicha), true, 303);
    exit;
} catch (InvalidArgumentException | JsonException $exception) {
    $_SESSION['cliente_fichas_estado'] = ['tipo' => 'error', 'mensaje' => $exception->getMessage()];
} catch (Throwable $exception) {
    error_log('Customer feeding sheet save error: ' . $exception->getMessage());
    $_SESSION['cliente_fichas_estado'] = ['tipo' => 'error', 'mensaje' => 'No pudimos guardar la ficha en este momento.'];
}

header('Location: ' . appUrl('public/clientes/fichas-alimentacion.php'), true, 303);
exit;
