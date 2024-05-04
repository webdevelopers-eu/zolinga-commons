import WebComponent from '/dist/system/js/web-component.js';

/**
 * Popup container
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-03 
 */
export default class PopupContainer extends WebComponent {
    #root;
    #main;

    constructor() {
        super();
        this.ready(this.#init());
    }

    connectedCallback() {
        document.documentElement.popupContainerCount = (document.documentElement.popupContainerCount || 0) + 1;
        document.documentElement.classList.add('popup-container-open');
    }

    disconnectedCallback() {
        document.documentElement.popupContainerCount--;
        if (document.documentElement.popupContainerCount === 0) {
            document.documentElement.classList.remove('popup-container-open');
        }
    }

    async #init() {
        this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: "closed",
            allowScripts: true,
            inheritStyles: false
        });
        this.#main = this.#root.querySelector('main');

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
        this.remove();
    }
}
