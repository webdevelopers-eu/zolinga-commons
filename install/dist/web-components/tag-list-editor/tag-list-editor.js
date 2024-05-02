import { gettext, ngettext } from "/dist/zolinga-intl/gettext.js?zolinga-commons";

/**
 * Tag list editor - allows editing of list of tags.
 * 
 * <tag-list-editor [max="number"] [name="name"] [readonly] [no-remove] [no-edit]>TAGS...</tag-list-editor>
 */
export default class TagListEditor extends HTMLElement {
    #root;
    static observedAttributes = ['name', 'readonly', 'no-edit', 'no-remove'];

    constructor() {
        super();
        this.#init();
    }

    async #init() {
        this.#root = this.attachShadow({ mode: 'open' });
        this.#root.innerHTML = `
            <slot></slot>
            <span class="add">
                <span class="icon">+</span>
                <span class="text">...</span>
            </span>
            <span class="trap" contenteditable="true"></span>
            `;
        this.#addStyles();
        this.#root.querySelector('.add .text').textContent = this.getAttribute('placeholder') || gettext('Add tag...');

        this.dataset.ready = true;

        const observer = new MutationObserver(this.#onContentChange.bind(this));
        observer.observe(this, { childList: true });
        this.#onContentChange();
        this.#addListeners();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.#propagateAttributes();
        }
    }

    #propagateAttributes() {
        this.querySelectorAll('tag-editor')
            .forEach(tag => {
                ['readonly', 'no-edit', 'no-remove'].forEach(attr => {
                    if (this.hasAttribute(attr)) tag.setAttribute(attr, '');
                    else tag.removeAttribute(attr);
                });
                tag.setAttribute('name', this.getAttribute('name'));
            });
    }

    #addListeners() {
        this.#root.querySelector('.add').addEventListener('click', () => {
            this.#addNewTag();
        });
        this.#root.querySelector('.trap').addEventListener('focus', () => {
            this.#root.querySelector('.add').click();
        });
    }

    #isMaxReached() {
        const max = parseInt(this.getAttribute('max') || 0);
        const count = parseInt(this.getAttribute('count') || 0);
        return max && count >= max;
    }

    #addNewTag() {
        if (this.#isMaxReached()) {
            console.warn(`Max tags limit reached (${max})`);
            return;
        }

        const tag = document.createElement('tag-editor');
        this.appendChild(tag);
        this.#propagateAttributes();
        tag.focus();
    }

    #onContentChange() {
        this.setAttribute('count', this.querySelectorAll('tag-editor').length);
        this.classList.toggle('max-tag-reached', this.#isMaxReached());
    }

    #addStyles() {
        const stylesheet = new CSSStyleSheet();
        stylesheet.replaceSync(`
                :host {
                }
                :host(:is([readonly], [no-edit], .max-tag-reached)) .add {
                    display: none;
                }
                :host(:not([count="0"])) .add .text {
                    display: none;
                }
                :host([count="0"]) .add .icon {
                    display: none;
                }
                .trap {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                .add {
                    cursor: pointer;

                    & .text {
                        font-style: italic;
                        opacity: 0.5;    
                    }

                    & .icon {
                        display: inline-block;
                        border-radius: 50%;
                        background-color: var(--color-primary, #333);
                        color: var(--color-bg, white);
                        font-weight: bold;
                        text-align: center;
                        width: 1.5em;
                        height: 1.5em;
                    }
                }
            `);
        this.#root.adoptedStyleSheets.push(stylesheet);
    }
}