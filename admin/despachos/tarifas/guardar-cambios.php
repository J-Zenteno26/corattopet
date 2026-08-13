<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/shared/seguridad.php';
require_once $projectRoot . '/config/database.php';
require_once __DIR__ . '/includes/funciones-tarifas-despacho.php';

requireAuthentication();

header('Content-Type: application/json; charset=utf-8');

function responderTarifasJson(
    int $status,
    bool $ok,
    string $message,
    array $extra = []
): never {
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'ok' => $ok,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responderTarifasJson(
        405,
        false,
        'Método no permitido.'
    );
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    responderTarifasJson(
        400,
        false,
        'No se recibieron cambios.'
    );
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    responderTarifasJson(
        400,
        false,
        'La solicitud contiene datos inválidos.'
    );
}

if (!is_array($payload)) {
    responderTarifasJson(
        400,
        false,
        'La solicitud contiene datos inválidos.'
    );
}

if (!validateCsrfToken($payload['csrf_token'] ?? null)) {
    responderTarifasJson(
        419,
        false,
        'La sesión del formulario expiró. Recarga la página.'
    );
}

$changes = $payload['cambios'] ?? null;

if (!is_array($changes) || $changes === []) {
    responderTarifasJson(
        422,
        false,
        'No hay cambios válidos para guardar.'
    );
}

if (count($changes) > 400) {
    responderTarifasJson(
        422,
        false,
        'Se recibieron demasiados cambios en una sola solicitud.'
    );
}

$normalized = [];

foreach ($changes as $change) {
    if (!is_array($change)) {
        responderTarifasJson(
            422,
            false,
            'Uno de los cambios recibidos no es válido.'
        );
    }

    $type = is_scalar($change['tipo'] ?? null)
        ? (string) $change['tipo']
        : '';

    $communeId = idPositivoTarifaDespacho(
        $change['id_comuna'] ?? null
    );

    if ($communeId === null) {
        responderTarifasJson(
            422,
            false,
            'Uno de los cambios no tiene una comuna válida.'
        );
    }

    if ($type === 'tarifa') {
        $weight = filter_var(
            $change['peso_maximo_gramos'] ?? null,
            FILTER_VALIDATE_INT
        );

        $value = filter_var(
            $change['valor'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0,
                    'max_range' => 1000000,
                ],
            ]
        );

        if (
            $weight === false
            || !array_key_exists((int) $weight, TRAMOS_TARIFA_DESPACHO)
            || $value === false
        ) {
            responderTarifasJson(
                422,
                false,
                'Uno de los valores de tarifa no es válido.'
            );
        }

        $key = 'tarifa:' . $communeId . ':' . (int) $weight;

        $normalized[$key] = [
            'tipo' => 'tarifa',
            'id_comuna' => $communeId,
            'peso_maximo_gramos' => (int) $weight,
            'valor' => (int) $value,
        ];

        continue;
    }

    if ($type === 'gratis_desde') {
        $rawAmount = $change['monto_envio_gratis'] ?? null;
        $amount = null;

        if ($rawAmount !== null && $rawAmount !== '') {
            $amount = filter_var(
                $rawAmount,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                        'max_range' => 10000000,
                    ],
                ]
            );

            if ($amount === false) {
                responderTarifasJson(
                    422,
                    false,
                    'Uno de los montos de despacho gratis no es válido.'
                );
            }
        }

        $key = 'gratis_desde:' . $communeId;

        $normalized[$key] = [
            'tipo' => 'gratis_desde',
            'id_comuna' => $communeId,
            'monto_envio_gratis' => $amount,
        ];

        continue;
    }

    if ($type === 'estado') {
        $active = filter_var(
            $change['activo'] ?? null,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($active === null) {
            responderTarifasJson(
                422,
                false,
                'Uno de los estados recibidos no es válido.'
            );
        }

        $key = 'estado:' . $communeId;

        $normalized[$key] = [
            'tipo' => 'estado',
            'id_comuna' => $communeId,
            'activo' => $active,
        ];

        continue;
    }

    responderTarifasJson(
        422,
        false,
        'Se recibió un tipo de cambio desconocido.'
    );
}

if ($normalized === []) {
    responderTarifasJson(
        422,
        false,
        'No hay cambios válidos para guardar.'
    );
}

