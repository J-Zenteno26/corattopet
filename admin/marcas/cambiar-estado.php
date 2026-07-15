<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php'; require_once dirname(__DIR__, 2) . '/config/database.php'; require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php'; require_once __DIR__ . '/includes/funciones-marca.php'; requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; } if (!validateCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); exit; }
$id = idPositivoMarca($_POST['id_marca'] ?? null); if ($id === null) { http_response_code(404); exit; }
try { $statement = database()->prepare('UPDATE marcas SET activo = NOT activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_marca = :id RETURNING id_marca, activo'); $statement->execute(['id' => $id]); $result = $statement->fetch(); if (!is_array($result)) { http_response_code(404); exit; } header('Location: ' . appUrl('admin/marcas/index.php?mensaje=' . (booleanoPostgresMantenedor($result['activo']) ? 'activada' : 'desactivada')), true, 303); exit; } catch (Throwable $exception) { error_log('Brand status error: ' . $exception->getMessage()); header('Location: ' . appUrl('admin/marcas/index.php?mensaje=error'), true, 303); exit; }
