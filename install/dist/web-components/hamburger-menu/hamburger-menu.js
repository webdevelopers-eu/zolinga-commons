import WebComponent from '/dist/system/js/web-component.js';

/**
 * Watch the element for overflow and add class "hamburger-icon-visible" if it is overflowing.
 * 
 * Special supported classes:
 * 
 *  .for-active - visible only when hamburger is active and menu is collapsed into pull down menu
 *  .for-inactive - visible only when hamburger is inactive and menu is displayed in full width
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @since 2024-04-06
 */
export default class HamburgerMenu extends WebComponent {
  #menu;
  #canary;
  #icon;
  #popup;
  #resizeObserver;
  #mutationObserver;
  #picker;
  #updating = false;

  constructor() {
    super();

    this.classList.add('hamburger-menu');
    this.classList.add('hamburger-ready'); this.ready(); // @todo remove

    this.ready(this.#init())
      .then(() => this.classList.add('hamburger-ready'));
  }

  async #init() {
    await this.installStyles();
    this.removeAttribute('hidden');

    this.#menu = this.querySelector(':scope > *:is(nav, ul, ol, menu)');
    this.#canary = this.querySelector('.hamburger-canary');

    this.#createMenuPopup();
    this.#createCanary();
    
    this.#resizeObserver = new ResizeObserver((entries) => this.#onResize());
    this.#resizeObserver.observe(this, { box: 'border-box' });

    this.#mutationObserver = new MutationObserver((mutations) => this.#createCanary());
    this.#mutationObserver.observe(this.#menu, { childList: true, subtree: true, attributes: true });

    this.#onResize();

    this.#picker.addEventListener('click', (ev) => {
      if (!ev.target.matches('li, a, .hamburger-icon')) {
        return;
      }

      const isOpen = this.#popup.open;

      if (isOpen) {
        console.log("Closing hamburger menu");
        this.#popup.close();
        this.classList.remove('hamburger-active');
      } else {
        console.log("Opening hamburger menu");
        this.classList.add('hamburger-active');
        this.#popup.showModal();
        this.#popup.focus();
      }
    });
  }

  async installStyles() {
    const doc = this.getRootNode();
   
    let promise = Promise.resolve();
    if (!doc.querySelector('#hamburger-styles')) {
      const style = document.createElement('link');
      style.rel = 'stylesheet';
      style.href = new URL(import.meta.url.replace('.js', '.css'));
      style.id = 'hamburger-styles';

      promise = new Promise((resolve) => {
        style.addEventListener('load', resolve);
      });

      (doc.querySelector('head') || doc).appendChild(style);
    }

    return promise;
  }

  #createMenuPopup() {
    this.#picker = document.createElement('div');
    this.#picker.classList.add('hamburger-picker');
    this.appendChild(this.#picker);
    
    this.#picker.innerHTML = `
      <svg class="hamburger-icon" xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'>
          <path stroke='currentColor' stroke-linecap='round' stroke-width='2' d='M4 18h16M4 12h16M4 6h16'/>
      </svg>
      <dialog closedby="any">
      </dialog>
    `;

    this.#icon = this.#picker.querySelector('.hamburger-icon');
    this.#popup = this.#picker.querySelector('dialog');
  }

  #onResize() {
    if (this.#updating) return;
    this.#updating = true;

    const maxSize = Math.max(0, this.clientWidth - this.#picker.clientWidth);
    this.#canary.style.setProperty('width', maxSize + 'px', 'important');

    // Cycle all subelements in canary and find the index of the first one that overflows right border.
    Array.from(this.#canary.children).forEach((child, idx) => {
      const overflows = this.#overflowsHorizontally(child, this.#canary);
      (overflows ? this.#popup : this.#menu).appendChild(child.originalElement);      
    });  

    this.classList.toggle('hamburger-icon-visible', !!this.#popup.children.length);
    this.#updating = false;
  }

  #createCanary() {
    if (this.#canary) {
      this.#canary.remove();
    }
    
    this.#canary = this.#menu.cloneNode(true);
    this.#canary.classList.add('hamburger-canary');
    this.#canary.innerHTML = '';

    const items = [
      ...this.#menu.children,
      ...this.#popup.children
    ];

      // Append moved elements too
    this.#canary.append(...items.map(el => {
      const clone = el.cloneNode(true);
      clone.originalElement = el;
      return clone;
    }));

    this.#canary.classList.add('hamburger-canary');
    this.appendChild(this.#canary);
  }

  #overflowsHorizontally(child, parent) {
    if (!child.clientWidth) return false; // skip invisible elements

    const cr = child.getBoundingClientRect();
    const pr = parent.getBoundingClientRect();

    return cr.left < pr.left || cr.right > pr.right;
  }
}
