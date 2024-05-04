import { gettext, ngettext } from "/dist/zolinga-intl/gettext.js?zolinga-commons";

/**
 * Tag editor - allows editing of tags that are displayed as pills.
 * 
 * <tag-editor [errormsg="ERROR"] [value="VALUE"] [name="name"] [autofocus] [readonly] [no-remove] [no-edit]>VALUE</tag-editor>
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-02
 */
export default class TagEditor extends HTMLElement {
    #input;
    #editor;
    #removeButton;
    #resolve;
    #readyPromise = new Promise((resolve) => { this.#resolve = resolve; });
    // #internals;
    static observedAttributes = ['value', 'name', 'readonly', 'type', 'validation-error', 'no-edit', 'no-remove', 'pattern', 'min', 'max', 'step', 'minlength', 'maxlength'];


    constructor() {
        super();
    }

    connectedCallback() {
        this.#init()
            .then(() => this.#resolve());
    }

    async #init() {        // this.#internals = this.attachInternals();

        this.#addStyles();
        const value = this.getAttribute('value') || this.textContent;

        this.innerHTML = `
        <input role="tag" class="input-tag" type="hidden" tabindex="-1" required />
        <div class="editor" contenteditable="true" spellcheck="false"></div>
        <div class="remove-confirm action">${gettext("Remove?")}</div>
        <div class="remove action">⨯</div>
        `;

        this.#input = this.querySelector('input.input-tag');
        this.#input.value = value;
        this.#editor = this.querySelector('.editor');
        this.#editor.textContent = value;
        this.#removeButton = this.querySelector('.remove-confirm');

        this.setValue(value);

        this.#initListeners();
        this.dataset.ready = true;

        if (this.hasAttribute('autofocus')) {
            this.focus();
            this.removeAttribute('autofocus');
        }
    }

