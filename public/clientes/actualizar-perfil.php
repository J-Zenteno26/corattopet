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
    $cliente = exigirClientePublico($pdo, 'public/clientes/perfil.php');

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $rut = trim((string) ($_POST['rut'] ?? ''));
    $direccion = trim((string) ($_POST['direccion'] ?? ''));
    $comuna = trim((string) ($_POST['comuna'] ?? ''));
    $region = trim((string) ($_POST['region'] ?? ''));

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new InvalidArgumentException('Ingresa un nombre válido.');
    }

    if ($apellido === '' || mb_strlen($apellido) > 100) {
        throw new InvalidArgumentException('Ingresa un apellido válido.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
        throw new InvalidArgumentException('Ingresa un correo válido.');
    }

    if (mb_strlen($telefono) > 40 || mb_strlen($rut) > 20) {
        throw new InvalidArgumentException('Revisa los datos de contacto ingresados.');
    }

    if (
        mb_strlen($direccion) > 500
        || mb_strlen($comuna) > 100
        || mb_strlen($region) > 100
    ) {
        throw new InvalidArgumentException('Uno de los datos de dirección es demasiado largo.');
    }

    $duplicate = $pdo->prepare(
        "SELECT 1
         FROM clientes
         WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
           AND id_cliente <> :id_cliente
         LIMIT 1"
    );
    $duplicate->execute([
        'email' => $email,
        'id_cliente' => (int) $cliente['id_cliente'],
    ]);

    if ($duplicate->fetchColumn() !== false) {
        throw new InvalidArgumentException('Ese correo ya pertenece a otra cuenta.');
    }

    $statement = $pdo->prepare(
        "UPDATE clientes
         SET
            nombre = :nombre,
            apellido = :apellido,
            email = :email,
            telefono = :telefono,
            rut = :rut,
            direccion = :direccion,
            comuna = :comuna,
            region = :region,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_cliente = :id_cliente"
    );
    $statement->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'telefono' => $telefono !== '' ? $telefono : null,
        'rut' => $rut !== '' ? $rut : null,
        'direccion' => $direccion !== '' ? $direccion : null,
        'comuna' => $comuna !== '' ? $comuna : null,
        'region' => $region !== '' ? $region : null,
        'id_cliente' => (int) $cliente['id_cliente'],
    ]);

    $_SESSION['cliente_nombre'] = $nombre;
    $_SESSION['cliente_email'] = $email;
    $_SESSION['cliente_perfil_estado'] = [
        'tipo' => 'ok',
        'mensaje' => 'Tus datos fueron actualizados correctamente.',
    ];
} catch (InvalidArgumentException $exception) {
    $_SESSION['cliente_perfil_estado'] = [
        'tipo' => 'error',
        'mensaje' => $exception->getMessage(),
    ];
} catch (Throwable $exception) {
    error_log('Customer public profile update error: ' . $exception->getMessage());
    $_SESSION['cliente_perfil_estado'] = [
        'tipo' => 'error',
        'mensaje' => 'No pudimos actualizar tus datos en este momento.',
    ];
}

header('Location: ' . appUrl('public/clientes/perfil.php'), true, 303);
exit;
