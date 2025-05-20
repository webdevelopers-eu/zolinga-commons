import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';
import zTemplate from '/dist/system/js/z-template.js';
import api from '/dist/system/js/api.js';
import { gettext, ngettext } from "/dist/zolinga-intl/gettext.js?zolinga-commons";


/**
 * IPD Alerts admin pane
 * 
 * this.onClose - callback function to be called when the editor is closed
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-03 
 */
export default class ContactForm extends WebComponent {
    #form;
    #thankYou;


    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        this.#form = this.querySelector(':scope form');
        this.#thankYou = this.querySelector(':scope thank-you');

        if (!this.#form || !this.#thankYou) {
            console.error('ContactForm: Missing <form> (%o) or <thank-you> (%o) element inside %o.', 
                this.#form, this.#thankYou, this);
            return;
        }

        this.#form.addEventListener('submit', async (e) => this.#formSubmit(e));
    }

    async #formSubmit(e) {
        e.preventDefault();

        const formData = new FormData(this.#form);
        const data = Object.fromEntries(formData.entries());

        try {
            this.#form.setAttribute('disabled', '');
            const packet = await api.dispatchEvent('contact-form', {data});
            if (packet.status !== 200) {
                throw new Error(`Invalid response from server: ${packet.status} ${packet.statusMessage}`);
            }
        } catch (error) {
            console.error('ContactForm: Error while sending form data: %o', error);
            alert(gettext('An error occurred. Please try again later.'));
            this.#toggleVisibility(true);
            return;
        }

        this.#toggleVisibility(false);
        setTimeout(() => {
            this.#form.reset();
            this.#toggleVisibility(true);
        }, 15000);
    }

    #toggleVisibility(formVisible) {
        if (formVisible) {
            this.#form.removeAttribute('hidden');
            this.#form.removeAttribute('disabled');

            this.#thankYou.setAttribute('hidden', '');
        } else {
            this.#form.setAttribute('hidden', '');
            this.#form.setAttribute('disabled', '');

            this.#thankYou.removeAttribute('hidden');
        }
    }
}