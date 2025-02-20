import { gettext, ngettext } from "/dist/zolinga-intl/gettext.js?zolinga-commons";

/**
 * Tag list editor - allows editing of list of tags.
 * 
 * <tag-list-editor [max-tags="number"] [name="name"] [readonly] [no-remove] [no-edit]>TAGS...</tag-list-editor>
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-02
 */
export default class TagListEditor extends HTMLElement {
    #root;
    //#internals;
    static observedAttributes = ['name', 'readonly', 'type', 'validation-error', 'no-edit', 'no-remove', 'pattern', 'min', 'max', 'step', 'minlength', 'maxlength'];

    constructor() {
        super();
        //this.#internals = this.attachInternals();
        const origTags = this.tags; // this is only before the class is loaded the first time
        delete this.tags; // delete property so our setter/getter starts working

        this.#init()
            .then(() => {
                if (origTags && origTags.length) {
                    console.log('TagListEditor: setting tags', origTags);
                    this.setTags(origTags);
                }
            });
    }

    get tags() {
        return Array.from(this.querySelectorAll('tag-editor input')).map(tag => tag.value).filter((t) => t);
    }

    set tags(values) {
        if (!Array.isArray(values)) {
            throw new Error('Tags must be an array');
        }
        this.setTags(values);
    }

    async #init() {
        this.#root = this.attachShadow({ mode: 'open' });
        // .trap is good for TAB navigation - when it lands there we create new tag
        this.#root.innerHTML = `
            <slot></slot>
            <span class="add">
                <span class="icon">+</span>
                <span class="text placeholder">...</span>
            </span>
            <span class="trap" contenteditable="true"></span>
            `;
        this.#addStyles();
        this.#root.querySelector('.add .text').textContent = this.getAttribute('placeholder') || gettext('Add tag...');

        const observer = new MutationObserver(this.#onContentChange.bind(this));
        observer.observe(this, { childList: true });
        this.#onContentChange();
        this.#addListeners();

        // Remove text nodes (spaces) between tags
        this.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) node.remove();
        });

        this.dataset.ready = true;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.#propagateAttributes();
        }
    }

    #propagateAttributes() {
        this.querySelectorAll('tag-editor')
            .forEach(tag => {
                ['readonly', 'name', 'no-edit', 'validation-error', 'no-remove', 'type', 'pattern', 'min', 'max', 'step', 'minlength', 'maxlength'].forEach(attr => {
                    if (this.hasAttribute(attr)) tag.setAttribute(attr, this.getAttribute(attr));
                    else if (attr !== 'type') tag.removeAttribute(attr);
                });
            });
    }

    #addListeners() {
        this.#root.querySelector('.add')
            .addEventListener('click', (event) => {
                this.addNewTag();
            });
        this.#root.querySelector('.trap')
            .addEventListener('focus', (event) => {
                console.log('TagListEditor: focus on trap - clicking [add] button');
                this.#root.querySelector('.add').click();
            });
    }

    #isMaxReached() {
        const max = parseInt(this.getAttribute('max-tags') || 0);
        const count = parseInt(this.getAttribute('count') || 0);
        return max && count >= max;
    }

    setTags(values) {
        console.log("Tag List Editor: setting tags", values);
        this.querySelectorAll(':scope > tag-editor').forEach(tagEditor => tagEditor.remove());
        values.forEach(value => this.addNewTag(value));
    }

    addNewTag(value) {
        if (this.#isMaxReached()) {
            console.warn(`Max tags limit reached (${max})`);
            return;
        }

        const tag = document.createElement('tag-editor');
        if (typeof value === 'string') {
            tag.textContent = value;
        } else {
            console.log('TagListEditor: setting autofocus attr on new tag');
            tag.setAttribute('autofocus', 'true');
        }
        this.appendChild(tag);
        this.#propagateAttributes();
        console.log('TagListEditor: added new tag', tag);
    }

    reset() {
        this.querySelectorAll('tag-editor').forEach(tagEditor => tagEditor.remove());
    }

    #onContentChange() {
        const count = this.querySelectorAll(':scope > tag-editor').length;
        this.setAttribute('count', count);
        this.classList.toggle('empty', count === 0);
        this.classList.toggle('placeholder-shown', count === 0);
        this.classList.toggle('max-tag-reached', this.#isMaxReached());
        this.#propagateAttributes();
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
                    display: inline-block;

                    & .text {
                        color: var(--color-placeholder, color-mix(in srgb, currentColor, transparent)); 
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