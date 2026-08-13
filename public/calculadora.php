<?php

declare(strict_types=1);

require __DIR__ . '/includes/public-page-bootstrap.php';

$calculatorProducts = [];
$calculatorUnavailable = false;
if ($pdo instanceof PDO) {
    try {
        $calculatorProducts = obtenerProductosCalculadoraPublica($pdo);
    } catch (Throwable $exception) {
        error_log('Public nutrition calculator products error: ' . $exception->getMessage());
        $calculatorUnavailable = true;
    }
} else {
    $calculatorUnavailable = true;
}

renderPublicPageStart(
    'Calculadora nutricional | Coratto Pet',
    'Estima las necesidades energéticas de tu mascota y conoce alimentos compatibles del catálogo Coratto Pet.',
    'calculadora'
);
$calculatorJson = json_encode($calculatorProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<link rel="stylesheet"
    href="<?= e(appUrl('public/assets/css/calculadora.css?v=' . filemtime(__DIR__ . '/assets/css/calculadora.css'))) ?>">
<main id="contenido" class="nutrition-calculator-page">
    <section class="nutrition-calculator-hero">
        <div class="container">
            <span>ORIENTACIÓN NUTRICIONAL</span>

            <h1>Calcula una pauta estimada para tu mascota</h1>

            <p>
                Cuéntanos su edad, peso y condición actual. Calcularemos una referencia diaria
                y buscaremos alimentos del catálogo Coratto compatibles con su perfil.
            </p>

            <div class="nutrition-calculator-hero-points">
                <span>Energía diaria</span>
                <span>Porción estimada</span>
                <span>Alimentos compatibles</span>
            </div>
        </div>
    </section>
    <section class="container nutrition-calculator-layout">
        <form id="nutrition-calculator-form" class="nutrition-calculator-form" novalidate>
            <header><span>PASO ÚNICO</span>
                <h2>Perfil de tu mascota</h2>
                <p>Completa sus datos actuales. Los campos marcados son necesarios para calcular.</p>
            </header>
            <div class="nutrition-form-grid">
                <label class="nutrition-field"><span>Nombre de tu mascota <small>opcional</small></span><input
                        name="petName" maxlength="60" autocomplete="off"></label>
                <label class="nutrition-field"><span>Mascota *</span><select name="species" required>
                        <option value="">Seleccionar</option>
                        <option value="perro">Perro</option>
                        <option value="gato">Gato</option>
                    </select></label>
                <label class="nutrition-field"><span>Sexo *</span><select name="sex" required>
                        <option value="">Seleccionar</option>
                        <option value="female">Hembra</option>
                        <option value="male">Macho</option>
                    </select></label>
                <label class="nutrition-field"><span>Edad *</span><span class="nutrition-inline"><input name="age"
                            type="number" min="0.1" max="30" step="0.1" required inputmode="decimal"><select
                            name="ageUnit" aria-label="Unidad de edad">
                            <option value="years">Años</option>
                            <option value="months">Meses</option>
                        </select></span></label>
                <label class="nutrition-field"><span>Peso actual (kg) *</span><input name="weight" type="number"
                        min="0.2" max="150" step="0.1" required inputmode="decimal"></label>
                <label class="nutrition-field"><span>Peso ideal (kg) <small>opcional</small></span><input
                        name="idealWeight" type="number" min="0.2" max="150" step="0.1" inputmode="decimal"></label>
                <label class="nutrition-field" data-dog-size hidden><span>Tamaño adulto *</span><select name="size">
                        <option value="">Seleccionar</option>
                        <option value="small">Pequeño</option>
                        <option value="medium">Mediano</option>
                        <option value="large">Grande</option>
                        <option value="giant">Gigante</option>
                    </select></label>
                <label class="nutrition-field"><span>Condición corporal *</span><select name="bodyCondition" required>
                        <option value="">Seleccionar</option>
                        <option value="thin">Bajo peso</option>
                        <option value="ideal">Ideal</option>
                        <option value="overweight">Sobrepeso</option>
                        <option value="obese">Obesidad marcada</option>
                    </select></label>
                <label class="nutrition-field"><span>Actividad *</span><select name="activity" required>
                        <option value="">Seleccionar</option>
                        <option value="low">Baja</option>
                        <option value="normal">Moderada</option>
                        <option value="high">Alta</option>
                    </select></label>
                <label class="nutrition-field"><span>Esterilización *</span><select name="sterilized" required>
                        <option value="">Seleccionar</option>
                        <option value="yes">Sí</option>
                        <option value="no">No</option>
                    </select></label>
                <label class="nutrition-field"><span>Tipo de raza *</span><select name="breedType" required>
                        <option value="">Seleccionar</option>
                        <option value="mixed">Mestiza</option>
                        <option value="defined">Raza definida</option>
                    </select></label>
                <label class="nutrition-field"><span>Raza <small>opcional</small></span><input name="breed"
                        maxlength="80" autocomplete="off"></label>
                <label class="nutrition-field"><span>Condición de salud *</span><select name="health" required>
                        <option value="">Seleccionar</option>
                        <option value="healthy">Sin condición informada</option>
                        <option value="sensitive">Sensibilidad digestiva o cutánea</option>
                        <option value="medical">Enfermedad o condición médica</option>
                        <option value="pregnancy">Gestación o lactancia</option>
                    </select></label>
                <label class="nutrition-field nutrition-field--full"><span>Proteína o componente a evitar</span><select
                        name="allergy">
                        <option value="none">Ninguno informado</option>
                        <option value="pollo">Pollo</option>
                        <option value="pescado">Pescado</option>
                        <option value="cordero">Cordero</option>
                        <option value="vacuno">Vacuno</option>
                        <option value="pavo">Pavo</option>
                        <option value="cerdo">Cerdo</option>
                        <option value="grain">Cereales</option>
                    </select></label>
            </div>
            <div id="nutrition-form-error" class="nutrition-feedback nutrition-feedback--error" role="alert" hidden>
            </div>
            <button class="button nutrition-calculate-button" type="submit">Calcular y buscar alimentos</button>
        </form>
        <aside class="nutrition-guidance">
            <h2>Antes de comenzar</h2>
            <p>Esta calculadora entrega una estimación orientativa y no reemplaza la evaluación de un médico
                veterinario.</p>
            <ul>
                <li>El resultado puede variar según metabolismo, ambiente y salud.</li>
                <li>Los cambios de alimento deben ser graduales.</li>
                <li>Controla peso y condición corporal periódicamente.</li>
            </ul>
        </aside>
    </section>
    <section id="nutrition-results" class="container nutrition-results" aria-live="polite" hidden>
        <div class="nutrition-results-summary">
            <div><span>ENERGÍA DIARIA ESTIMADA</span><strong data-result-kcal>—</strong><small>kcal por día</small>
            </div>
            <div><span>ETAPA ESTIMADA</span><strong data-result-stage>—</strong><small data-result-meals>—</small></div>
        </div>
        <div id="nutrition-special-warning" class="nutrition-special-warning" hidden></div>
        <header class="nutrition-recommendations-heading"><span>COINCIDENCIAS DEL CATÁLOGO</span>
            <h2>Alimentos para revisar</h2>
            <p>El orden considera el perfil informado; la disponibilidad no cambia la compatibilidad nutricional.</p>
        </header>
        <div id="nutrition-product-results" class="nutrition-product-grid"></div>
        <?php if (isset($_SESSION['id_cliente'])): ?>
            <form id="nutrition-save-form" method="post" action="<?= e(appUrl('public/clientes/guardar-ficha-alimentacion.php')) ?>" hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="snapshot" value="">
            </form>
        <?php else: ?>
            <p>Para guardar una recomendación, <a href="<?= e(appUrl('public/clientes/login.php?return=' . rawurlencode('public/calculadora.php'))) ?>">inicia sesión</a>.</p>
        <?php endif; ?>
    </section>
    <?php if ($calculatorUnavailable): ?>
        <div class="container nutrition-feedback nutrition-feedback--error" role="status">La calculadora no puede cargar
            productos en este momento. Inténtalo más tarde.</div><?php endif; ?>
    <section class="container nutrition-disclaimer"><strong>Importante</strong>
        <p>Los resultados son orientativos. Ante gestación, lactancia, crecimiento, obesidad o una condición médica,
            consulta a un médico veterinario antes de cambiar la alimentación.</p>
    </section>
</main>
<script id="nutrition-products-data"
    type="application/json"><?= $calculatorJson === false ? '[]' : $calculatorJson ?></script>
<script src="<?= e(appUrl('public/assets/js/calculadora.js?v=' . filemtime(__DIR__ . '/assets/js/calculadora.js'))) ?>"
    defer></script>
<?php renderPublicPageEnd(); ?>
