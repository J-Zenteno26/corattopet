<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

requireAuthentication();
$csrfToken = csrfToken();
$pageTitle = 'Inventario';
$activeSection = 'inventario';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title admin-page-title--paw">Inventario</h1>
            <p>Gestiona el stock y la información de los productos</p>
        </div>
        <div class="admin-actions" aria-label="Acciones de inventario">
            <button class="admin-button admin-button--primary" type="button">Agregar producto</button>
            <button class="admin-button" type="button">Importar Excel</button>
            <button class="admin-button" type="button">Exportar inventario</button>
        </div>
    </header>

    <section class="admin-summary-grid" aria-label="Resumen del inventario">
        <article class="admin-summary-card">
            <span>PRODUCTOS TOTALES</span>
            <strong>—</strong>
        </article>
        <article class="admin-summary-card admin-summary-card--notice">
            <span>STOCK TOTAL</span>
            <strong>—</strong>
        </article>
        <article class="admin-summary-card admin-summary-card--warning">
            <span>STOCK BAJO</span>
            <strong>—</strong>
        </article>
        <article class="admin-summary-card admin-summary-card--warning">
            <span>SIN STOCK</span>
            <strong>—</strong>
        </article>
    </section>
    <section class="admin-panel" aria-label="Listado de inventario">
        <div class="admin-panel__header">
            <h2>Lista de productos</h2>
        </div>

        <div class="admin-toolbar">
            <div class="admin-field admin-field--search">
                <label for="inventory-search">Buscar</label>
                <input id="inventory-search" type="search" placeholder="Buscar por producto, SKU o código">
            </div>
            <div class="admin-field">
                <label for="category-filter">Categoría</label>
                <select id="category-filter">
                    <option>Todas</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="brand-filter">Marca</label>
                <select id="brand-filter">
                    <option>Todas</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="pet-filter">Tipo de mascota</label>
                <select id="pet-filter">
                    <option>Todos</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="stock-filter">Estado de stock</label>
                <select id="stock-filter">
                    <option>Todos</option>
                </select>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">PRODUCTO</th>
                        <th scope="col">SKU</th>
                        <th scope="col">CÓDIGO DE BARRAS</th>
                        <th scope="col">CATEGORÍA</th>
                        <th scope="col">MARCA</th>
                        <th scope="col">MASCOTA</th>
                        <th scope="col">PRECIO</th>
                        <th scope="col">STOCK</th>
                        <th scope="col">ESTADO</th>
                        <th scope="col">ACTUALIZADO</th>
                        <th scope="col">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="admin-empty-state">
                        <td colspan="11">
                            <strong>Aún no hay productos registrados</strong>
                            <span>Los productos aparecerán aquí cuando registres o importes el catálogo.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">
            <p class="admin-pagination__summary">
                Mostrando 0 de 0 productos
            </p>

            <nav class="admin-pagination__pages" aria-label="Paginación del inventario">
                <button type="button" class="admin-pagination__button" disabled aria-label="Página anterior">
                    ‹
                </button>

                <button type="button" class="admin-pagination__button is-active" disabled>
                    1
                </button>

                <button type="button" class="admin-pagination__button" disabled aria-label="Página siguiente">
                    ›
                </button>
            </nav>

            <label class="admin-pagination__size">
                <span>Mostrar</span>

                <select disabled>
                    <option selected>8</option>
                    <option>16</option>
                    <option>24</option>
                </select>

                <span>por página</span>
            </label>
        </div>
    </section>
    <?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>