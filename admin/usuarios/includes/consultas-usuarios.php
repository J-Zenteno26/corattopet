<?php

declare(strict_types=1);

/** @return array<int, array<string, mixed>> */
function listarUsuarios(PDO $connection, string $search = '', string $status = '', string $role = ''): array
{
    $conditions = [];
    $parameters = [];
    if ($search !== '') {
        $conditions[] = '(nombre ILIKE :search OR email ILIKE :search)';
        $parameters['search'] = '%' . $search . '%';
    }
    if ($status === 'activo' || $status === 'inactivo') {
        $conditions[] = 'activo = :activo';
    }
    if (array_key_exists($role, rolesUsuarios())) {
        $conditions[] = 'rol = :rol';
        $parameters['rol'] = $role;
    }
    $sql = 'SELECT id_usuario, nombre, email, rol, activo, ultimo_acceso, creado_en, actualizado_en FROM usuarios';
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY activo DESC, nombre ASC, id_usuario ASC';
    $statement = $connection->prepare($sql);
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value);
    }
    if ($status === 'activo' || $status === 'inactivo') {
        $statement->bindValue(':activo', $status === 'activo', PDO::PARAM_BOOL);
    }
    $statement->execute();
    return $statement->fetchAll();
}

/** @return array<string, int> */
function resumenUsuarios(PDO $connection): array
{
    $row = $connection->query("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE activo) AS activos, COUNT(*) FILTER (WHERE NOT activo) AS inactivos, COUNT(*) FILTER (WHERE rol = 'administrador') AS administradores FROM usuarios")->fetch();
    return ['total' => (int) ($row['total'] ?? 0), 'activos' => (int) ($row['activos'] ?? 0), 'inactivos' => (int) ($row['inactivos'] ?? 0), 'administradores' => (int) ($row['administradores'] ?? 0)];
}

/** @return array<string, mixed>|null */
function obtenerUsuarioPorId(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id_usuario, nombre, email, rol, activo, ultimo_acceso, creado_en, actualizado_en FROM usuarios WHERE id_usuario = :id_usuario LIMIT 1');
    $statement->bindValue(':id_usuario', $id, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function existeEmailUsuario(PDO $connection, string $email, ?int $excludedId = null): bool
{
    $sql = 'SELECT 1 FROM usuarios WHERE LOWER(email) = LOWER(:email)';
    if ($excludedId !== null) {
        $sql .= ' AND id_usuario <> :excluded_id';
    }
    $statement = $connection->prepare($sql . ' LIMIT 1');
    $statement->bindValue(':email', $email);
    if ($excludedId !== null) {
        $statement->bindValue(':excluded_id', $excludedId, PDO::PARAM_INT);
    }
    $statement->execute();
    return $statement->fetchColumn() !== false;
}

function contarUsuariosActivos(PDO $connection): int
{
    return (int) $connection->query('SELECT COUNT(*) FROM usuarios WHERE activo')->fetchColumn();
}

