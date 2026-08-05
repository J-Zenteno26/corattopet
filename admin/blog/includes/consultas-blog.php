<?php

declare(strict_types=1);

function obtenerMetricasBlog(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE estado = 'publicado') AS publicados,
            COUNT(*) FILTER (WHERE estado = 'borrador') AS borradores,
            COUNT(*) FILTER (WHERE destacado = TRUE) AS destacados
        FROM blog_articulos"
    );
    $statement->execute();
    $row = $statement->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'publicados' => (int) ($row['publicados'] ?? 0),
        'borradores' => (int) ($row['borradores'] ?? 0),
        'destacados' => (int) ($row['destacados'] ?? 0),
    ];
}

function listarCategoriasFiltroBlog(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT id_categoria_blog, nombre
         FROM blog_categorias
         ORDER BY orden ASC, nombre ASC, id_categoria_blog ASC'
    );
    $statement->execute();

    return $statement->fetchAll();
}

function listarCategoriasActivasBlog(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT id_categoria_blog, nombre
         FROM blog_categorias
         WHERE activo = TRUE
         ORDER BY orden ASC, nombre ASC, id_categoria_blog ASC'
    );
    $statement->execute();

    return $statement->fetchAll();
}

function listarCategoriasEdicionBlog(PDO $connection, int $currentCategoryId): array
{
    $statement = $connection->prepare(
        'SELECT id_categoria_blog, nombre, activo
         FROM blog_categorias
         WHERE activo = TRUE OR id_categoria_blog = :id_categoria_blog
         ORDER BY orden ASC, nombre ASC, id_categoria_blog ASC'
    );
    $statement->bindValue(':id_categoria_blog', $currentCategoryId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function categoriaActivaBlogExiste(PDO $connection, int $categoryId): bool
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM blog_categorias
         WHERE id_categoria_blog = :id_categoria_blog AND activo = TRUE
         LIMIT 1'
    );
    $statement->bindValue(':id_categoria_blog', $categoryId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchColumn() !== false;
}

function categoriaDisponibleEdicionBlog(PDO $connection, int $categoryId, int $currentCategoryId): bool
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM blog_categorias
         WHERE id_categoria_blog = :id_categoria_blog
           AND (activo = TRUE OR id_categoria_blog = :categoria_actual)
         LIMIT 1'
    );
    $statement->bindValue(':id_categoria_blog', $categoryId, PDO::PARAM_INT);
    $statement->bindValue(':categoria_actual', $currentCategoryId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchColumn() !== false;
}

function obtenerArticuloEdicionBlog(PDO $connection, int $articleId): ?array
{
    $statement = $connection->prepare(
        'SELECT
            id_articulo,
            id_categoria_blog,
            titulo,
            slug,
            extracto,
            imagen_portada,
            video_url,
            contenido_html,
            autor_publico,
            estado
         FROM blog_articulos
         WHERE id_articulo = :id_articulo
         LIMIT 1'
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
    $article = $statement->fetch();

    return is_array($article) ? $article : null;
}

function slugBlogExiste(PDO $connection, string $slug, int $excludedArticleId): bool
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM blog_articulos
         WHERE LOWER(slug) = LOWER(:slug) AND id_articulo <> :id_articulo
         LIMIT 1'
    );
    $statement->bindValue(':slug', $slug);
    $statement->bindValue(':id_articulo', $excludedArticleId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchColumn() !== false;
}

