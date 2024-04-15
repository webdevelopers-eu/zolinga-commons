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

        this.getAttribute('assets').split(/\s+/)
            .forEach(asset => this.#appendAsset(asset));

        this.dataset.ready = true;
    }

    #appendAsset(asset) {
        const atom = this.#getAssetAtom(asset);
        atom.dependencies.forEach(r => this.#appendAsset(r));
        atom.resources.forEach(r => this.#appendResource(r));
        const el = document.createElement(atom.tag);
    }

    #appendResource(resource) {
        const resType = resource.split('.').pop();
        resource += `?v=${this.#resources.revision}`;
        switch (resType) {
            case 'js':
                const script = document.createElement('script');
                script.src = resource;
                this.appendChild(script);
                break;
            case 'css':
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = resource;
                this.appendChild(link);
                break;
            default:
                throw new Error(`Unknown resource type: ${resType}`);
        }
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