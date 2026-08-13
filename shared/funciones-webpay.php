<?php

declare(strict_types=1);

use Transbank\Webpay\WebpayPlus\Transaction;

const WEBPAY_INTEGRATION_COMMERCE_CODE = '597055555532';
const WEBPAY_INTEGRATION_API_KEY = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';

/**
 * Obtiene una variable de entorno sin depender de una librería adicional.
 */
function webpayEnv(string $nombre, ?string $predeterminado = null): ?string
{
    $valor = $_ENV[$nombre]
        ?? $_SERVER[$nombre]
        ?? getenv($nombre);

    if (!is_string($valor)) {
        return $predeterminado;
    }

    $valor = trim($valor);

    return $valor !== '' ? $valor : $predeterminado;
}

/**
 * Carga Composer y construye la transacción para integración o producción.
 */
function webpayTransaction(): Transaction
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($autoload)) {
        throw new RuntimeException(
            'No encontramos vendor/autoload.php. Ejecuta composer install.'
        );
    }

    require_once $autoload;

    $environment = strtolower(
        webpayEnv('WEBPAY_ENVIRONMENT', 'integration') ?? 'integration'
    );

    if ($environment === 'production') {
        $commerceCode = webpayEnv('WEBPAY_COMMERCE_CODE');
        $apiKey = webpayEnv('WEBPAY_API_KEY');

        if ($commerceCode === null || $apiKey === null) {
            throw new RuntimeException(
                'Faltan las credenciales productivas de Webpay.'
            );
        }

        return Transaction::buildForProduction(
            $apiKey,
            $commerceCode
        );
    }

    return Transaction::buildForIntegration(
        webpayEnv(
            'WEBPAY_API_KEY',
            WEBPAY_INTEGRATION_API_KEY
        ) ?? WEBPAY_INTEGRATION_API_KEY,
        webpayEnv(
            'WEBPAY_COMMERCE_CODE',
            WEBPAY_INTEGRATION_COMMERCE_CODE
        ) ?? WEBPAY_INTEGRATION_COMMERCE_CODE
    );
}

/**
 * URL pública que Transbank utilizará para devolver el control.
 */
function webpayReturnUrl(): string
{
    return webpayEnv(
        'WEBPAY_RETURN_URL',
        appUrl('public/webpay/retorno.php')
    ) ?? appUrl('public/webpay/retorno.php');
}

/**
 * Genera identificadores válidos para Webpay.
 */
function generarIdentificadoresWebpay(
    int $idPedido,
    int $numeroIntento
): array {
    $sufijo = strtoupper(bin2hex(random_bytes(3)));

    return [
        'buy_order' => sprintf(
            'WP-%d-%d-%s',
            $idPedido,
            $numeroIntento,
            $sufijo
        ),
        'session_id' => sprintf(
            'COR-%d-%s',
            $idPedido,
            bin2hex(random_bytes(12))
        ),
    ];
}

/**
 * Convierte el objeto de respuesta del SDK en un arreglo serializable.
 */
function respuestaCommitWebpayAArray(object $response): array
{
    return [
        'status' => $response->getStatus(),
        'response_code' => $response->getResponseCode(),
        'amount' => $response->getAmount(),
        'authorization_code' => $response->getAuthorizationCode(),
        'payment_type_code' => $response->getPaymentTypeCode(),
        'accounting_date' => $response->getAccountingDate(),
        'installments_number' => $response->getInstallmentsNumber(),
        'installments_amount' => $response->getInstallmentsAmount(),
        'session_id' => $response->getSessionId(),
        'buy_order' => $response->getBuyOrder(),
        'card_number' => $response->getCardNumber(),
        'transaction_date' => $response->getTransactionDate(),
        'vci' => method_exists($response, 'getVci')
            ? $response->getVci()
            : null,
        'approved' => method_exists($response, 'isApproved')
            ? $response->isApproved()
            : false,
    ];
}

/**
 * Traduce el código de tipo de pago a una etiqueta comprensible.
 */
function etiquetaTipoPagoWebpay(?string $codigo): string
{
    return match ($codigo) {
        'VD' => 'Débito',
        'VN' => 'Crédito sin cuotas',
        'VC' => 'Crédito en cuotas',
        'SI' => 'Crédito en cuotas sin interés',
        'S2' => 'Crédito en 2 cuotas sin interés',
        'NC' => 'Crédito en cuotas',
        'VP' => 'Prepago',
        default => 'Tarjeta',
    };
}
