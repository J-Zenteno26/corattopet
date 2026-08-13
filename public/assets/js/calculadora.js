(() => {
    'use strict';
    const form = document.querySelector('#nutrition-calculator-form');
    const dataNode = document.querySelector('#nutrition-products-data');
    if (!form || !dataNode) return;
    let products = [];
    try { products = JSON.parse(dataNode.textContent || '[]'); } catch (_) { products = []; }

    const sizeField = form.querySelector('[data-dog-size]');
    const speciesField = form.elements.species;
    const errorBox = document.querySelector('#nutrition-form-error');
    const results = document.querySelector('#nutrition-results');
    const cards = document.querySelector('#nutrition-product-results');
    const warning = document.querySelector('#nutrition-special-warning');
    const saveForm = document.querySelector('#nutrition-save-form');
    let currentCalculation = null;
    const labels = { puppy: 'Cachorro', kitten: 'Gatito', adult: 'Adulto', senior: 'Senior' };
    const normalizeSpecies = value => value === 'dog' ? 'perro' : value === 'cat' ? 'gato' : value;
    const calculationStorageKey = 'coratto_nutrition_last_calculation';

    function saveCalculation(calculation) {
        try {
            sessionStorage.setItem(
                calculationStorageKey,
                JSON.stringify(calculation)
            );
        } catch (_) {
            // La calculadora sigue funcionando aunque el navegador bloquee storage.
        }
    }

    function toggleSize() {
        const dog = speciesField.value === 'perro';
        sizeField.hidden = !dog;
        form.elements.size.required = dog;
        if (!dog) form.elements.size.value = '';
    }
    speciesField.addEventListener('change', toggleSize);
    toggleSize();

    function lifeStage(species, months, size) {
        if (species === 'gato') {
            if (months < 12) return 'kitten';
            return months >= 120 ? 'senior' : 'adult';
        }
        const adultAt = { small: 12, medium: 15, large: 18, giant: 24 }[size] || 15;
        const seniorAt = ({ small: 10, medium: 9, large: 8, giant: 7 }[size] || 9) * 12;
        if (months < adultAt) return 'puppy';
        return months >= seniorAt ? 'senior' : 'adult';
    }

    function energyFactor(profile, stage, months) {
        if (stage === 'puppy' || stage === 'kitten') return months < 4 ? 3 : 2;
        let factor;
        if (profile.species === 'gato') factor = stage === 'senior' ? 1.1 : (profile.sterilized ? 1.2 : 1.4);
        else factor = stage === 'senior' ? 1.4 : (profile.sterilized ? 1.6 : 1.8);
        factor *= { low: .85, normal: 1, high: 1.2 }[profile.activity] || 1;
        factor *= { thin: 1.1, ideal: 1, overweight: .8, obese: .65 }[profile.bodyCondition] || 1;
        if (profile.health === 'pregnancy') factor *= 1.35;
        return factor;
    }

    function matchProduct(product, profile, stage) {
        const species = normalizeSpecies(
            String(product.especie || '').toLowerCase()
        );

        if (![profile.species, 'ambos'].includes(species)) {
            return null;
        }

        const stages = Array.isArray(product.etapas)
            ? product.etapas.map(String)
            : [];

        const sizes = Array.isArray(product.tamanos)
            ? product.tamanos.map(String)
            : [];

        const proteins = Array.isArray(product.proteinas)
            ? product.proteinas.map(String)
            : [];

        /*
         * Compatibilidades obligatorias.
         * Si falla alguna, el alimento no debe aparecer como recomendación.
         */
        if (stages.length && !stages.includes(stage)) {
            return null;
        }

        if (
            profile.species === 'perro'
            && sizes.length
            && !sizes.includes(profile.size)
        ) {
            return null;
        }

        if (
            profile.allergy !== 'none'
            && profile.allergy !== 'grain'
            && proteins.includes(profile.allergy)
        ) {
            return null;
        }

        if (profile.allergy === 'grain' && !product.grainFree) {
            return null;
        }

        /*
         * Desde aquí solo puntuamos preferencias.
         */
        let score = 20;

        const reasons = [
            'Compatible con ' + (
                profile.species === 'perro'
                    ? 'perros'
                    : 'gatos'
            )
        ];

        if (stages.includes(stage)) {
            score += 35;
            reasons.push('Etapa de vida compatible');
        }

        if (
            profile.species === 'perro'
            && sizes.includes(profile.size)
        ) {
            score += 16;
            reasons.push('Tamaño compatible');
        }

        if (profile.sterilized && product.esterilizados) {
            score += 14;
            reasons.push('Perfil para esterilizados');
        }

        if (
            ['overweight', 'obese'].includes(profile.bodyCondition)
            && product.controlPeso
        ) {
            score += 18;
            reasons.push('Apoyo al control de peso');
        }

        if (
            profile.health === 'sensitive'
            && product.sensible
        ) {
            score += 18;
            reasons.push('Perfil sensible');
        }

        if (
            profile.allergy === 'grain'
            && product.grainFree
        ) {
            score += 18;
            reasons.push('Sin cereales según perfil');
        }

        if (
            profile.allergy !== 'none'
            && profile.allergy !== 'grain'
        ) {
            reasons.push('No declara la proteína evitada');
        }

        return {
            product,
            score,
            reasons: reasons.slice(0, 3)
        };
    }

    function mealsFor(stage, months) {
        if (stage === 'puppy' || stage === 'kitten') return months < 6 ? 4 : 3;
        return 2;
    }

    function productCard(match, kcal, meals) {
        const p = match.product;
        const grams = p.kcalKg ? Math.round(kcal * 1000 / p.kcalKg) : null;
        const image = p.imagen ? `<img src="${escapeHtml(p.imagen)}" alt="${escapeHtml(p.nombre)}" loading="lazy">` : '<div class="nutrition-product-placeholder" aria-hidden="true">🐾</div>';
        const energy = p.kcalKg ? `${Math.round(p.kcalKg).toLocaleString('es-CL')} kcal/kg${p.energiaVerificada ? '' : ' · Energía referencial'}` : 'Energía no informada';
        const portion = grams ? `<strong>${grams} g/día</strong><span>${Math.round(grams / meals)} g por comida · ${meals} comidas</span>` : '<strong>Porción no calculable</strong><span>Este alimento no tiene kcal/kg informadas.</span>';
        const saveButton = saveForm && grams ? `<button class="button" type="button" data-save-nutrition-product-id="${Number(p.id)}">Guardar en mi perfil</button>` : '';
        return `<article class="nutrition-product-card">${image}<div class="nutrition-product-card__body"><span class="nutrition-product-brand">${escapeHtml(p.marca)}</span><h3>${escapeHtml(p.nombre)}</h3><ul>${match.reasons.map(reason => `<li>${escapeHtml(reason)}</li>`).join('')}</ul><div class="nutrition-product-energy"><span>${escapeHtml(energy)}</span>${portion}</div><div class="nutrition-product-footer"><span class="${p.disponible ? 'is-available' : 'is-unavailable'}">${p.disponible ? 'Disponible' : 'Sin stock'}</span>${saveButton}<a
                class="button"
                href="${escapeHtml(p.url)}"
                data-nutrition-product-id="${Number(p.id)}"
            >
                Ver producto
            </a></div></div></article>`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char]);
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        toggleSize();
        if (!form.reportValidity()) return;
        const values = Object.fromEntries(new FormData(form).entries());
        const weight = Number(values.weight);
        const idealWeight = values.idealWeight ? Number(values.idealWeight) : null;
        if (!Number.isFinite(weight) || weight <= 0 || (idealWeight !== null && (!Number.isFinite(idealWeight) || idealWeight <= 0))) {
            errorBox.textContent = 'Revisa los pesos ingresados.'; errorBox.hidden = false; return;
        }
        errorBox.hidden = true;
        const months = Number(values.age) * (values.ageUnit === 'years' ? 12 : 1);
        const profile = { species: values.species, size: values.size, sterilized: values.sterilized === 'yes', activity: values.activity, bodyCondition: values.bodyCondition, health: values.health, allergy: values.allergy };
        const stage = lifeStage(profile.species, months, profile.size);
        const calculationWeight = idealWeight && ['overweight', 'obese'].includes(profile.bodyCondition) ? idealWeight : weight;
        const kcal = Math.round(70 * Math.pow(calculationWeight, .75) * energyFactor(profile, stage, months));
        const meals = mealsFor(stage, months);
        document.querySelector('[data-result-kcal]').textContent = kcal.toLocaleString('es-CL');
        document.querySelector('[data-result-stage]').textContent = labels[stage];
        document.querySelector('[data-result-meals]').textContent = `${meals} comidas recomendadas`;
        const warnings = [];
        if (profile.health === 'pregnancy') warnings.push('Gestación o lactancia requiere seguimiento veterinario y ajustes individuales.');
        if (profile.bodyCondition === 'obese') warnings.push('La obesidad marcada debe manejarse con un plan indicado y controlado por un médico veterinario.');
        if (profile.health === 'medical') warnings.push('Una enfermedad o condición médica puede requerir alimentación terapéutica prescrita por un médico veterinario.');
        if (stage === 'puppy' || stage === 'kitten') warnings.push('Durante el crecimiento, controla peso y desarrollo con tu médico veterinario.');
        warning.hidden = warnings.length === 0;
        warning.innerHTML = warnings.length ? `<strong>Atención especial</strong><ul>${warnings.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>` : '';
        const matches = products
            .map(product => matchProduct(product, profile, stage))
            .filter(Boolean)
            .sort(
                (a, b) =>
                    b.score - a.score
                    || a.product.nombre.localeCompare(
                        b.product.nombre,
                        'es'
                    )
            )
            .slice(0, 3);

        const calculation = {
            version: 1,
            calculatedAt: new Date().toISOString(),

            profile: {
                petName: String(values.petName || '').trim(),
                species: profile.species,
                sex: values.sex,
                age: Number(values.age),
                ageUnit: values.ageUnit,
                weight,
                idealWeight,
                size: profile.size || null,
                bodyCondition: profile.bodyCondition,
                activity: profile.activity,
                sterilized: profile.sterilized,
                breedType: values.breedType,
                breed: String(values.breed || '').trim(),
                health: profile.health,
                allergy: profile.allergy
            },

            result: {
                stage,
                kcalDay: kcal,
                meals
            },

            recommendations: matches.map(match => {
                const product = match.product;

                const gramsDay = product.kcalKg
                    ? Math.round(
                        kcal * 1000 / product.kcalKg
                    )
                    : null;

                return {
                    productId: product.id,
                    sku: product.sku,
                    nombre: product.nombre,
                    marca: product.marca,
                    kcalKg: product.kcalKg,
                    energiaVerificada: product.energiaVerificada,
                    gramsDay,
                    gramsMeal: gramsDay
                        ? Math.round(gramsDay / meals)
                        : null,
                    disponible: product.disponible,
                    url: product.url,
                    reasons: match.reasons
                };
            }),

            selectedProductId: null
        };

        saveCalculation(calculation);
        currentCalculation = calculation;

        cards.innerHTML = matches.length
            ? matches
                .map(
                    match =>
                        productCard(
                            match,
                            kcal,
                            meals
                        )
                )
                .join('')
            : '<div class="nutrition-empty"><h3>No encontramos una coincidencia compatible</h3><p>Revisa los datos ingresados o conversa con nuestro equipo y tu médico veterinario.</p></div>';
        results.hidden = false;
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

cards.addEventListener('click', event => {
    const saveButton = event.target.closest('[data-save-nutrition-product-id]');
    if (saveButton && saveForm && currentCalculation) {
        const productId = Number(saveButton.dataset.saveNutritionProductId);
        const recommendation = currentCalculation.recommendations.find(item => Number(item.productId) === productId);
        if (!recommendation) return;
        saveForm.elements.snapshot.value = JSON.stringify({
            profile: currentCalculation.profile,
            result: currentCalculation.result,
            recommendation
        });
        saveButton.disabled = true;
        saveForm.submit();
        return;
    }
    const link = event.target.closest(
        '[data-nutrition-product-id]'
    );

    if (!link) {
        return;
    }

    const productId = Number(
        link.dataset.nutritionProductId
    );

    if (!Number.isInteger(productId) || productId <= 0) {
        return;
    }

    try {
        const stored = JSON.parse(
            sessionStorage.getItem(
                calculationStorageKey
            ) || 'null'
        );

        if (!stored || typeof stored !== 'object') {
            return;
        }

        stored.selectedProductId = productId;

        sessionStorage.setItem(
            calculationStorageKey,
            JSON.stringify(stored)
        );
    } catch (_) {
        // No impedir la navegación si storage no está disponible.
    }
});

})();
