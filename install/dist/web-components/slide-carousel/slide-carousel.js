/**
 * This is an horizontal carousel of slides.
 * 
 * It is a flex container with horizontal flex direction.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-13
 */
export default class SlideCarousel extends HTMLElement {
    static observedAttributes = ['data-active'];
    #root;
    #slides;
    #style;
    #lastActiveNames;

    constructor() {
        super();

        this.#root = this.attachShadow({ mode: 'open' });
        this.#root.innerHTML = "<slot></slot>";
        this.#addStyle();
        this.#slides = this;
        this.dataset.ready = true; // Must be before #markActiveSlide otherwise there is inline-block applied and it activates all tabs for a moment
        if (!this.dataset.active) { // otherwise it will be called from attributeChangedCallback automatically
            this.#markActiveSlide();
        }
        this.#slides.addEventListener('scroll', this.#markActiveSlide.bind(this), { passive: true });
    }

    #markActiveSlide(ev) {
        if (ev && ev.target != this.#slides) return;

        const slides = this.querySelectorAll(':scope > *');
        const scrollLeft = Math.ceil(this.#slides.scrollLeft + this.#slides.offsetLeft); /* slotted items do not treat #slides as relative so we need to add offsetLeft */
        const scrollRight = Math.floor(this.#slides.scrollLeft + this.#slides.offsetLeft + this.#slides.offsetWidth);

        let active = [];
        let position = -1; // before active slide(s)
        slides.forEach((slide, index) => {
            if (position == 1) { // speed optimization - after active slide(s)
                slide.dataset.active = false;
                return;
            }

            const slideLeft = Math.ceil(slide.offsetLeft);
            const slideRight = Math.floor(slide.offsetLeft + slide.offsetWidth);
            
            if (scrollLeft < slideRight && slideLeft < scrollRight) {
                slide.dataset.active = true;
                active.push(slide);
                position = 0; // in active slide(s)
            } else {
                slide.dataset.active = false;
                if (position == 0) {
                    position = 1; // after active slide(s)
                }
            }
            // console.log("slide: %o (%s, %s), scroll (%s, %s) = %o", slide, slideLeft, slideRight, scrollLeft, scrollRight, slide.dataset.active);
        });

        this.querySelectorAll(':scope > [data-active="true"] [disabled].auto-enable')
            .forEach(el => el.removeAttribute('disabled'));

        this.#lastActiveNames = active.map(slide => slide.dataset.name).join(' ');
        if (this.dataset.active != this.#lastActiveNames) {
            this.dataset.active = this.#lastActiveNames;
            this.dispatchEvent(new CustomEvent('slide-carousel:change', { detail: { active: active }, bubbles: true, composed: true }));
            // console.log("SlideCarousel: active = %s", this.dataset.active);
        }
    }

    connectedCallback() {
        this.#markActiveSlide();
    }

    disconnectedCallback() {
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        switch (name) {
            case 'data-active':
                if (this.#lastActiveNames != newValue) { // did we set new attribute?
                    console.log("attributeChangedCallback: %s = %s -> %s", name, oldValue, newValue);
                    const first = this.dataset.active.trim().split(' ')[0];
                    if (first) {
                        const slide = this.querySelector(`[data-name="${first}"]`);
                        if (slide) {
                            slide.scrollIntoView({ behavior: oldValue !== null ? 'smooth' : 'instant' });
                        }
                    }
                }
                break;
        }
    }

    #addStyle() {
        const style = new CSSStyleSheet();
        style.replaceSync(`
                :host {
                    display: flex !important;
                    flex-direction: row !important;
                    flex-wrap: nowrap !important;
                    /* position: relative; does not work for slotted items */
                    padding: 0px;
                    gap: 0px;
                    transition: transform 0.5s;
                    overflow: auto !important;
                    height: 100%;
                    width: 100%;

                    scroll-snap-type: both mandatory !important;
                    overscroll-behavior: contain;
                    scrollbar-width: none !important;

                    &::-webkit-scrollbar, &::scrollbar, &::-webkit-scrollbar-thumb {
                        display: none;
                    }
                }
                ::slotted(*) {
                    flex: 0 0 100%;
                    height: 100%;
                    scroll-snap-stop: always !important;
                    scroll-snap-align: center !important;
                    overflow: auto;
                }
            `);
        this.#root.adoptedStyleSheets = [style];
    }
}
