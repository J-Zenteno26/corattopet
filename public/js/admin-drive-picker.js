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
        csrfToken: root.dataset.csrfToken ?? '',
    };
    const button = root.querySelector('[data-drive-select]');
    const status = root.querySelector('[data-drive-status]');
    const error = root.querySelector('[data-drive-error]');
    const result = root.querySelector('[data-drive-result]');
    const foldersContainer = root.querySelector('[data-drive-folders]');
    const empty = root.querySelector('[data-drive-empty]');
    let accessToken = null;
    let tokenClient = null;
    let pickerReady = false;
    let identityReady = false;
    const driveScope = 'https://www.googleapis.com/auth/drive.readonly';

    const configured = Object.values(config).every((value) => value !== '');
    if (!(button instanceof HTMLButtonElement) || !configured) {
        if (button instanceof HTMLButtonElement) button.disabled = true;
        return;
    }

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

    const initializePicker = () => {
        if (!window.gapi) {
            setError('Google Picker no pudo cargarse. Recarga la página.');
            return;
        }
        window.gapi.load('picker', {
            callback: () => {
                pickerReady = true;
                setStatus(identityReady ? 'Google Drive está listo.' : 'Cargando autorización de Google…');
            },
            onerror: () => setError('Google Picker no pudo cargarse. Recarga la página.'),
        });
    };

    const initializeIdentity = () => {
        if (!window.google?.accounts?.oauth2) {
            setError('La autorización de Google no pudo cargarse. Recarga la página.');
            return;
        }
        tokenClient = window.google.accounts.oauth2.initTokenClient({
            client_id: config.clientId,
            scope: driveScope,
            callback: () => {},
            error_callback: (oauthError) => {
                if (oauthError?.type === 'popup_closed') setStatus('Autorización cancelada.');
                else setError('No fue posible completar la autorización con Google.');
            },
        });
        identityReady = true;
        setStatus(pickerReady ? 'Google Drive está listo.' : 'Cargando Google Picker…');
    };

    const pickerCallback = (data) => {
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
        const folderView = new window.google.picker.DocsView(window.google.picker.ViewId.FOLDERS)
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
        if (!pickerReady || !identityReady || !tokenClient) {
            setError('Las bibliotecas de Google todavía no están listas. Intenta nuevamente.');
            return;
        }
        setStatus('Esperando autorización de Google…');
        tokenClient.callback = (response) => {
            if (response?.error || typeof response?.access_token !== 'string') {
                accessToken = null;
                setError('Google no concedió acceso a Drive.');
                return;
            }
            if (!window.google.accounts.oauth2.hasGrantedAllScopes(response, driveScope)) {
                accessToken = null;
                setError('Google no concedió el permiso de solo lectura requerido para Drive.');
                return;
            }
            accessToken = response.access_token;
            showPicker();
        };
        tokenClient.requestAccessToken({ prompt: accessToken === null ? 'consent' : '' });
    };

    const renderResult = (data) => {
        root.querySelector('[data-drive-folder-name]').textContent = data.carpeta?.nombre ?? '';
        root.querySelector('[data-drive-folder-count]').textContent = String(data.cantidad_subcarpetas ?? 0);
        root.querySelector('[data-drive-sku-count]').textContent = String(data.cantidad_skus ?? 0);
        root.querySelector('[data-drive-product-count]').textContent = String(data.cantidad_productos_encontrados ?? 0);
        const ignoredRoot = Array.isArray(data.elementos_raiz_ignorados) ? data.elementos_raiz_ignorados : [];
        const ignoredRootMessage = root.querySelector('[data-drive-root-ignored]');
        ignoredRootMessage.hidden = ignoredRoot.length === 0;
        ignoredRootMessage.textContent = ignoredRoot.length
            ? `Elementos ignorados en la raíz: ${ignoredRoot.map((item) => item.nombre).join(', ')}.`
            : '';
        foldersContainer.replaceChildren();
        const folders = Array.isArray(data.carpetas) ? data.carpetas : [];
        folders.forEach((folder) => {
            const section = document.createElement('section');
            section.className = 'admin-panel';
            const title = document.createElement('h3');
            title.textContent = folder.nombre_carpeta ?? 'Carpeta sin nombre';
            section.appendChild(title);

            const summary = document.createElement('dl');
            const addSummary = (label, value) => {
                const row = document.createElement('div');
                const term = document.createElement('dt');
                const detail = document.createElement('dd');
                term.textContent = label;
                detail.textContent = value;
                row.append(term, detail);
                summary.appendChild(row);
            };
            addSummary('SKU detectados', Array.isArray(folder.sku_detectados) && folder.sku_detectados.length ? folder.sku_detectados.join(', ') : 'Ninguno');
            const products = Array.isArray(folder.productos_encontrados) ? folder.productos_encontrados : [];
            addSummary('Productos encontrados', products.length ? products.map((product) => `${product.sku} · ${product.nombre}`).join(' | ') : 'Ninguno');
            addSummary('SKU inexistentes', Array.isArray(folder.sku_inexistentes) && folder.sku_inexistentes.length ? folder.sku_inexistentes.join(', ') : 'Ninguno');
            const standardImages = Array.isArray(folder.imagenes_compatibles) ? folder.imagenes_compatibles : [];
            const heicImages = Array.isArray(folder.imagenes_heic_heif) ? folder.imagenes_heic_heif : [];
            addSummary('Imágenes compatibles', standardImages.length ? standardImages.map((file) => file.nombre).join(', ') : 'Ninguna');
            addSummary('Imágenes HEIC/HEIF', heicImages.length ? heicImages.map((file) => file.nombre).join(', ') : 'Ninguna');
            addSummary('Portada propuesta', folder.portada_detectada?.nombre ? `${folder.portada_detectada.nombre} (${folder.portada_detectada.deteccion})` : 'Ninguna');
            const ignored = Array.isArray(folder.archivos_ignorados) ? folder.archivos_ignorados : [];
            addSummary('Elementos ignorados', ignored.length ? ignored.map((file) => `${file.nombre}: ${file.motivo}`).join(' | ') : 'Ninguno');
            section.appendChild(summary);

            const processableImages = [...standardImages, ...heicImages];
            if (processableImages.length) {
                const processHeading = document.createElement('h4');
                processHeading.textContent = 'Prueba de procesamiento';
                const processList = document.createElement('ul');
                processableImages.forEach((file) => {
                    const item = document.createElement('li');
                    const name = document.createElement('span');
                    name.textContent = file.nombre;
                    const processButton = document.createElement('button');
                    processButton.type = 'button';
                    processButton.className = 'admin-button admin-button--small';
                    processButton.textContent = 'Procesar temporalmente';
                    const output = document.createElement('span');
                    output.setAttribute('role', 'status');
                    processButton.addEventListener('click', () => processFile(folder.id_carpeta, file.id, processButton, output));
                    item.append(name, document.createTextNode(' '), processButton, document.createTextNode(' '), output);
                    processList.appendChild(item);
                });
                section.append(processHeading, processList);
            }

            const incidents = Array.isArray(folder.incidencias) ? folder.incidencias : [];
            if (incidents.length) {
                const heading = document.createElement('h4');
                heading.textContent = 'Incidencias';
                const list = document.createElement('ul');
                incidents.forEach((incident) => {
                    const item = document.createElement('li');
                    item.textContent = incident;
                    list.appendChild(item);
                });
                section.append(heading, list);
            }
            foldersContainer.appendChild(section);
        });
        empty.hidden = folders.length !== 0;
        const heicStatus = data.diagnostico_heic ?? {};
        root.querySelector('[data-drive-imagick]').textContent = heicStatus.imagick_instalado ? 'Disponible' : 'No disponible';
        root.querySelector('[data-drive-heic-read]').textContent = heicStatus.heic_lectura_disponible ? 'Disponible' : 'No disponible';
        root.querySelector('[data-drive-webp-write]').textContent = heicStatus.webp_escritura_disponible ? 'Disponible' : 'No disponible';
        const heicWarning = root.querySelector('[data-drive-heic-warning]');
        heicWarning.hidden = !data.advertencia_heic;
        heicWarning.textContent = data.advertencia_heic ?? '';
        result.hidden = false;
        setStatus(data.message ?? 'Carpeta analizada correctamente.');
    };

    const processFile = async (folderId, fileId, processButton, output) => {
        if (typeof accessToken !== 'string' || accessToken === '') {
            output.textContent = 'La autorización venció. Selecciona nuevamente la carpeta.';
            return;
        }
        processButton.disabled = true;
        output.textContent = 'Procesando…';
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
                headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString(),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.ok) {
                if (response.status === 401) accessToken = null;
                output.textContent = data?.error ?? 'No fue posible procesar el archivo.';
                return;
            }
            const file = data.archivo ?? {};
            const dimensions = file.dimensiones ?? {};
            output.textContent = `${file.extension_final?.toUpperCase() ?? ''} · ${file.tamano_final ?? 0} bytes · ${dimensions.ancho ?? 0}×${dimensions.alto ?? 0} px · ${file.convertido ? 'convertido' : 'conservado'}`;
        } catch {
            output.textContent = 'No fue posible contactar el procesador.';
        } finally {
            processButton.disabled = false;
        }
    };

    const analyzeFolder = async (folderId) => {
        result.hidden = true;
        foldersContainer.replaceChildren();
        setStatus('Analizando subcarpetas, SKU e imágenes compatibles…');
        button.disabled = true;
        try {
            const body = new URLSearchParams({
                csrf_token: config.csrfToken,
                folder_id: folderId,
                access_token: accessToken,
            });
            const response = await fetch(config.analyzeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString(),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.ok) {
                if (response.status === 401) accessToken = null;
                setError(data?.message ?? 'No fue posible analizar la carpeta de Drive.');
                return;
            }
            renderResult(data);
        } catch {
            setError('Drive API no respondió. Revisa tu conexión e intenta nuevamente.');
        } finally {
            button.disabled = false;
        }
    };

    button.addEventListener('click', requestAuthorization);
    const apiScript = document.querySelector('#google-api-loader');
    const identityScript = document.querySelector('#google-identity-services');
    if (window.gapi) initializePicker();
    else {
        apiScript?.addEventListener('load', initializePicker, { once: true });
        apiScript?.addEventListener('error', () => setError('Google Picker no pudo cargarse.'), { once: true });
    }
    if (window.google?.accounts?.oauth2) initializeIdentity();
    else {
        identityScript?.addEventListener('load', initializeIdentity, { once: true });
        identityScript?.addEventListener('error', () => setError('La autorización de Google no pudo cargarse.'), { once: true });
    }
})();
