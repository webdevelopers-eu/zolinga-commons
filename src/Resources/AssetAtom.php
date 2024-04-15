<?php
declare(strict_types=1);

namespace Zolinga\Commons\Resources;

// "id": "forms/css",
// "dependencies": [],
// "resources": [
//     "/dist/zolinga-common/forms/forms.css"
// ]
class AssetAtom implements \JsonSerializable {
    public readonly string $id;

    /**
     * List of other assets that this asset dependencies.
     *
     * @var String[]
     */
    public readonly array $dependencies;

    /**
     * List of resources that this asset provides.
     *
     * @var String[]
     */
    public readonly array $resources;

    /**
     * Asset atom data.
     *
     * @param array{"id": string, "dependencies": ?string[], "resources": string[]} $data
     */
    public function __construct(array $data) {
        $this->id = $data['id'];
        $this->dependencies = $data['dependencies'] ?? [];
        $this->resources = $data['resources'];
    }

    /**
     * JSON serialization.
     *
     * @return array{id: string, dependencies: string[], resources: string[]}
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'dependencies' => $this->dependencies,
            'resources' => $this->resources
        ];
    }
}