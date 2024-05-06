<?php

declare(strict_types=1);

namespace Zolinga\Commons;
use WeakReference;

/**
 * Object cache trait.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-06
 */
trait ObjectCacheTrait
{
    /**
     * List of weakly referenced instances
     *
     * @var array<WeakReference> $cache
     */
    static private array $cache = [];

    /**
     * Search for an object in the cache and return it if found
     *
     * @param int $id
     * @return Object|null
     */
    static private function getObjectFromCache(int $id): Object|null
    {
        // Search for the object in the cache
        foreach (self::$cache as $k => $weak) {
            /** @var Object|null $obj */
            $obj = $weak->get();

            // Remove the weak reference if object was destroyed
            if ($obj === null) {
                unset(self::$cache[$k]);
                continue;
            }

            if ($obj->id === $id) {
                return $obj;
            }
        }
        return null;
    }

    static private function addObjectToCache(Object $obj): void
    {
        self::$cache[] = WeakReference::create($obj);
    }

    static private function removeObjectFromCache(Object $removeObj): bool
    {
        foreach (self::$cache as $k => $weak) {
            /** @var Object|null $obj */
            $obj = $weak->get();
            if ($obj === null || $obj === $removeObj) {
                unset(self::$cache[$k]);
                return true;
            }
        }
        return false;
    }
}
