// we don't use WebComponent becaues we may have thousands on the page
// import WebComponent from '/dist/system/js/web-component.js';

/**
 * Copy the contents of the element on click.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2025-04-05
 */
export default class ClipboardCopy extends HTMLElement {

    constructor() {
        super();

        this.#init()
            .then(() => this.dataset.ready = true);
    }

    copy() {
        const text = this.innerText;
        navigator.clipboard.writeText(text).then(() => {
            console.log('Copied to clipboard:', text);
            this.classList.add('copied');
            setTimeout(() => {
                this.classList.remove('copied');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

    async #init() {
        this.addEventListener('click', this.copy.bind(this));

        // Inject css from the same directory
        if (this.getRootNode().querySelector('#clipboard-copy-style') === null) {
            const style = document.createElement('link');
            style.id = 'clipboard-copy-style';
            style.rel = 'stylesheet';
            style.href = new URL('./clipboard-copy.css', import.meta.url).href;
            this.getRootNode().appendChild(style);
        }
    }

}