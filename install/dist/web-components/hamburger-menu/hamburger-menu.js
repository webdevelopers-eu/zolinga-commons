/**
 * Watch the element for overflow and add class "hamburger-active" if it is overflowing.
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @since 2024-04-06
 */
export default class HamburgerMenu extends HTMLElement {
  #menu;
  #canary;
  #observer;

  constructor() {
    super();
    this.#init();
    this.dataset.ready = 'true';
  }

  async #init() {
    await this.installStyles();

    this.#menu = this.querySelector(':scope > *:is(nav, ul, ol, menu)');
    this.#observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        if (entry.target === this.#menu) { // menu changed
          this.#makeCanary();
        } else if (entry.target === this) {
          const isOverflowing = this.clientWidth < this.#canary.clientWidth;
          console.log(this.clientWidth, entry.target.clientWidth, isOverflowing );
          if (!this.classList.toggle('hamburger-active', isOverflowing)) {
            this.classList.remove('hamburger-open'); // hamburger is inactive, reset it
          }
        }
      }
    });

    this.#observer.observe(this, {box: 'border-box'});
    this.#observer.observe(this.#menu, {box: 'border-box'});
    this.#makeCanary();

    this.addEventListener('click', (ev) => {
        if (this.classList.contains('hamburger-active') && ev.target === this) {
            this.classList.toggle('hamburger-open');
        }
    });
  }

  async installStyles() {
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

  #makeCanary() {
    this.#canary = this.#menu.cloneNode(true);
    this.#canary.classList.add('hamburger-canary');
    this.appendChild(this.#canary);
  }
}
