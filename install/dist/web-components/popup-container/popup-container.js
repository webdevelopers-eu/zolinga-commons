import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';

/**
 * Popup container
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-03 
 */
export default class PopupContainer extends WebComponent {
    #root;
    #main;
    #grid;
    #lastPopupState = -1;
    static observedAttributes = [...WebComponent.observedAttributes, 'width'];

    constructor() {
        super();
        this.ready(this.#init());
    }

    connectedCallback() {
        super.connectedCallback();
        this.#countPopups(1);
    }

    disconnectedCallback() {
        this.#countPopups(-1);
    }

    #countPopups(inc) {
        if (this.#lastPopupState === inc) return; // already counted/closed/opened
        this.#lastPopupState = inc;

        document.documentElement.popupContainerCount = (document.documentElement.popupContainerCount || 0);
        document.documentElement.popupContainerCount += inc;

        console.log(`popupContainerCount: ${document.documentElement.popupContainerCount} remaining popups`);
        
        document.documentElement.classList.toggle('popup-container-open', !!document.documentElement.popupContainerCount);
    }

    attributeChangedCallback(name, oldValue, newValue) {
        switch (name) {
            case 'width':
                this.style.setProperty('--popup-width', newValue);
                break;
        }
    }

    async #init() {
        this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: "closed",
            allowScripts: true,
            inheritStyles: false
        });
        this.#main = this.#root.querySelector('main');
        this.#grid = this.#root.querySelector('.grid');

        // It is recommended to have overflow-y:overlay; or scrollbar-gutter:stable; to avoid reflows.
        if (!document.popupContainerCssInstalled) {
            document.popupContainerCssInstalled = true;
            const mainFrameCss = `
            html.popup-container-open, html.popup-container-open body {
                overflow: hidden;
            }
            `;
            const style = new CSSStyleSheet();
            style.replaceSync(mainFrameCss);
            document.adoptedStyleSheets = [...document.adoptedStyleSheets, style];
        }

        this.#initListeners();
    }

    #initListeners() {
        // Delegated listener on all [role="close-popup"] clicks
        this.#main.addEventListener('click', (event) => {
            if (event.target.closest('[role="popup-close"]') || event.target.matches('.popup-container')) {
                this.close();
            }
        });
    }

    close() {
        this.dispatchEvent(new CustomEvent('popup-close'));
        this.setAttribute('closed', '');
        this.resolveModal();
        this.#countPopups(-1);
    }

    open() {
        this.dispatchEvent(new CustomEvent('popup-open'));
        this.removeAttribute('closed');
        this.#countPopups(1);
    }

    remove() {
        this.dispatchEvent(new CustomEvent('popup-remove'));
        super.remove();
        this.#countPopups(-1);
    }

    cover() {
        // this.dispatchEvent(new CustomEvent('popup-cover'));
        this.classList.add('cover');
    }

    uncover() {
        // this.dispatchEvent(new CustomEvent('popup-uncover'));
        this.classList.remove('cover');
    }
}