    #initListeners() {
        this.#editor.addEventListener('input', (event) => {
            this.setValue(this.#editor.textContent);
        });

        this.querySelector('.remove').addEventListener('click', (event) => {
            this.#confirmRemoval();
        });

        // On validation it is focused and error displayed
        this.#input.addEventListener('keydown', (event) => {
            this.focus();
        });

        this.#removeButton.addEventListener('click', (event) => {
            this.#remove();
        });

        // On blur run validation on this.#input
        this.#editor.addEventListener('blur', (event) => {
            if (this.#editor.textContent.trim() === '') {
                this.#remove();
            } else {
                this.#editor.textContent = this.#editor.textContent.trim();
                this.validate();
            }
        });

        // Intercept all TAB, ENTER, and COMMA keys
        this.#editor.addEventListener('keydown', (event) => {
            const sel = this.getRootNode().getSelection();
            if (this.classList.contains('removing')) {
                if (event.key === 'Enter' || event.key === 'Backspace') {
                    this.#remove();
                } else {
                    this.classList.remove('removing');
                }
            } else if (event.key === 'Tab' || event.key === 'Enter') {
                if (this.validate()) {
                    this.#editor.blur();
                } else {
                    event.preventDefault();
                }
            } else if (event.key === 'ArrowLeft' && sel.focusOffset === 0) {
                this.#focusPrev();
                event.preventDefault();
            } else if (event.key === 'ArrowRight' && (sel.focusOffset === sel.focusNode.length || sel.focusNode === this.#editor)) {
                this.#focusNext();
                event.preventDefault();
            }


            // Backspace on empty editor should remove the tag
            if ((event.key === 'Backspace' || event.key === 'Delete') && this.#editor.textContent === '') {
                this.#confirmRemoval();
            }
        });
        // Watch editor for any elements and remove them using mutation observer
        // const observer = new MutationObserver((mutations) => {
        //     // Filter only element additions and removals
        //     const filtered = mutations.filter(m =>
        //         Array.from(m.addedNodes).some(n => n.nodeType === Node.ELEMENT_NODE) ||
        //         Array.from(m.removedNodes).some(n => n.nodeType === Node.ELEMENT_NODE)
        //     );
        //     if (filtered.length === 0) return;
        //     this.#editor.textContent = this.#editor.textContent.trim();
        // });
        // observer.observe(this.#editor, { childList: true, subtree: false, });
    }

    validate() {
        this.#input.setCustomValidity('');
        if (this.#input.validity.valid) {
            return true;
        } else {
            this.#input.setCustomValidity(this.getAttribute('validation-error') || "");
            this.#input.reportValidity();
            return false;
        }
    }

    #confirmRemoval() {
        this.classList.add('removing');
        document.body.addEventListener('click', (event) => {
            console.log(`Event ${event.type} on %o`, document.body);
            if (event.target.matches(':invalid, input, textarea, select')) { // browser triggers click on first invalid input in the form?
                this.#confirmRemoval();
            } else if (event.target !== this.#removeButton && !this.#removeButton.contains(event.target)) {
                this.classList.remove('removing');
            }
        }, { once: true, capture: true });
    }

    #remove() {
        if (!this.hasAttribute('no-remove') && !this.hasAttribute('readonly')) {
            this.#focusPrev();
            this.remove();
        }
    }

    focus(position = 'start') {
        this.#readyPromise.then(() => {
            this.#editor.focus();
            if (position === 'end') {
                const sel = this.getRootNode().getSelection();
                sel.collapse(this.#editor, this.#editor.childNodes.length);
            }
        });
    }

    set value(value) {
        this.setValue(value);
    }

    get value() {
        return this.#input.value;
    }

    set name(value) {
        this.setAttribute('name', value);
    }

    get name() {
        return this.getAttribute('name');
    }

    setValue(value) {
        value = value.trim();

        if (this.#input.value !== value) {
            this.#input.value = value;
        }
        if (this.#editor.textContent.trim() !== value) {
            this.#editor.textContent = value;
        }
        if (this.getAttribute('value') !== value) {
            this.setAttribute('value', value);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        this.#readyPromise.then(() => {
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
                case 'pattern':
                    this.#input.pattern = newValue;
                    break;
                case 'min':
                case 'max':
                case 'step':
                    this.#input[name] = newValue;
                    this.#input.type = 'number';
                    break;
                case 'minlength':
                    this.#input.minLength = newValue;
                    break;
                case 'maxlength':
                    this.#input.maxLength = newValue;
                    break;
                case 'type':
                    // When type!="hidden" there is a problem with focus - 
                    // clicking on the second focuses the first, don't know why
                    // it fires "click" event on the first tag's <input> element
                    this.#input.type = newValue === 'text' ? 'hidden' : newValue;
                    break;
            }
        });
    }

    #focusPrev() {
        this.previousElementSibling?.focus('end');
    }

    #focusNext() {
        this.nextElementSibling?.focus('start');
    }

    #addStyles() {
        let doc = this.getRootNode();
        if (!doc.tagEditorStylesInjected) {
            doc.tagEditorStylesInjected = true;

            const sheet = new CSSStyleSheet();
            sheet.replaceSync(`
                tag-editor {
                    display: inline-grid;
                    grid-template-columns: 0px [main-start] auto [main-end remove-start] auto [remove-end];
                    border-radius: var(--radius, 3px);
                    background-color: color-mix(in srgb, var(--color-primary, #f0f0f0) 70%, transparent);
                    color: var(--color-bg, #333);
                    min-height: 1lh;
                    padding: 0px;
                    margin: 0.1em;
                    max-width: 100%;
                    gap: .5em;
                    overflow: hidden;
                    position: relative;

                    &:has(input:invalid):not(:focus-within) {
                        outline: 2px solid red;
                    }

                    & .input-tag {
                        opacity: 0;
                        position: absolute;
                        pointer-events: none;
                        width: 0px;
                        height: 0px;
                        bottom: 0px;
                        left: 50%;
                    }

                    &[value=""] .editor::before {
                        content: '...';
                        opacity: 0.5;
                    }

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

                        & * {
                            display: contents !important;
                        }
                    }

                    &> .remove {
                        grid-column: remove-start / remove-end;
                        grid-row: 1 / span 1;
                        cursor: pointer;
                        padding: 0px 0.2em;
                        opacity: 0.5;

                        &:hover {
                            opacity: 1;
                            background-color: color-mix(in srgb, darkred, transparent);
                            color: white;
                        }
                    }

                    &.removing::before {
                        content: '';
                        position: fixed;
                        inset: 0px;
                        z-index: 100;
                    }

                    &:not(.removing) > .remove-confirm {
                        display: none;
                    }

                    &> .remove-confirm {
                        grid-column: 1 / -1;
                        grid-row: 1 / span 1;
                        padding: 0em 0.5em;
                        text-align: center;
                        background-color: color-mix(in srgb, darkred, transparent);
                        color: white;
                        z-index: 100;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        backdrop-filter: blur(1px) saturate(0%);
                    }

                    &:is([readonly], [no-remove]) > .action {
                        display: none;
                    }
                    
                    &:focus-within {
                        background-color: color-mix(in srgb, var(--color-primary, #f0f0f0) 100%, transparent);
                    }
                }
                `);
            doc.adoptedStyleSheets.push(sheet);
        }
    }
}