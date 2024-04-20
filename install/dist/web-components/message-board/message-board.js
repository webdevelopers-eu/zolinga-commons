import WebComponent from '/dist/system/lib/web-component.js';

export default class MessageBoard extends WebComponent {
    #messages;

    constructor() {
        super();

        this.ready(this.#init());
    }

    async #init() {
        const contentURL = import.meta.url.replace('message-board.js', 'message-board.html');
        const root = await this.loadContent(contentURL, {
            mode: 'open'
        });
        this.#messages = root.querySelector('.messages');
        this.listen('message', this.showMessage.bind(this));
    }

    showMessage({message, type = 'info', id = null, timeout = 0}) {
        const msgNode = document.createElement('div');
        msgNode.classList.add('message', type);

        const textNode = msgNode.appendChild(document.createElement('span'));
        textNode.textContent = message;

        const closeNode = msgNode.appendChild(document.createElement('button'));
        closeNode.addEventListener('click', () => msgNode.remove());

        if (id) {
            msgNode.id = id;
            this.#messages.querySelector(`#${id}`)?.remove();
        }

        this.#messages.appendChild(msgNode);

        if (timeout) {
            setTimeout(() => msgNode.remove(), timeout);
        }
    }
}