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
                const script = document.createElement('script');
                script.src = resource;
                el = this.appendChild(script);
                break;
            case 'css':
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = resource;
                el = this.appendChild(link);
                break;
            default:
                throw new Error(`Unknown resource type: ${resType}`);
        }
        await new Promise((resolve, reject) => {
            el.onload = resolve;
            el.onerror = reject;
        });
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