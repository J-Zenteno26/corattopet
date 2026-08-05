(() => {
    'use strict';

    const dialog = document.querySelector('[data-blog-preview-modal]');
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const loading = dialog.querySelector('[data-blog-preview-loading]');
    const error = dialog.querySelector('[data-blog-preview-error]');
    const article = dialog.querySelector('[data-blog-preview-article]');
    const state = dialog.querySelector('[data-blog-preview-state]');
    const category = dialog.querySelector('[data-blog-preview-category]');
    const title = dialog.querySelector('[data-blog-preview-title]');
    const excerpt = dialog.querySelector('[data-blog-preview-excerpt]');
    const publicAuthor = dialog.querySelector('[data-blog-preview-public-author]');
    const responsible = dialog.querySelector('[data-blog-preview-responsible]');
    const cover = dialog.querySelector('[data-blog-preview-cover]');
    const content = dialog.querySelector('[data-blog-preview-content]');
    const videoWrap = dialog.querySelector('[data-blog-preview-video-wrap]');
    const video = dialog.querySelector('[data-blog-preview-video]');
    let opener = null;
    let requestController = null;

    const resetPreview = () => {
        requestController?.abort();
        requestController = null;
        loading.hidden = false;
        error.hidden = true;
        error.textContent = '';
        article.hidden = true;
        state.textContent = '';
        state.className = 'blog-preview-modal__state';
        category.textContent = '';
        title.textContent = '';
        excerpt.textContent = '';
        publicAuthor.textContent = '';
        responsible.textContent = '';
        content.replaceChildren();
        cover.hidden = true;
        cover.removeAttribute('src');
        cover.alt = '';
        videoWrap.hidden = true;
        video.removeAttribute('href');
    };

    const showError = (message) => {
        loading.hidden = true;
        article.hidden = true;
        error.textContent = message;
        error.hidden = false;
    };

    const responseMessage = (status) => {
        if (status === 403) {
            return 'No tienes permiso para ver este artículo.';
        }
        if (status === 404) {
            return 'El artículo solicitado no existe.';
        }
        return 'No fue posible cargar la vista previa. Intenta nuevamente.';
    };

    const renderPreview = (data) => {
        state.textContent = data.estado_etiqueta ?? '';
        if (typeof data.estado === 'string' && data.estado !== '') {
            state.classList.add(`is-${data.estado}`);
        }
        category.textContent = data.categoria ?? '';
        title.textContent = data.titulo ?? '';
        excerpt.textContent = data.extracto ?? '';
        publicAuthor.textContent = data.autor_publico ?? '';
        responsible.textContent = data.responsable ?? '';
        content.innerHTML = typeof data.contenido === 'string' ? data.contenido : '';

        if (typeof data.portada_url === 'string' && data.portada_url !== '') {
            cover.src = data.portada_url;
            cover.alt = `Portada de ${data.titulo ?? 'artículo'}`;
            cover.hidden = false;
        }
        if (typeof data.video_url === 'string' && data.video_url !== '') {
            video.href = data.video_url;
            videoWrap.hidden = false;
        }

        loading.hidden = true;
        error.hidden = true;
        article.hidden = false;
    };

    const loadPreview = async (button) => {
        const url = button.dataset.previewUrl;
        if (!url) {
            showError('No fue posible identificar el artículo.');
            return;
        }

        const controller = new AbortController();
        requestController = controller;
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) {
                showError(responseMessage(response.status));
                return;
            }
            const data = await response.json();
            renderPreview(data);
        } catch (fetchError) {
            if (fetchError.name !== 'AbortError') {
                showError('No fue posible cargar la vista previa. Revisa tu conexión e intenta nuevamente.');
            }
        } finally {
            if (requestController === controller) {
                requestController = null;
            }
        }
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-blog-preview-open]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        opener = button;
        resetPreview();
        if (!dialog.open) {
            dialog.showModal();
        }
        loadPreview(button);
    });

    dialog.querySelectorAll('[data-blog-preview-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        resetPreview();
        if (opener instanceof HTMLButtonElement && opener.isConnected) {
            opener.focus();
        }
        opener = null;
    });
})();
