import WebComponent from '/dist/system/js/web-component.js';
import zTemplate from '/dist/system/js/z-template.js';
import api from '/dist/system/js/api.js';

/**
 * Upload files to server.
 * 
 * <file-uploader 
 *  [accept="MIME_TYPES"]>
 *      [<template>...</template>]
 * </file-uploader>
 * 
 * Example:
 * 
 * <file-uploader accept="image/png, image/jpeg, text/*"></file-uploader>
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-05-22
 */
export default class FileUploader extends WebComponent {
    observedAttributes = ['accept', ...WebComponent.observedAttributes];
    #root;
    #template;
    #file;
    #uploadPromise;
    #uploadBatchPromise;
    #dragoverDelay;
    #dropCover;

    constructor() {
        super();

        this.#uploadPromise = Promise.resolve();

        this.ready(this.#init())
            .then(() => this.classList.add('file-uploader-ready'));
    }

    /**
     * @returns Promise that resolves when all files are uploaded.
     */
    waitForUpload() {
        return this.#uploadBatchPromise;
    }

    async #init() {
        this.#root = await this.loadContent(import.meta.url.replace(/\.js$/, '.html'), {
            mode: "closed",
            allowScripts: true,
            inheritStyles: true
        });

        this.#template = this.querySelector('template') || this.#root.querySelector('template');
        this.#file = this.#root.querySelector('input[role~="new-file"]');
        this.#file.accept = this.getAttribute('accept') || '*/*';
        this.#dropCover = this.#root.querySelector('.fu-drop-target-cover');

        this.#installListeners();

        // Add style to parent document if not already present
        const parentRoot = this.getRootNode();
        if (!parentRoot.fuInitialized) {
            parentRoot.fuInitialized = true;
            const sheet = new CSSStyleSheet();
            const url = import.meta.url.replace(/\.js$/, '.css');
            sheet.replaceSync(await fetch(url).then(response => response.text()));
            parentRoot.adoptedStyleSheets.push(sheet);
        }

        this.#updateCount();
    }

    #installListeners() {
        this.#file.addEventListener('change', async (ev) => {
            let files = Array.from(this.#file.files);
            if (!this.#file.reportValidity()) {
                files = [];
            }
            this.#file.value = '';

            this.#uploadBatchPromise = Promise.all(
                files.map((file) => this.#addNewFile(file))
            );
        });

        // On drag&drop files
        this.addEventListener('dragover', (ev) => {
            // console.log("Uploader: Dragging over %s files", ev.dataTransfer.files.length);
            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'copy';
            ev.dataTransfer.effectAllowed = 'copy';
            this.classList.add('fu-dragover');
        });

        this.addEventListener('dragleave', (ev) => {
            // console.log("Uploader: Drag leave with %s files", ev.dataTransfer.files.length);
            clearTimeout(this.#dragoverDelay);
            this.#dragoverDelay = setTimeout(() => this.classList.remove('fu-dragover'), 100);
        });

        this.addEventListener('drop', (ev) => {
            ev.preventDefault();
            const files = Array.from(ev.dataTransfer.files);
            console.log("Uploader: Drop %s files", files.length);
            this.classList.remove('fu-dragover');
            this.#uploadBatchPromise = Promise.all(
                files.map(file => this.#addNewFile(file))
            );
        });

        this.#root.addEventListener('click', (ev) => {
            const role = ev.target.closest('[role]')?.getAttribute('role');
            const uri = ev.target.closest('[data-uri]')?.getAttribute('data-uri');
            const fileBlock = this.querySelector(`:scope > [data-uri="${uri}"]`);
            switch (role) {
                case "remove-file-confirm":
                    const popover = this.#root.querySelector('#fu-confirm-removal');
                    popover.dataset.uri = uri;
                    popover.showPopover();
                    break;
                case "remove-file":
                    if (uri.match(/^zolinga:uploader:/)) {
                        api.dispatchEvent('uploader', { op: "remove", uri });
                    }
                    this.removeFile(uri);
                    break;
            }
        });
    }

    /**
     * Add already uploaded file.
     * 
     * @param Object{uri, fieldName, name, size, sizeHR, type, lastModified, url} data 
     * @returns HTMLElement 
     */
    addFile(data) {
        const ret = this.#createElement(data);
        return ret;
    }

    removeFile(uri) {
        this.querySelector(`:scope > [data-uri="${uri}"]`)?.remove();
        this.#updateCount();
    }

    reset() {
        this.querySelectorAll(':scope > .fu-file[data-uri]').forEach(el => this.removeFile(el.dataset.uri));
        this.#file.value = '';
    }

    #updateCount() {
        this.dataset.count = this.querySelectorAll(':scope > .fu-file').length;
    }

    /**
     * Upload new file
     * 
     * @param File file 
     */
    async #addNewFile(file) {
        const data = {
            uri: '',
            fieldName: this.getAttribute('name') || 'file', // Name of the file input field (default: file
            name: file.name,
            size: file.size,
            sizeHR: (file.size / 1024).toFixed(2) + ' KB',
            type: file.type,
            lastModified: new Date(file.lastModified).toLocaleString(),
            url: URL.createObjectURL(file)
        };

        const el = this.#createElement(data);
        el.classList.add('fu-loading');

        this.#uploadPromise = this.#uploadPromise
            .then(async () => this.#uploadFile(file)
                .then(uri => {
                    el.dataset.uri = uri;
                    zTemplate(el, { ...data, uri })
                    el.classList.remove('fu-loading');
                })
                .catch(error => {
                    console.log(`Uploader: Removing upload element due to error: ${error.message}`, el);
                    el.dataset.uri = 'uploader:error:' + Math.random();
                    this.removeFile(el.dataset.uri);
                })
            );

        return this.#uploadPromise;
    }

    /**
     * Clone the template and insert data into it.
     * 
     * @param Object{uri, fieldName, name, size, sizeHR, type, lastModified, url} data 
     * @returns 
     */
    #createElement(data) {
        const el = this.#template.content.firstElementChild.cloneNode(true);
        el.dataset.uri = data.uri || '';
        el.classList.add('fu-file');
        this.appendChild(el);
        zTemplate(el, data);
        this.#updateCount();
        return el;
    }


    /**
     * Upload a single file and return Uploader URI identifier.
     * 
     * @param File file 
     * @returns 
     */
    async #uploadFile(file) {
        const url = '/dist/zolinga-commons/upload/';
        const formData = new FormData();
        formData.append('file', file);

        try {
            // Do standard POST file upload to dist/zolinga-commons/upload/
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`File Uploader: ${response.status} ${response.statusText}`);
            }

            const result = await response.json();

            if (result.status !== 200) {
                throw new Error(`${result.message} (${file.name} ${Math.ceil(file.size / 1024) / 1024} MB)`);
            }

            return result.id;
        } catch (error) {
            this.broadcast('message', {
                message: error.message,
                type: 'error',
                timeout: 15000,
                id: 'file-uploader'
            });
            throw error;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        switch (name) {
            case 'accept':
            case 'capture':
                this.#file[name] = newValue;
                break
        }
    }

    get accept() {
        return this.getAttribute('accept');
    }

    set accept(value) {
        this.setAttribute('accept', value);
    }

}