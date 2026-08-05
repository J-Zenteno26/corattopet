(() => {
    'use strict';

    const editors = document.querySelectorAll('[data-blog-editor]');
    if (editors.length === 0) {
        return;
    }

    const allowedLink = /^(https?:\/\/|mailto:|\/(?!\/))/i;

    editors.forEach((container) => {
        const source = container.querySelector('[data-blog-editor-source]');
        const surface = container.querySelector('[data-blog-editor-surface]');
        const toolbar = container.querySelector('.admin-blog-editor__toolbar');
        const clientError = container.querySelector('[data-blog-editor-error]');
        const form = container.closest('form');

        if (!(source instanceof HTMLTextAreaElement)
            || !(surface instanceof HTMLElement)
            || !(toolbar instanceof HTMLElement)
            || !(form instanceof HTMLFormElement)) {
            return;
        }

        let savedRange = null;

        const setClientError = (message = '') => {
            if (!(clientError instanceof HTMLElement)) {
                return;
            }
            clientError.textContent = message;
            clientError.hidden = message === '';
            surface.toggleAttribute('aria-invalid', message !== '');
        };

        const currentText = () => (surface.textContent ?? '').replace(/\u00a0/g, ' ').trim();

        const syncSource = () => {
            source.value = surface.innerHTML.trim();
        };

        const saveSelection = () => {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                return;
            }
            const range = selection.getRangeAt(0);
            if (surface.contains(range.commonAncestorContainer)) {
                savedRange = range.cloneRange();
            }
        };

        const restoreSelection = () => {
            if (!(savedRange instanceof Range)) {
                surface.focus();
                return;
            }
            const selection = window.getSelection();
            if (!selection) {
                return;
            }
            selection.removeAllRanges();
            selection.addRange(savedRange);
        };

        const refreshToolbarState = () => {
            toolbar.querySelectorAll('[data-editor-command]').forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }
                const command = button.dataset.editorCommand ?? '';
                const canHaveState = ['bold', 'italic', 'insertUnorderedList', 'insertOrderedList'].includes(command);
                const isActive = canHaveState && document.queryCommandState(command);
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        const applyCommand = (button) => {
            const command = button.dataset.editorCommand;
            const value = button.dataset.editorValue ?? null;
            if (!command) {
                return;
            }

            restoreSelection();

            if (command === 'createLink') {
                const selection = window.getSelection();
                const selectedAnchor = selection?.anchorNode instanceof Node
                    ? selection.anchorNode.parentElement?.closest('a')
                    : null;

                if (selectedAnchor instanceof HTMLAnchorElement) {
                    document.execCommand('unlink');
                } else {
                    const href = window.prompt('Ingresa una URL http, https, correo o ruta interna:');
                    if (href === null) {
                        return;
                    }
                    const normalizedHref = href.trim();
                    if (!allowedLink.test(normalizedHref)) {
                        setClientError('El enlace debe comenzar con http://, https://, mailto: o /.');
                        return;
                    }
                    document.execCommand('createLink', false, normalizedHref);
                }
            } else if (command === 'formatBlock') {
                document.execCommand(command, false, value ?? 'p');
            } else {
                document.execCommand(command, false, value);
            }

            setClientError();
            syncSource();
            saveSelection();
            refreshToolbarState();
            surface.focus();
        };

        source.required = false;
        surface.innerHTML = source.value.trim();
        container.classList.add('is-enhanced');

        toolbar.addEventListener('mousedown', (event) => {
            if (event.target.closest('[data-editor-command]')) {
                event.preventDefault();
            }
        });

        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-editor-command]');
            if (button instanceof HTMLButtonElement) {
                applyCommand(button);
            }
        });

        surface.addEventListener('input', () => {
            setClientError();
            syncSource();
            saveSelection();
            refreshToolbarState();
        });

        surface.addEventListener('keyup', () => {
            saveSelection();
            refreshToolbarState();
        });

        surface.addEventListener('mouseup', () => {
            saveSelection();
            refreshToolbarState();
        });

        surface.addEventListener('focus', saveSelection);

        surface.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text/plain');
            if (typeof text !== 'string') {
                return;
            }
            event.preventDefault();
            document.execCommand('insertText', false, text);
        });

        form.addEventListener('submit', (event) => {
            syncSource();
            if (currentText() === '') {
                event.preventDefault();
                setClientError('Ingresa el contenido del artículo.');
                surface.focus();
                return;
            }
            if (source.value.length > 100000) {
                event.preventDefault();
                setClientError('El contenido no puede superar los 100.000 caracteres.');
                surface.focus();
            }
        });
    });
})();
