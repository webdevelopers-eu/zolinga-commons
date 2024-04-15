<?php

declare(strict_types=1);

namespace Zolinga\Commons\Resources;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\Cms\Events\ContentElementEvent;
use DOMDocumentFragment;

/**
 * Handler for <c-resources> element.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-15
 */
class ResourcesElement implements ListenerInterface
{
    /**
     * List of Assets
     *
     * @var array{revision: int, list: AssetAtom[]}
     */
    private array $data;

    public function __construct()
    {
        $this->load();

        if ($this->data['revision'] !== $this->getResourceRevision()) {
            $this->discover();
            $this->load();
        }
    }

    private function getResourceRevision(): int
    {
        return filemtime('module://zolinga-commons/data/resources.json') ?: 0;
    }

    private function load(): void
    {
        $file = 'public://zolinga-commons/resources.json';

        $this->data = json_decode(file_get_contents($file) ?: '[]', true)
            or throw new \Exception('Failed to read resources list: ' . $file);

        $this->data['list'] = array_map(
            /** @phpstan-ignore-next-line */
            fn (array $data) => new AssetAtom($data),
            $this->data['list']
        )
            or throw new \Exception('Failed to read resources list.');
    }


    /**
     * Handle <c-resources> element.
     * 
     * Syntax:
     * 
     * <c-resources assets="id1 id2 ..."></c-resources>
     *
     * @param ContentElementEvent $event
     * @return void
     */
    public function onResources(ContentElementEvent $event): void
    {
        $assets = preg_split('/\s+/', $event->input->getAttribute('assets')) ?: [];
        foreach ($assets as $asset) {
            $this->addAsset($event->output, $asset);
        }
        $event->setStatus($event::STATUS_OK, 'Served assets.');
    }

    private function addAsset(DOMDocumentFragment $frag, string $asset): void
    {
        $atom = $this->getAtom($asset);
        foreach ($atom->dependencies as $req) {
            $this->addAsset($frag, $req);
        }

        foreach ($atom->resources as $res) {
            $ext = pathinfo($res, PATHINFO_EXTENSION);
            $res .= '?rev=' . $this->data['revision'];
            switch ($ext) {
                case 'css':
                    $this->createElement($frag, 'link', ['rel' => 'stylesheet', 'href' => $res], $res);
                    break;
                case 'js':
                    $this->createElement($frag, 'script', ['src' => $res, 'type' => 'module'], $res);
                    break;
                default:
                    throw new \Exception("ZolingaCommon: Unknown asset type '$ext'.");
            }
        }
    }

    /**
     * Create an element and append it to the fragment.
     *
     * @param DOMDocumentFragment $frag
     * @param string $name
     * @param array<string, string> $attrs
     * @param string $res
     * @return void
     */
    private function createElement(DOMDocumentFragment $frag, string $name, array $attrs, string $res): void
    {
        $el = $frag->ownerDocument->createElement($name);
        foreach ($attrs as $key => $val) {
            $el->setAttribute($key, $val);
        }
        $frag->appendChild($el);
    }

    private function getAtom(string $id): AssetAtom
    {
        foreach ($this->data['list'] as $atom) {
            if ($atom->id === $id) {
                return $atom;
            }
        }
        throw new \Exception("ZolingaCommon: Asset atom '$id' not found.");
    }

    public function onInstall(): void
    {
        $this->discover();
    }

    private function discover(): void
    {
        global $api;

        // Read default resources list
        $file = 'module://zolinga-commons/data/resources.json';
        $list = json_decode(file_get_contents($file) ?: '[]', true)
            or throw new \Exception('Failed to read resources list: ' . $file);

        $list = array_map(
            /** @phpstan-ignore-next-line */
            fn (array $data) => new AssetAtom($data),
            $list['list']
        )
            or throw new \Exception('Failed to read resources list: ' . $file);

        $data = [
            'revision' => $this->getResourceRevision(),
            'list' => $list
        ];

        // Here we will fire some event to let other modules integrate with 
        // <c-resources> element.
        file_put_contents('public://zolinga-commons/resources.json', json_encode($data))
            or throw new \Exception('Failed to write resources list: ' . $file);
    }
}
