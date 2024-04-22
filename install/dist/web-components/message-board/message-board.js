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
            mode: 'seamless'
        });
        this.#messages = root.querySelector('.messages');
        this.listen('message', this.showMessage.bind(this));
    }

    showMessage({message = null, type = 'info', id = null, timeout = 0}) {
        if (id) {
            this.#messages.querySelectorAll(`#${id}.message:not(.removing)`)
                .forEach(this.#hideMessage.bind(this));
        }

        if (!message) { // probably just an intent to remove old message
            return;
        }

        const msgNode = document.createElement('div');
        if (id) msgNode.id = id;
        msgNode.classList.add('message', type);

        const textNode = msgNode.appendChild(document.createElement('span'));
        textNode.textContent = message;

        const closeNode = msgNode.appendChild(document.createElement('button'));
        closeNode.addEventListener('click', () => this.#hideMessage(msgNode));

        this.#messages.appendChild(msgNode);

        if (timeout) {
            setTimeout(() => this.#hideMessage(msgNode), timeout);
        }
    }

    #hideMessage(node) {
        node.classList.add('removing');
        node.addEventListener('animationend', () => node.remove());

        // Ensurance if the animation didn't run or didn't finish
        // Get the animation length from variable --message-animation-length
        // and set a timeout to remove the node after that time
        let timeout = getComputedStyle(node).getPropertyValue('--message-animation-duration');
        timeout = parseFloat(timeout) * 1000;
        setTimeout(() => node.remove(), timeout);
    }
}