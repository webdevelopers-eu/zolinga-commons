export default class ResourcesElement extends HTMLElement {
    static #resourcesPromise = null;
    #resources;

    constructor() {
        super();
        
        if (!ResourcesElement.#resourcesPromise) {
            ResourcesElement.#resourcesPromise = fetch('/data/zolinga-commons/resources.json')
                .then(resp => resp.json());
        }

        this.#init();
    }

    async #init() {
        this.#resources = await ResourcesElement.#resourcesPromise;

        let promises = [];
        this.getAttribute('assets').split(/\s+/)
            .forEach(asset => promises.push(this.#appendAsset(asset)));

        await Promise.all(promises);
        this.dataset.ready = true;
        this.dispatchEvent(new CustomEvent('web-component-ready'));
    }

    async #appendAsset(asset) {
        let promises = [];
        const atom = this.#getAssetAtom(asset);
        atom.dependencies.forEach(r => promises.push(this.#appendAsset(r)));
        atom.resources.forEach(r => promises.push(this.#appendResource(r)));
        await Promise.all(promises);
    }

    async #appendResource(resource) {
        const resType = resource.split('.').pop();
        let el;
        resource += `?v=${this.#resources.revision}`;
        switch (resType) {
            case 'js':
                el = document.createElement('script');
                el.src = resource;
                break;
            case 'css':
                el = document.createElement('link');
                el.rel = 'stylesheet';
                el.href = resource;
                break;
            default:
                throw new Error(`Unknown resource type: ${resType}`);
        }
        const promise = new Promise((resolve, reject) => {
            el.onload = resolve;
            el.onerror = reject;
        });
        this.appendChild(el);
        await promise;
    }

    #getAssetAtom(asset) {
        // Find the object with id === asset in this.#resources
        for (const obj of this.#resources.list) {
            if (obj.id === asset) {
                return obj;
            }
        }
        throw new Error(`Asset not found: ${asset}`);
    }
}