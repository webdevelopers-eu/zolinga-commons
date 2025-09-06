import WebComponent from '/dist/system/js/web-component.js';

/**
 * ticker-slider
 *
 * <ticker-slider [speed="SPEED"] [hidden="hidden"] [shuffle="true"]><img ...>...</ticker-slider>
 * 
 * Example:
 * 
 * <ticker-slider speed="120s">
 *    <img src="logo1.svg"/>
 *    <img src="logo2.svg"/>
 * </ticker-slider> 
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
  /** @type {HTMLElement} */ #clones;
  /** @type {HTMLSlotElement} */ #slot;

  constructor() {
    super();

    if (this.hasAttribute('speed') && this.getAttribute('speed').length) {
      this.style.setProperty('--speed', this.getAttribute('speed'));
    }

    if (this.getAttribute('shuffle') == 'true') {
      // Randomize children order
      this.#shuffleChildren();
    }

    this.ready(this.#init()).then(() => this.removeAttribute('hidden'));
  }

  #shuffleChildren() {
    const children = Array.from(this.children);
    for (let i = children.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [children[i], children[j]] = [children[j], children[i]];
    }
    this.append(...children);
  }

  async #init() {
    // Load closed shadow content with isolated styles
    this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
      mode: 'closed',
      allowScripts: false,
      inheritStyles: false
    });

    this.#track = this.#root.querySelector('.track');
    this.#clones = this.#root.querySelectorAll('.track-clone');
    this.#slot = this.#root.querySelector('slot');

    // Initial clone and on subsequent slot changes
    this.#slot.addEventListener('slotchange', () => this.#syncClone());
    this.#syncClone();
  }

  #syncClone() {
    if (!this.#track || !this.#clones.length) return;
    // Wipe previous clone content
    this.#clones.forEach(el => el.textContent = '');
    // Clone assigned elements (light DOM children of <ticker-slider>)
    const nodes = this.#slot.assignedElements ? this.#slot.assignedElements({ flatten: true }) : [];
    for (const n of nodes) {
      this.#clones.forEach(el => el.appendChild(n.cloneNode(true)));
    }
  }
}
