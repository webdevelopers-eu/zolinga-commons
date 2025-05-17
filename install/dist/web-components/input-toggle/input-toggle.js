
/**
 * Checkbox-compatible form toggle input.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-08
 */
export default class InputToggle extends HTMLElement {
    #root;
    #input;
    #toggle;
    static observedAttributes = ['value', 'checked', 'disabled', 'readonly', 'required', 'name', 'form'];
    #states = {};

    constructor() {
        super();
        this.boundOnInputChange = this.#onInputChange.bind(this);
        this.#init()
            .then(() => this.dataset.ready = true);
    }

    get checked() {
        return this.#input.checked;
    }

    set checked(value) {
        const state = value === true || value === 'true' || value === '1' || value === 'on';
        this.#states.checked = state;
        this.#input.checked = state;
        this.toggleAttribute('checked', state);
    }

    async #init() {
        await this.#initShadowDom();
        await this.#initInput();
    }

    async #initInput() {
        this.#input = this.querySelector('input');
        let master = this;
        if (this.#input) {
            master = this.#input;
        } else {
            this.#input = document.createElement('input');
            this.#input.type = 'checkbox';
            this.appendChild(this.#input);
        }

        InputToggle.observedAttributes.forEach(attr => {
            this.#states[attr] = master.getAttribute(attr);
        });
        this.#syncStates();

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                if (m.type === 'attributes' && InputToggle.observedAttributes.includes(m.attributeName)) {
                    if (this.#states[m.attributeName] !== m.target.getAttribute(m.attributeName)) {
                        this.#states[m.attributeName] = m.target.getAttribute(m.attributeName);
                        this.#syncStates();
                    }
                }
            });
        });
        observer.observe(this.#input, { attributes: true });

        this.#input.addEventListener('click', this.boundOnInputChange);
        this.#input.addEventListener('change', this.boundOnInputChange);
    }

    #onInputChange() {
        if (this.#states.checked !== (this.#input.checked ? '' : null)) {
            this.#input.toggleAttribute('checked', this.#input.checked);
        }
    }

    #syncStates() {
        Object.entries(this.#states)
            .forEach(([attr, value]) => {
                [this.#input, this]
                    .forEach(el => {
                        if (el.getAttribute(attr) !== value) {
                            if (value === null) {
                                el.removeAttribute(attr);
                            } else {
                                el.setAttribute(attr, value);
                            }
                        }
                    });
            });
        this.#input.checked = this.#states.checked !== null;
    }

    // Lifecycle hooks
    connectedCallback() {
        this.#initInput();
    }

    // removed from dom
    disconnectedCallback() {
        this.#input.removeEventListener('click', this.boundOnInputChange);
        this.#input.removeEventListener('change', this.boundOnInputChange);
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (!this.#input) return; // not yet initialized

        if (oldValue === newValue) return;
        if (this.#states[name] === newValue) return;

        this.#states[name] = newValue;
        this.#syncStates();
    }

    async #initShadowDom() {
        this.#root = this.attachShadow({ mode: 'closed' });
        this.#root.innerHTML = `
            <style>
                :host {
                    --toggle-size: 1lh;
                    --toggle-color-off: #ddd;
                    --toggle-color-on: var(--color-primary, #007bff);
                    --toggle-color-knob: #fff;
                }
                #toggle {
                    display: inline-block;
                    position: relative;
                    width: calc(var(--toggle-size) * 2);
                    height: var(--toggle-size);;
                    user-select: none;
                    border-radius: var(--toggle-size);
                    background-color: var(--toggle-color-off);
                    transition: background-color 0.3s;

                    & .knob {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        position: absolute;
                        top: 10%;
                        left: 5%;
                        height: 80%;
                        width: 40%;
                        border-radius: 50%;
                        background-color: var(--toggle-color-knob);
                        transition: transform 0.3s;
                        box-shadow: calc(var(--toggle-size) / 16) calc(var(--toggle-size) / 16) calc(var(--toggle-size) / 16) rgba(0, 0, 0, 0.1);

                        & .icon {
                            height: 60%;
                            aspect-ratio: 1;
                            fill: var(--toggle-color-off);
                        }
                    }
                }
                :host(:not([checked])) .icon-on,
                :host([checked]) .icon-off {
                    display: none;
                }
                :host([checked]) #toggle {
                    background-color: var(--toggle-color-on);

                    & .knob {
                        transform: translateX(120%);
                        box-shadow: calc(var(--toggle-size) / 16) calc(var(--toggle-size) / 16) calc(var(--toggle-size) / 16) rgba(0, 0, 0, 0.3);
                        
                        & .icon {
                            fill: var(--toggle-color-on);
                        }
                    }
                }
                ::slotted(input) {
                    position: absolute;
                    inset: 0px;
                    opacity: 0;
                    cursor: pointer;
                }
            </style>
            <div id="toggle">
                <div class="knob">
                    <svg class="icon icon-off" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 492 492">
                        <path d="m300.2 246 184-184a26.8 26.8 0 0 0 0-38L468 7.9a26.7 26.7 0 0 0-38 0l-184 184L62 7.8a26.7 26.7 0 0 0-38 0l-16.1 16a27 27 0 0 0 0 38.1l184 184-184 184a26.7 26.7 0 0 0 0 38L24 484.1a26.7 26.7 0 0 0 38 0l184-184 184 184a26.7 26.7 0 0 0 38 0l16.1-16c5.1-5.2 7.9-12 7.9-19.1 0-7.2-2.8-14-7.9-19l-184-184z"/>
                    </svg>
                    <svg class="icon icon-on" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 492 492">
                        <path d="m484.1 104.5-16-16.1a27 27 0 0 0-38.1 0L203.5 314.8 62 173.3c-5-5-11.8-7.8-19-7.8-7.2 0-14 2.8-19 7.8L7.9 189.4a26.7 26.7 0 0 0 0 38l159.7 159.8.7.9 16.1 15.8c5 5 11.8 7.6 19.1 7.6 7.3 0 14-2.5 19.1-7.6l16.1-16c.3-.2.5-.4.6-.7l244.8-244.7a26.9 26.9 0 0 0 0-38z"/>
                    </svg>
                </div>
                <slot></slot>
            <div>
        `;
        this.#toggle = this.#root.querySelector('#input');
    }
}