try {
    $connection = database();
    $connection->beginTransaction();

    $communeStatement = $connection->prepare(
        'SELECT 1
         FROM comunas
         WHERE id_comuna = :id_comuna
           AND activo = TRUE'
    );

    $updateTariffStatement = $connection->prepare(
        'UPDATE tarifas_despacho
         SET
            valor = :valor,
            actualizado_en = NOW()
         WHERE id_comuna = :id_comuna
           AND peso_maximo_gramos = :peso_maximo_gramos'
    );

    $updateStatusStatement = $connection->prepare(
        'UPDATE tarifas_despacho
         SET
            activo = :activo,
            actualizado_en = NOW()
         WHERE id_comuna = :id_comuna
           AND peso_maximo_gramos IN (3000, 6000, 16000, 25000)'
    );

    $updateFreeShippingStatement = $connection->prepare(
        'UPDATE tarifas_despacho
         SET
            monto_envio_gratis = :monto_envio_gratis,
            actualizado_en = NOW()
         WHERE id_comuna = :id_comuna
           AND peso_maximo_gramos IN (3000, 6000, 16000, 25000)'
    );

    $validatedCommunes = [];
    $saved = 0;

    foreach ($normalized as $change) {
        $communeId = $change['id_comuna'];

        if (!isset($validatedCommunes[$communeId])) {
            $communeStatement->bindValue(
                ':id_comuna',
                $communeId,
                PDO::PARAM_INT
            );
            $communeStatement->execute();

            if (!$communeStatement->fetchColumn()) {
                throw new RuntimeException(
                    'Se intentó actualizar una comuna inexistente o inactiva.'
                );
            }

            $validatedCommunes[$communeId] = true;
        }

        if ($change['tipo'] === 'tarifa') {
            $updateTariffStatement->bindValue(
                ':valor',
                $change['valor'],
                PDO::PARAM_INT
            );
            $updateTariffStatement->bindValue(
                ':id_comuna',
                $communeId,
                PDO::PARAM_INT
            );
            $updateTariffStatement->bindValue(
                ':peso_maximo_gramos',
                $change['peso_maximo_gramos'],
                PDO::PARAM_INT
            );
            $updateTariffStatement->execute();

            if ($updateTariffStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se encontró el tramo tarifario que se intentó actualizar.'
                );
            }

            $saved++;
            continue;
        }

        if ($change['tipo'] === 'gratis_desde') {
            if ($change['monto_envio_gratis'] === null) {
                $updateFreeShippingStatement->bindValue(
                    ':monto_envio_gratis',
                    null,
                    PDO::PARAM_NULL
                );
            } else {
                $updateFreeShippingStatement->bindValue(
                    ':monto_envio_gratis',
                    $change['monto_envio_gratis'],
                    PDO::PARAM_INT
                );
            }

            $updateFreeShippingStatement->bindValue(
                ':id_comuna',
                $communeId,
                PDO::PARAM_INT
            );
            $updateFreeShippingStatement->execute();

            if ($updateFreeShippingStatement->rowCount() < 1) {
                throw new RuntimeException(
                    'No se encontraron tarifas para actualizar el despacho gratis de la comuna.'
                );
            }

            $saved++;
            continue;
        }

        $updateStatusStatement->bindValue(
            ':activo',
            $change['activo'],
            PDO::PARAM_BOOL
        );
        $updateStatusStatement->bindValue(
            ':id_comuna',
            $communeId,
            PDO::PARAM_INT
        );
        $updateStatusStatement->execute();

        if ($updateStatusStatement->rowCount() < 1) {
            throw new RuntimeException(
                'No se encontraron tarifas para actualizar el estado de la comuna.'
            );
        }

        $saved++;
    }

    $connection->commit();

    responderTarifasJson(
        200,
        true,
        $saved === 1
            ? 'Se guardó 1 cambio correctamente.'
            : 'Se guardaron ' . $saved . ' cambios correctamente.',
        [
            'guardados' => $saved,
        ]
    );
} catch (Throwable $exception) {
    if (
        isset($connection)
        && $connection instanceof PDO
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Selective shipping tariff save error: '
        . $exception->getMessage()
    );

    responderTarifasJson(
        500,
        false,
        'No fue posible guardar los cambios. Inténtalo nuevamente.'
    );
}
