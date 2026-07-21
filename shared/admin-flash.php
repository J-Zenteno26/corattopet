<?php

declare(strict_types=1);

/** @param array<string, mixed> $options */
function guardarModalAdmin(string $type, string $title, string $message, array $options = []): void
{
    $allowedTypes = ['success', 'error', 'warning', 'info', 'confirm'];
    $_SESSION['admin_modal_flash'] = array_merge($options, [
        'type' => in_array($type, $allowedTypes, true) ? $type : 'info',
        'title' => $title,
        'message' => $message,
    ]);
}

/** @return array<string, mixed>|null */
function consumirModalAdmin(): ?array
{
    $modal = $_SESSION['admin_modal_flash'] ?? null;
    unset($_SESSION['admin_modal_flash']);

    return is_array($modal) ? $modal : null;
}
