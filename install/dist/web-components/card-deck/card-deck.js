/**
 * Flip Deck of Cards
 *
 * Example:
 *
 *   <card-deck show-card="b" flip-speed="4" flip-perspective="100">
 *       <div card-name="a" class="o0" onclick="this.parentNode.showCard('b');">aaa</div>
 *       <div card-name="b" class="o1" onclick="this.parentNode.setAttribute('show-card', 'c');">bbb</div>
 *       <div card-name="c" class="o2" onclick="this.parentNode.showCard('a');">cccc</div>
 *   </card-deck>
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @since 2024-02-17
 */
class FlipCardDeck extends HTMLElement {
  static observedAttributes = ['show-card', 'flip-speed', 'flip-perspective'];
  #isLocked = false;
  #queue = [];
  #root;
  #observer;

  constructor() {
    super();
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(`
            /* make the self element a width to fit the contents */
            :host {
                --flip-perspective: 300px;
                --flip-speed: 0.5s;
                --flip-speed-half: calc(var(--flip-speed, 0.5s) / 2);

                display: inline-block;
                justify-items: center;
                align-items: center;
                width: fit-content;
                height: fit-content;
                contain: layout; /* scrollbars during animation fix */
            }
            .deck {
                display: inline-grid;
                grid-template-columns: 1fr;
                grid-template-rows: 1fr;
                perspective: var(--flip-perspective, 600px);
                justify-items: stretch;
                align-items: stretch;
            }
            /* place all slotted children to the first cell */
            ::slotted(*) {
                grid-column: 1;
                grid-row: 1;
                backface-visibility: hidden;
                transform-style: preserve-3d;
                margin: 0px;
            }
            ::slotted(*:not(.card-active, .card-deactivated)) {
                transform: rotateY(-180deg);
                transition: margin var(--flip-speed-half) linear;
                margin-top: 0%:
                margin-left: 0%;
                margin-bottom: -100%;
                margin-right: 0%;
                visibility: hidden;
            }
            ::slotted(.card-active) {
                transform: rotateY(0deg);
                transition: transform var(--flip-speed, 0.5s) linear, margin var(--flip-speed-half) linear;
                margin: 0% 0% 0% 0%;
            }
            ::slotted(.card-deactivated) {
                transform: rotateY(180deg);
                transition: transform var(--flip-speed, 0.5s) linear;
            }
        `);

    this.#root = this.attachShadow({"mode": 'closed', 'clonable': true});
    this.#root.innerHTML = `
            <div class="deck">
                <slot></slot>
            </div>
        `;
    this.#root.adoptedStyleSheets = [sheet];
    this.#installObserver();

    if (!this.hasAttribute('show-card')) {
      const firstCard = this.querySelector(':scope > *[card-name]')?.getAttribute('card-name');
      firstCard && this.showCard(firstCard);
    }

    this.dataset.ready = true;
  }

  // Install observer that will watch for new children
  // and will force reflow on new children so the initial
  // animation works
  #installObserver() {
    this.#observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (mutation.type === 'childList') {
          for (const node of mutation.addedNodes) {
            if (node.nodeType === Node.ELEMENT_NODE) {
              node.offsetHeight; // forces reflow
            }
          }
        }
      }
    });

    this.#observer.observe(this, {childList: true});
  }

  /**
   * listen on show-card attr change
   * @param {string} name - The name of the attribute that changed.
   * @param {string} oldValue - The previous value of the attribute.
   * @param {string} newValue - The new value of the attribute.
   */
  attributeChangedCallback(name, oldValue, newValue) {
    switch (name) {
      case 'show-card':
        this.showCard(newValue);
        break;
      case 'flip-speed':
        this.style.setProperty('--flip-speed', newValue + 's');
        break;
      case 'flip-perspective':
        this.style.setProperty('--flip-perspective', newValue + 'px');
        break;
    }
  }

  async showCard(newCardName) {
    // we are triggered primarily by the attribute change
    if (this.getAttribute('show-card') !== newCardName) {
      this.setAttribute('show-card', newCardName);
      return;
    }

    const {newCard, oldCard, otherCards} = this.#getCards(newCardName);
    if (!newCard || newCard === oldCard) return;

    return new Promise(async (resolve) => {
      await this.#obtainLock();
      newCard.classList.add('card-active');

      const callback = () => {
        oldCard?.classList.remove('card-deactivated');
        this.#releaseLock();
        resolve();
      };

      if (oldCard) { // initial state has no oldCard
        newCard.addEventListener('transitionend', callback, {once: true});
        // Animations can be interrupted or restarted or not run at all if hidden
        // so we need to remove the class manually if the event is not fired
        setTimeout(callback, parseFloat(getComputedStyle(newCard).getPropertyValue('--flip-speed')) * 1000);
      } else {
        this.#releaseLock();
        resolve();
      }

      oldCard?.classList.add('card-deactivated');
      oldCard?.classList.remove('card-active');

      otherCards.forEach((el) => el.classList.remove('card-active'));
    });
  }

  async #obtainLock() {
    return new Promise((resolve) => {
      if (!this.#isLocked) {
        this.#isLocked = true;
        resolve();
      } else {
        this.#queue.push(resolve);
      }
    });
  }

  #releaseLock() {
    if (this.#queue.length > 0) {
      const nextResolver = this.#queue.shift();
      this.#isLocked = true;
      nextResolver();
    } else {
      this.#isLocked = false;
    }
  }

  #getCards(newCardName) {
    const newCard = Array.from(this.children).find((el) => el.getAttribute('card-name') === newCardName);
    const oldCard = Array.from(this.children).find((el) => el.classList.contains('card-active'));
    const otherCards = Array.from(this.children).filter((el) => el !== newCard && el !== oldCard);

    return {
      newCard,
      oldCard,
      otherCards,
    };
  }
}

export default FlipCardDeck;
