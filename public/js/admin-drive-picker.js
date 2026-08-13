(() => {
    'use strict';

    const root = document.querySelector('[data-drive-picker]');
    if (!(root instanceof HTMLElement)) return;

    const config = {
        clientId: root.dataset.clientId ?? '',
        apiKey: root.dataset.apiKey ?? '',
        appId: root.dataset.appId ?? '',
        analyzeUrl: root.dataset.analyzeUrl ?? '',
        processUrl: root.dataset.processUrl ?? '',
        importUrl: root.dataset.importUrl ?? '',
        csrfToken: root.dataset.csrfToken ?? '',
    };

    const selectButton = root.querySelector('[data-drive-select]');
    const status = root.querySelector('[data-drive-status]');
    const error = root.querySelector('[data-drive-error]');
    const result = root.querySelector('[data-drive-result]');
    const foldersContainer = root.querySelector('[data-drive-folders]');
    const empty = root.querySelector('[data-drive-empty]');
    const busyLayer = root.querySelector('[data-drive-busy]');
    const busyTitle = root.querySelector('[data-drive-busy-title]');
    const busyMessage = root.querySelector('[data-drive-busy-message]');

    let accessToken = null;
    let tokenClient = null;
    let pickerReady = false;
    let identityReady = false;
    let busy = false;

    const driveScope = 'https://www.googleapis.com/auth/drive.readonly';
    const configured = Object.values(config).every((value) => value !== '');

    if (
        !(selectButton instanceof HTMLButtonElement)
        || !(status instanceof HTMLElement)
        || !(error instanceof HTMLElement)
        || !(result instanceof HTMLElement)
        || !(foldersContainer instanceof HTMLElement)
        || !(empty instanceof HTMLElement)
        || !(busyLayer instanceof HTMLElement)
        || !(busyTitle instanceof HTMLElement)
        || !(busyMessage instanceof HTMLElement)
        || !configured
    ) {
        if (selectButton instanceof HTMLButtonElement) {
            selectButton.disabled = true;
        }
        return;
    }

    const modalAvailable = () => (
        window.AdminModal
        && typeof window.AdminModal.open === 'function'
        && typeof window.AdminModal.confirm === 'function'
    );

    const openModal = (options) => {
        if (modalAvailable()) {
            window.AdminModal.open(options);
            return;
        }

        window.alert(options.message ?? options.title ?? 'Operación completada.');
    };

    const confirmModal = (options) => {
        if (modalAvailable()) {
            window.AdminModal.confirm(options);
            return;
        }

        if (window.confirm(options.message ?? '¿Deseas continuar?')) {
            options.onConfirm?.();
        }
    };

    const setStatus = (message) => {
        status.textContent = message;
        error.textContent = '';
        error.hidden = true;
    };

    const setError = (message) => {
        status.textContent = '';
        error.textContent = message;
        error.hidden = false;
    };

    const setBusy = (
        state,
        title = 'Procesando imágenes',
        message = 'No cierres la página ni presiones otros botones hasta finalizar.'
    ) => {
        busy = state;
        root.setAttribute('aria-busy', state ? 'true' : 'false');
        busyLayer.hidden = !state;

        if (state) {
            busyTitle.textContent = title;
            busyMessage.textContent = message;
        }

        root.querySelectorAll('button').forEach((element) => {
            if (!(element instanceof HTMLButtonElement)) return;
            if (element.closest('[data-drive-busy]')) return;

            if (state) {
                element.dataset.driveWasDisabled = element.disabled ? '1' : '0';
                element.disabled = true;
                return;
            }

            if (element.dataset.driveWasDisabled !== '1') {
                element.disabled = false;
            }

            delete element.dataset.driveWasDisabled;
        });
    };

    const initializePicker = () => {
        if (!window.gapi) {
            setError('Google Picker no pudo cargarse. Recarga la página.');
            return;
        }

        window.gapi.load('picker', {
            callback: () => {
                pickerReady = true;
                setStatus(
                    identityReady
                        ? 'Google Drive está listo.'
                        : 'Cargando autorización de Google…'
                );
            },
            onerror: () => {
                setError('Google Picker no pudo cargarse. Recarga la página.');
            },
        });
    };

    const initializeIdentity = () => {
        if (!window.google?.accounts?.oauth2) {
            setError(
                'La autorización de Google no pudo cargarse. Recarga la página.'
            );
            return;
        }

        tokenClient = window.google.accounts.oauth2.initTokenClient({
            client_id: config.clientId,
            scope: driveScope,
            callback: () => {},
            error_callback: (oauthError) => {
                if (oauthError?.type === 'popup_closed') {
                    setStatus('Autorización cancelada.');
                    return;
                }

                setError(
                    'No fue posible completar la autorización con Google.'
                );
            },
        });

        identityReady = true;
        setStatus(
            pickerReady
                ? 'Google Drive está listo.'
                : 'Cargando Google Picker…'
        );
    };

    const pickerCallback = (data) => {
        if (busy) return;

        if (data.action === window.google.picker.Action.CANCEL) {
            setStatus('Selección cancelada.');
            return;
        }

        if (data.action !== window.google.picker.Action.PICKED) return;

        const folderId = data.docs?.[0]?.id;

        if (typeof folderId !== 'string' || folderId === '') {
            setError('La carpeta seleccionada no es válida.');
            return;
        }

        analyzeFolder(folderId);
    };

    const showPicker = () => {
        if (busy) return;

        const folderView = new window.google.picker.DocsView(
            window.google.picker.ViewId.FOLDERS
        )
            .setIncludeFolders(true)
            .setSelectFolderEnabled(true)
            .setMimeTypes('application/vnd.google-apps.folder')
            .setMode(window.google.picker.DocsViewMode.LIST);

        const picker = new window.google.picker.PickerBuilder()
            .addView(folderView)
            .setOAuthToken(accessToken)
            .setDeveloperKey(config.apiKey)
            .setAppId(config.appId)
            .setMaxItems(1)
            .setOrigin(window.location.origin)
            .setCallback(pickerCallback)
            .build();

        picker.setVisible(true);
    };

    const requestAuthorization = () => {
        if (busy) return;

        if (!pickerReady || !identityReady || !tokenClient) {
            setError(
                'Las bibliotecas de Google todavía no están listas. Intenta nuevamente.'
            );
            return;
        }

        setStatus('Esperando autorización de Google…');

        tokenClient.callback = (response) => {
            if (
                response?.error
                || typeof response?.access_token !== 'string'
            ) {
                accessToken = null;
                setError('Google no concedió acceso a Drive.');
                return;
            }

            if (!window.google.accounts.oauth2.hasGrantedAllScopes(
                response,
                driveScope
            )) {
                accessToken = null;
                setError(
                    'Google no concedió el permiso de solo lectura requerido para Drive.'
                );
                return;
            }

            accessToken = response.access_token;
            showPicker();
        };

        tokenClient.requestAccessToken({
            prompt: accessToken === null ? 'consent' : '',
        });
    };

    const addSummaryRow = (summary, label, value) => {
        const row = document.createElement('div');
        const term = document.createElement('dt');
        const detail = document.createElement('dd');

        term.textContent = label;
        detail.textContent = value;

        row.append(term, detail);
        summary.appendChild(row);
    };

    const renderImportResult = (data, output) => {
        const summary = data.resumen ?? {};
        const resultList = Array.isArray(data.resultados)
            ? data.resultados
            : [];

        const box = document.createElement('div');
        box.className = 'admin-drive-import-result';

        const heading = document.createElement('strong');
        heading.textContent = data.message ?? 'Importación completada.';

        const totals = document.createElement('p');
        totals.textContent = [
            `Guardadas: ${summary.imagenes_guardadas ?? 0}`,
            `Convertidas: ${summary.archivos_convertidos ?? 0}`,
            `Omitidas: ${summary.imagenes_omitidas ?? 0}`,
            `Errores: ${summary.errores ?? 0}`,
        ].join(' · ');

        box.append(heading, totals);

        if (resultList.length) {
            const details = document.createElement('ul');

            resultList.forEach((file) => {
                const item = document.createElement('li');
                const productResults = Array.isArray(file.productos)
                    ? file.productos
                    : [];

                const productText = productResults.length
                    ? productResults.map((product) => {
                        const suffix = product.message
                            ? `: ${product.message}`
                            : '';

                        return `${product.sku} — ${product.estado}${suffix}`;
                    }).join(' | ')
                    : (file.message ?? file.estado ?? '');

                item.textContent = `${file.archivo}: ${productText}`;
                details.appendChild(item);
            });

            box.appendChild(details);
        }

        output.replaceChildren(box);
    };

    const importFolder = async (folderId, importButton, output) => {
        if (busy) return;

        if (typeof accessToken !== 'string' || accessToken === '') {
            openModal({
                type: 'warning',
                title: 'Autorización vencida',
                message:
                    'Selecciona nuevamente la carpeta para renovar el acceso temporal a Google Drive.',
                primaryText: 'Entendido',
            });
            return;
        }

        setBusy(
            true,
            'Importando imágenes',
            'La descarga y conversión puede tardar. No cierres ni recargues esta página.'
        );

        const previousText = importButton.textContent;
        importButton.textContent = 'Importando…';
        output.replaceChildren();

        try {
            const body = new URLSearchParams({
                csrf_token: config.csrfToken,
                folder_id: String(folderId ?? ''),
                access_token: accessToken,
            });

            const response = await fetch(config.importUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type':
                        'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data?.ok) {
                if (response.status === 401) accessToken = null;

                const message = data?.message
                    ?? 'No fue posible importar las imágenes.';

                setError(message);

                openModal({
                    type: 'error',
                    title: 'No se completó la importación',
                    message,
                    primaryText: 'Cerrar',
                });

                return;
            }

            renderImportResult(data, output);

            const summary = data.resumen ?? {};
            const detail = [
                `${summary.imagenes_guardadas ?? 0} imágenes guardadas`,
                `${summary.archivos_convertidos ?? 0} convertidas a WebP`,
                `${summary.imagenes_omitidas ?? 0} omitidas`,
                `${summary.errores ?? 0} errores`,
            ].join(' · ');

            openModal({
                type: (summary.errores ?? 0) > 0
                    ? 'warning'
                    : 'success',
                title: (summary.errores ?? 0) > 0
                    ? 'Importación completada con observaciones'
                    : 'Imágenes importadas',
                message:
                    data.message
                    ?? 'La importación se completó correctamente.',
                detail,
                primaryText: 'Ver resultado',
            });
        } catch {
            const message =
                'No fue posible contactar el importador. Revisa la conexión antes de volver a intentar.';

            setError(message);

            openModal({
                type: 'error',
                title: 'Error de conexión',
                message,
                primaryText: 'Cerrar',
            });
        } finally {
            importButton.textContent =
                previousText || 'Importar imágenes al catálogo';
            setBusy(false);
        }
    };

    const confirmImport = (
        folderId,
        importButton,
        output,
        folder,
        products
    ) => {
        if (busy) return;

        const imageCount = (
            (Array.isArray(folder.imagenes_compatibles)
                ? folder.imagenes_compatibles.length
                : 0)
            + (Array.isArray(folder.imagenes_heic_heif)
                ? folder.imagenes_heic_heif.length
                : 0)
        );

        const productNames = products
            .map((product) => `${product.sku} · ${product.nombre}`)
            .join(' | ');

        confirmModal({
            title: 'Importar imágenes al catálogo',
            message:
                `Se procesarán ${imageCount} imagen(es) para ${products.length} producto(s).`,
            detail: productNames,
            confirmText: 'Importar imágenes',
            cancelText: 'Cancelar',
            closeOnOverlay: false,
            onConfirm: () => {
                window.AdminModal?.close?.();
                importFolder(folderId, importButton, output);
            },
        });
    };

    const renderFolderCard = (folder) => {
        const section = document.createElement('section');
        section.className = 'admin-panel admin-drive-folder-card';

        const header = document.createElement('div');
        header.className = 'admin-drive-folder-card__header';

        const headingGroup = document.createElement('div');
        const eyebrow = document.createElement('span');
        eyebrow.className = 'admin-drive-folder-card__eyebrow';
        eyebrow.textContent = 'Carpeta de producto';

        const title = document.createElement('h3');
        title.textContent =
            folder.nombre_carpeta ?? 'Carpeta sin nombre';

        headingGroup.append(eyebrow, title);
        header.appendChild(headingGroup);
        section.appendChild(header);

        const summary = document.createElement('dl');
        summary.className = 'admin-drive-folder-card__summary';

        const products = Array.isArray(folder.productos_encontrados)
            ? folder.productos_encontrados
            : [];

        const standardImages = Array.isArray(folder.imagenes_compatibles)
            ? folder.imagenes_compatibles
            : [];

        const heicImages = Array.isArray(folder.imagenes_heic_heif)
            ? folder.imagenes_heic_heif
            : [];

        const ignored = Array.isArray(folder.archivos_ignorados)
            ? folder.archivos_ignorados
            : [];

        addSummaryRow(
            summary,
            'SKU detectados',
            Array.isArray(folder.sku_detectados)
                && folder.sku_detectados.length
                ? folder.sku_detectados.join(', ')
                : 'Ninguno'
        );

        addSummaryRow(
            summary,
            'Productos encontrados',
            products.length
                ? products.map((product) => (
                    `${product.sku} · ${product.nombre}`
                )).join(' | ')
                : 'Ninguno'
        );

        addSummaryRow(
            summary,
            'SKU inexistentes',
            Array.isArray(folder.sku_inexistentes)
                && folder.sku_inexistentes.length
                ? folder.sku_inexistentes.join(', ')
                : 'Ninguno'
        );

        addSummaryRow(
            summary,
            'Imágenes compatibles',
            standardImages.length
                ? standardImages.map((file) => file.nombre).join(', ')
                : 'Ninguna'
        );

        addSummaryRow(
            summary,
            'Imágenes HEIC/HEIF',
            heicImages.length
                ? heicImages.map((file) => file.nombre).join(', ')
                : 'Ninguna'
        );

        addSummaryRow(
            summary,
            'Portada propuesta',
            folder.portada_detectada?.nombre
                ? `${folder.portada_detectada.nombre} (${folder.portada_detectada.deteccion})`
                : 'Ninguna'
        );

        addSummaryRow(
            summary,
            'Elementos ignorados',
            ignored.length
                ? ignored.map((file) => (
                    `${file.nombre}: ${file.motivo}`
                )).join(' | ')
                : 'Ninguno'
        );

        section.appendChild(summary);

        const processableImages = [
            ...standardImages,
            ...heicImages,
        ];

        if (processableImages.length && products.length) {
            const importArea = document.createElement('div');
            importArea.className = 'admin-drive-folder-card__import';

            const importCopy = document.createElement('div');
            const importTitle = document.createElement('strong');
            importTitle.textContent = '¿Todo correcto?';

            const importText = document.createElement('span');
            importText.textContent =
                'Importa las imágenes y espera el resultado antes de continuar.';

            importCopy.append(importTitle, importText);

            const importButton = document.createElement('button');
            importButton.type = 'button';
            importButton.className =
                'admin-button admin-button--primary';
            importButton.innerHTML = [
                '<i class="bi bi-cloud-arrow-down" aria-hidden="true"></i>',
                'Importar imágenes al catálogo',
            ].join(' ');

            const importOutput = document.createElement('div');
            importOutput.className =
                'admin-drive-folder-card__output';
            importOutput.setAttribute('role', 'status');
            importOutput.setAttribute('aria-live', 'polite');

            importButton.addEventListener('click', () => {
                confirmImport(
                    folder.id_carpeta,
                    importButton,
                    importOutput,
                    folder,
                    products
                );
            });

            importArea.append(importCopy, importButton);
            section.append(importArea, importOutput);
        }

        if (processableImages.length) {
            const testDetails = document.createElement('details');
            testDetails.className =
                'admin-drive-folder-card__tests';

            const testSummary = document.createElement('summary');
            testSummary.textContent =
                'Pruebas individuales de conversión';

            const processList = document.createElement('ul');

            processableImages.forEach((file) => {
                const item = document.createElement('li');
                const name = document.createElement('span');
                name.textContent = file.nombre;

                const processButton = document.createElement('button');
                processButton.type = 'button';
                processButton.className =
                    'admin-button admin-button--small';
                processButton.textContent =
                    'Procesar temporalmente';

                const output = document.createElement('span');
                output.setAttribute('role', 'status');

                processButton.addEventListener('click', () => {
                    processFile(
                        folder.id_carpeta,
                        file.id,
                        processButton,
                        output
                    );
                });

                item.append(name, processButton, output);
                processList.appendChild(item);
            });

            testDetails.append(testSummary, processList);
            section.appendChild(testDetails);
        }

        const incidents = Array.isArray(folder.incidencias)
            ? folder.incidencias
            : [];

        if (incidents.length) {
            const incidentBox = document.createElement('div');
            incidentBox.className =
                'admin-drive-folder-card__incidents';

            const heading = document.createElement('strong');
            heading.textContent = 'Observaciones';

            const list = document.createElement('ul');

            incidents.forEach((incident) => {
                const item = document.createElement('li');
                item.textContent = incident;
                list.appendChild(item);
            });

            incidentBox.append(heading, list);
            section.appendChild(incidentBox);
        }

        return section;
    };

    const renderResult = (data) => {
        root.querySelector('[data-drive-folder-name]').textContent =
            data.carpeta?.nombre ?? '';

        root.querySelector('[data-drive-folder-count]').textContent =
            String(data.cantidad_subcarpetas ?? 0);

        root.querySelector('[data-drive-sku-count]').textContent =
            String(data.cantidad_skus ?? 0);

        root.querySelector('[data-drive-product-count]').textContent =
            String(data.cantidad_productos_encontrados ?? 0);

        const ignoredRoot = Array.isArray(data.elementos_raiz_ignorados)
            ? data.elementos_raiz_ignorados
            : [];

        const ignoredRootMessage = root.querySelector(
            '[data-drive-root-ignored]'
        );

        ignoredRootMessage.hidden = ignoredRoot.length === 0;
        ignoredRootMessage.textContent = ignoredRoot.length
            ? `Elementos ignorados en la raíz: ${ignoredRoot
                .map((item) => item.nombre)
                .join(', ')}.`
            : '';

        foldersContainer.replaceChildren();

        const folders = Array.isArray(data.carpetas)
            ? data.carpetas
            : [];

        folders.forEach((folder) => {
            foldersContainer.appendChild(renderFolderCard(folder));
        });

        empty.hidden = folders.length !== 0;

        const heicStatus = data.diagnostico_heic ?? {};

        root.querySelector('[data-drive-imagick]').textContent =
            heicStatus.imagick_instalado
                ? 'Disponible'
                : 'No disponible';

        root.querySelector('[data-drive-heic-read]').textContent =
            heicStatus.heic_lectura_disponible
                ? 'Disponible'
                : 'No disponible';

        root.querySelector('[data-drive-webp-write]').textContent =
            heicStatus.webp_escritura_disponible
                ? 'Disponible'
                : 'No disponible';

        const heicWarning = root.querySelector(
            '[data-drive-heic-warning]'
        );

        heicWarning.hidden = !data.advertencia_heic;
        heicWarning.textContent = data.advertencia_heic ?? '';

        result.hidden = false;
        setStatus(
            data.message ?? 'Carpeta analizada correctamente.'
        );
    };

    const processFile = async (
        folderId,
        fileId,
        processButton,
        output
    ) => {
        if (busy) return;

        if (typeof accessToken !== 'string' || accessToken === '') {
            output.textContent =
                'La autorización venció. Selecciona nuevamente la carpeta.';
            return;
        }

        setBusy(
            true,
            'Procesando imagen',
            'Se está descargando y convirtiendo el archivo temporalmente.'
        );

        const previousText = processButton.textContent;
        processButton.textContent = 'Procesando…';
        output.textContent = '';

        try {
            const body = new URLSearchParams({
                csrf_token: config.csrfToken,
                folder_id: String(folderId ?? ''),
                file_id: String(fileId ?? ''),
                access_token: accessToken,
            });

            const response = await fetch(config.processUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type':
                        'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data?.ok) {
                if (response.status === 401) accessToken = null;

                output.textContent = data?.error
                    ?? 'No fue posible procesar el archivo.';

                return;
            }

            const file = data.archivo ?? {};
            const dimensions = file.dimensiones ?? {};

            output.textContent = [
                file.extension_final?.toUpperCase() ?? '',
                `${file.tamano_final ?? 0} bytes`,
                `${dimensions.ancho ?? 0}×${dimensions.alto ?? 0} px`,
                file.convertido ? 'convertido' : 'conservado',
            ].join(' · ');
        } catch {
            output.textContent =
                'No fue posible contactar el procesador.';
        } finally {
            processButton.textContent =
                previousText || 'Procesar temporalmente';
            setBusy(false);
        }
    };

    const analyzeFolder = async (folderId) => {
        if (busy) return;

        result.hidden = true;
        foldersContainer.replaceChildren();
        setStatus(
            'Analizando carpetas, SKU e imágenes compatibles…'
        );

        setBusy(
            true,
            'Analizando carpeta',
            'Estamos revisando los SKU y archivos disponibles en Google Drive.'
        );

        try {
            const body = new URLSearchParams({
                csrf_token: config.csrfToken,
                folder_id: folderId,
                access_token: accessToken,
            });

            const response = await fetch(config.analyzeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type':
                        'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data?.ok) {
                if (response.status === 401) accessToken = null;

                const message = data?.message
                    ?? 'No fue posible analizar la carpeta de Drive.';

                setError(message);

                openModal({
                    type: 'error',
                    title: 'No se pudo analizar la carpeta',
                    message,
                    primaryText: 'Cerrar',
                });

                return;
            }

            renderResult(data);
        } catch {
            const message =
                'Drive API no respondió. Revisa tu conexión e intenta nuevamente.';

            setError(message);

            openModal({
                type: 'error',
                title: 'Error de conexión con Drive',
                message,
                primaryText: 'Cerrar',
            });
        } finally {
            setBusy(false);
        }
    };

    selectButton.addEventListener('click', requestAuthorization);

    const apiScript = document.querySelector('#google-api-loader');
    const identityScript = document.querySelector(
        '#google-identity-services'
    );

    if (window.gapi) {
        initializePicker();
    } else {
        apiScript?.addEventListener(
            'load',
            initializePicker,
            { once: true }
        );

        apiScript?.addEventListener(
            'error',
            () => setError('Google Picker no pudo cargarse.'),
            { once: true }
        );
    }

    if (window.google?.accounts?.oauth2) {
        initializeIdentity();
    } else {
        identityScript?.addEventListener(
            'load',
            initializeIdentity,
            { once: true }
        );

        identityScript?.addEventListener(
            'error',
            () => setError(
                'La autorización de Google no pudo cargarse.'
            ),
            { once: true }
        );
    }
})();