function actualizarArticuloBlog(PDO $connection, int $articleId, array $values, string $slug): void
{
    $statement = $connection->prepare(
        'UPDATE blog_articulos
         SET titulo = :titulo,
             slug = :slug,
             id_categoria_blog = :id_categoria_blog,
             extracto = :extracto,
             video_url = :video_url,
             contenido_html = :contenido_html,
             autor_publico = :autor_publico,
             actualizado_en = NOW()
         WHERE id_articulo = :id_articulo'
    );
    $statement->bindValue(':titulo', $values['titulo']);
    $statement->bindValue(':slug', $slug);
    $statement->bindValue(':id_categoria_blog', $values['id_categoria_blog'], PDO::PARAM_INT);
    $statement->bindValue(':extracto', $values['extracto']);
    $statement->bindValue(
        ':video_url',
        $values['video_url'],
        $values['video_url'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
    $statement->bindValue(':contenido_html', $values['contenido_html']);
    $statement->bindValue(':autor_publico', $values['autor_publico']);
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
}

function actualizarPortadaArticuloBlog(PDO $connection, int $articleId, string $relativePath): void
{
    $statement = $connection->prepare(
        'UPDATE blog_articulos
         SET imagen_portada = :imagen_portada,
             actualizado_en = NOW()
         WHERE id_articulo = :id_articulo'
    );
    $statement->bindValue(':imagen_portada', $relativePath);
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
}

function generarSlugUnicoBlog(PDO $connection, string $requestedSlug): string
{
    $base = normalizarSlugBlog($requestedSlug);
    $statement = $connection->prepare(
        "SELECT slug
         FROM blog_articulos
         WHERE LOWER(slug) = :base OR LOWER(slug) LIKE :pattern ESCAPE '\\'"
    );
    $statement->execute([
        'base' => $base,
        'pattern' => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base) . '-%',
    ]);

    return construirSlugDisponibleBlog($base, $statement->fetchAll(PDO::FETCH_COLUMN));
}

function insertarBorradorBlog(PDO $connection, array $values, int $authorId, string $slug): int
{
    $statement = $connection->prepare(
        "INSERT INTO blog_articulos (
            id_categoria_blog,
            id_autor,
            titulo,
            slug,
            extracto,
            video_url,
            contenido_html,
            autor_publico,
            estado,
            destacado,
            fecha_publicacion
         ) VALUES (
            :id_categoria_blog,
            :id_autor,
            :titulo,
            :slug,
            :extracto,
            :video_url,
            :contenido_html,
            :autor_publico,
            'borrador',
            FALSE,
            NULL
         )
         RETURNING id_articulo"
    );
    $statement->bindValue(':id_categoria_blog', $values['id_categoria_blog'], PDO::PARAM_INT);
    $statement->bindValue(':id_autor', $authorId, PDO::PARAM_INT);
    $statement->bindValue(':titulo', $values['titulo']);
    $statement->bindValue(':slug', $slug);
    $statement->bindValue(':extracto', $values['extracto']);
    $statement->bindValue(
        ':video_url',
        $values['video_url'],
        $values['video_url'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
    $statement->bindValue(':contenido_html', $values['contenido_html']);
    $statement->bindValue(':autor_publico', $values['autor_publico']);
    $statement->execute();

    return (int) $statement->fetchColumn();
}

function listarArticulosBlog(PDO $connection, array $filters): array
{
    [$whereSql, $bindings] = construirFiltrosSqlBlog($filters);
    $countStatement = $connection->prepare(
        'SELECT COUNT(a.id_articulo) FROM blog_articulos a' . $whereSql
    );
    ejecutarConsultaBlog($countStatement, $bindings);
    $totalRecords = (int) $countStatement->fetchColumn();
    $perPage = $filters['por_pagina'];
    $totalPages = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = min($filters['pagina'], $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    $statement = $connection->prepare(
        'SELECT
            a.id_articulo,
            a.titulo,
            a.extracto,
            a.imagen_portada,
            a.estado,
            a.destacado,
            a.fecha_publicacion,
            c.nombre AS categoria,
            u.nombre AS autor
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         INNER JOIN usuarios u ON u.id_usuario = a.id_autor'
        . $whereSql
        . ' ORDER BY a.fecha_publicacion DESC NULLS LAST, a.actualizado_en DESC, a.id_articulo DESC
            LIMIT :limit OFFSET :offset'
    );
    ejecutarConsultaBlog($statement, $bindings, $perPage, $offset);

    return [
        'registros' => $statement->fetchAll(),
        'total_registros' => $totalRecords,
        'total_paginas' => $totalPages,
        'pagina_actual' => $currentPage,
        'por_pagina' => $perPage,
    ];
}

function construirFiltrosSqlBlog(array $filters): array
{
    $where = [];
    $bindings = [];

    if ($filters['buscar'] !== '') {
        $where[] = "a.titulo ILIKE :buscar ESCAPE '\\'";
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['buscar']);
        $bindings['buscar'] = '%' . $escapedSearch . '%';
    }

    if ($filters['estado'] !== '') {
        $where[] = 'a.estado = :estado';
        $bindings['estado'] = $filters['estado'];
    }

    if ($filters['id_categoria_blog'] !== null) {
        $where[] = 'a.id_categoria_blog = :id_categoria_blog';
        $bindings['id_categoria_blog'] = $filters['id_categoria_blog'];
    }

    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $bindings];
}

function ejecutarConsultaBlog(
    PDOStatement $statement,
    array $bindings,
    ?int $limit = null,
    ?int $offset = null
): void {
    foreach ($bindings as $name => $value) {
        $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    if ($limit !== null) {
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    if ($offset !== null) {
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $statement->execute();
}

function obtenerArticuloVistaPreviaBlog(PDO $connection, int $articleId): ?array
{
    $statement = $connection->prepare(
        'SELECT
            a.id_articulo,
            a.titulo,
            a.extracto,
            a.contenido_html,
            a.imagen_portada,
            a.video_url,
            a.autor_publico,
            a.estado,
            c.nombre AS categoria,
            u.nombre AS autor
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         INNER JOIN usuarios u ON u.id_usuario = a.id_autor
         WHERE a.id_articulo = :id_articulo
         LIMIT 1'
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
    $article = $statement->fetch();

    return is_array($article) ? $article : null;
}

function obtenerEstadoArticuloBlog(PDO $connection, int $articleId): ?string
{
    $statement = $connection->prepare(
        'SELECT estado
         FROM blog_articulos
         WHERE id_articulo = :id_articulo
         LIMIT 1'
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
    $state = $statement->fetchColumn();

    return is_string($state) ? $state : null;
}

function publicarBorradorBlog(PDO $connection, int $articleId): bool
{
    $statement = $connection->prepare(
        "UPDATE blog_articulos
         SET estado = 'publicado',
             fecha_publicacion = COALESCE(fecha_publicacion, NOW()),
             actualizado_en = NOW()
         WHERE id_articulo = :id_articulo AND estado = 'borrador'"
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->rowCount() === 1;
}

function archivarArticuloBlog(PDO $connection, int $articleId): bool
{
    $statement = $connection->prepare(
        "UPDATE blog_articulos
         SET estado = 'archivado',
             destacado = FALSE,
             actualizado_en = NOW()
         WHERE id_articulo = :id_articulo AND estado IN ('borrador', 'publicado')"
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->rowCount() === 1;
}


function obtenerArticuloDestacadoBlog(PDO $connection, int $articleId): ?array
{
    $lockSql = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? ' FOR UPDATE' : '';
    $statement = $connection->prepare(
        'SELECT estado, destacado
         FROM blog_articulos
         WHERE id_articulo = :id_articulo
         LIMIT 1' . $lockSql
    );
    $statement->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $statement->execute();
    $article = $statement->fetch();

    return is_array($article) ? $article : null;
}

function bloquearDestacadosBlog(PDO $connection): void
{
    if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        $connection->exec('LOCK TABLE blog_articulos IN SHARE ROW EXCLUSIVE MODE');
    }
}

function quitarDestacadoBlog(PDO $connection): void
{
    $statement = $connection->prepare(
        'UPDATE blog_articulos
         SET destacado = FALSE, actualizado_en = NOW()
         WHERE destacado = TRUE'
    );
    $statement->execute();
}

function destacarArticuloBlog(PDO $connection, int $articleId): void
{
    $clear = $connection->prepare(
        'UPDATE blog_articulos
         SET destacado = FALSE, actualizado_en = NOW()
         WHERE destacado = TRUE AND id_articulo <> :id_articulo'
    );
    $clear->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $clear->execute();

    $feature = $connection->prepare(
        "UPDATE blog_articulos
         SET destacado = TRUE, actualizado_en = NOW()
         WHERE id_articulo = :id_articulo AND estado = 'publicado'"
    );
    $feature->bindValue(':id_articulo', $articleId, PDO::PARAM_INT);
    $feature->execute();
    if ($feature->rowCount() !== 1) {
        throw new RuntimeException('El artículo no está disponible para destacar.');
    }
}
