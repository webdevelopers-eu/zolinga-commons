import WebComponent from '/dist/system/js/web-component.js';

/**
 * Watch the element for overflow and add class "hamburger-active" if it is overflowing.
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @since 2024-04-06
 */
export default class HamburgerMenu extends WebComponent {
  #menu;
  #canary;
  #hamburger;
  #resizeObserver;
  #mutationObserver;

  constructor() {
    super();

    this.classList.add('hamburger-menu');

    this.ready(this.#init())
      .then(() => this.classList.add('hamburger-ready'));
  }

  async #init() {
    await this.installStyles();
    this.removeAttribute('hidden');

    this.#menu = this.querySelector(':scope > *:is(nav, ul, ol, menu)');

    this.#resizeObserver = new ResizeObserver((entries) => this.#updateOverflowClass());
    this.#resizeObserver.observe(this, { box: 'border-box' });

    this.#mutationObserver = new MutationObserver((mutations) => this.#makeCanary());
    this.#mutationObserver.observe(this.#menu, { childList: true, subtree: true, attributes: true });

    this.#makeCanary();
    this.#updateOverflowClass();

    this.addEventListener('click', (ev) => {
      if (this.matches('.hamburger-active')) {
        this.classList.toggle('hamburger-open');
      }
    });
  }

  async installStyles() {
    const svg = document.createElement('div');
    svg.innerHTML = `
        <svg class="hamburger-icon" xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'>
            <path stroke='currentColor' stroke-linecap='round' stroke-width='2' d='M4 18h16M4 12h16M4 6h16'/>
        </svg>
    `;
    this.#hamburger = this.appendChild(svg.firstElementChild);

    if (document.querySelector('#hamburger-styles')) {
      return;
    }

    const style = document.createElement('link');
    style.rel = 'stylesheet';
    style.href = new URL(import.meta.url.replace(/\.js$/, '.css'));
    style.id = 'hamburger-styles';

    const promise = new Promise((resolve) => {
      style.addEventListener('load', resolve);
    });

    document.head.appendChild(style);

    return promise;
  }

  #updateOverflowClass() {
    const isOverflowing = this.clientWidth < this.#canary.clientWidth;
    if (!this.classList.toggle('hamburger-active', isOverflowing)) {
      this.classList.remove('hamburger-open'); // hamburger is inactive, reset it
    }
  }

  #makeCanary() {
    if (this.#canary) {
      this.#canary.remove();
    }
    this.#canary = this.#menu.cloneNode(true);
    this.#canary.classList.add('hamburger-canary');
    this.appendChild(this.#canary);
  }
}
