import { gettext, ngettext } from "/dist/zolinga-intl/gettext.js?zolinga-commons";

/**
 * Tag editor - allows editing of tags that are displayed as pills.
 * 
 * <tag-editor [value="VALUE"] [name="name"] [readonly] [no-remove] [no-edit]>VALUE</tag-editor>
 */
export default class TagEditor extends HTMLElement {
    #input;
    #editor;
    static observedAttributes = ['value', 'name', 'readonly', 'no-edit', 'no-remove'];


    constructor() {
        super();
        this.#init();
    }

    async #init() {
        this.#addStyles();
        const value = this.getAttribute('value') || this.textContent;

        this.innerHTML = `
        <input type="hidden" />
        <div class="editor" contenteditable="true" spellcheck="false"></div>
        <div class="remove-confirm action">${gettext("Remove?")}</div>
        <div class="remove action">⨯</div>
        `;

        this.#input = this.querySelector('input[type="hidden"]');
        this.#input.value = value;
        this.#editor = this.querySelector('.editor');
        this.#editor.textContent = value;

        this.setValue(value);

        this.#initListeners();
        this.dataset.ready = true;
    }

    #initListeners() {
        this.#editor.addEventListener('input', () => {
            this.setValue(this.#editor.textContent);
        });

        this.querySelector('.remove').addEventListener('click', () => {
            this.#confirmRemoval();
        });

        this.querySelector('.remove-confirm').addEventListener('click', () => {
            this.remove();
        });

        // Intercept all TAB, ENTER, and COMMA keys
        this.#editor.addEventListener('keydown', (event) => {
            if (event.key === 'Tab' || event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                this.#editor.blur();
            }
        });
        // Watch editor for any elements and remove them using mutation observer
        const observer = new MutationObserver((mutations) => {
            this.#editor.textContent = this.#editor.textContent.trim();
        });
        observer.observe(this.#editor, { childList: true });
    }

    #confirmRemoval() {
        this.classList.add('removing');
        document.body.addEventListener('click', (event) => {
            if (event.target !== this && !this.contains(event.target)) {
                this.classList.remove('removing');
            }
        }, { once: true, capture: true });
    }

    setValue(value) {
        value = value.trim();
        
        if (this.#input.value !== value) {
            this.#input.value = value;
        }
        if (this.#editor.textContent !== value) {
            this.#editor.textContent = value;
        }
        if (this.getAttribute('value') !== value) {
            this.setAttribute('value', value);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        switch (name) {
            case 'no-edit':
            case 'readonly':
                this.#editor.setAttribute('contenteditable', newValue === null ? 'true' : 'false');
                break;
            case 'value':
                this.setValue(newValue);
                break;
            case 'name':
                this.#input.name = newValue;
                break;
        }
    }

    #addStyles() {
        let el = document.querySelector('style[id="tag-editor-styles"]');
        if (!el) {
            let el = document.createElement('style');
            el.id = 'tag-editor-styles';
            el.textContent = `
                tag-editor {
                    display: inline-grid;
                    grid-template-columns: 0px [main-start] auto [main-end remove-start] auto [remove-end];
                    border-radius: var(--radius, 3px);
                    background-color: color-mix(in srgb, var(--color-fg, #f0f0f0), transparent);
                    color: var(--color-bg, #333);
                    min-height: 1lh;
                    padding: 0px;
                    margin: 1px;
                    max-width: 100%;
                    gap: .5em;
                    overflow: hidden;

                    &> .editor {
                        outline: none !important;
                        border: none !important;
                        padding: 0px;
                        margin: 0px;
                        text-overflow: ellipsis;
                        overflow: hidden;
                        word-wrap: break-word;
                        white-space: normal;
                        margin: 0px;
                        cursor: text;
                        grid-column: main-start / main-end;
                        grid-row: 1 / span 1;
                    }

                    &> .remove {
                        grid-column: remove-start / remove-end;
                        grid-row: 1 / span 1;
                        cursor: pointer;
                        padding: 0px 0.2em;
                        opacity: 0.5;

                        &:hover {
                            opacity: 1;
                            background-color: darkred;
                            color: white;
                        }
                    }

                    &:not(.removing) > .remove-confirm {
                        display: none;
                    }

                    &> .remove-confirm {
                        grid-column: 1 / -1;
                        grid-row: 1 / span 1;
                        padding: 0em 0.5em;
                        text-align: center;
                        background-color: darkred;
                        color: white;
                        z-index: 1;
                        cursor: pointer;
                    }

                    &:is([readonly], [no-remove]) > .action {
                        display: none;
                    }

                    &:hover, &:focus-within {
                        background-color: color-mix(in srgb, var(--color-fg, #f0f0f0) 70%, transparent);
                    }
                }
            `;
            document.head.appendChild(el);
        }
        let doc = this.getRootNode();
        if (doc !== document) {
            doc.adoptedStyleSheets.push(el.styleSheet);
        }
    }
}