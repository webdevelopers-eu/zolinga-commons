import WebComponent from '/dist/system/js/web-component.js';

/**
 * ticker-slider
 *
 * Closed, style-isolated horizontal ticker. Loads its HTML and CSS and
 * exposes a simple structure for marquee-like infinite scrolling.
 *
 * Shadow DOM layout (closed):
 * <main>
 *   <div class="ticker">
 *     <div class="track"><slot></slot></div>
 *     <div class="track track-clone" aria-hidden="true"></div>
 *   </div>
 * </main>
 *
 * Notes
 * - We duplicate slotted children into the ".track-clone" to enable
 *   seamless horizontal loops via CSS animations.
 * - Keep animation CSS minimal; you can override in page CSS variables:
 *   --ticker-gap, --ticker-speed, etc.
 */
export default class TickerSlider extends WebComponent {
  /** @type {ShadowRoot} */ #root;
  /** @type {HTMLElement} */ #track;
  /** @type {HTMLElement} */ #clone;
  /** @type {HTMLSlotElement} */ #slot;

  constructor() {
    super();
    this.ready(this.#init());
  }

  async #init() {
    // Load closed shadow content with isolated styles
    this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
      mode: 'closed',
      allowScripts: false,
      inheritStyles: false
    });

    this.#track = this.#root.querySelector('.track');
    this.#clone = this.#root.querySelector('.track-clone');
    this.#slot = this.#root.querySelector('slot');

    // Initial clone and on subsequent slot changes
    this.#slot.addEventListener('slotchange', () => this.#syncClone());
    this.#syncClone();
  }

  #syncClone() {
    if (!this.#track || !this.#clone) return;
    // Wipe previous clone content
    this.#clone.textContent = '';
    // Clone assigned elements (light DOM children of <ticker-slider>)
    const nodes = this.#slot.assignedElements ? this.#slot.assignedElements({ flatten: true }) : [];
    for (const el of nodes) {
      const clone = el.cloneNode(true);
      this.#clone.appendChild(clone);
    }
  }
}
