<?php
/**
 * Copyright 2017 Sinkevich Alexey
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Movephp\ClassLoader;

use Movephp\ClassLoader\Map;
use Psr\Cache, Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class Autoload
 * @package Movephp\ClassLoader
 */
class Autoload
{
    /**
     * @var Cache\CacheItemPoolInterface
     */
    private $cachePool = null;

    /**
     * @var string
     */
    private $cacheItemKey = '';

    /**
     * @var Map\MapInterface
     */
    private $cleanMap;

    /**
     * @var Map\MapInterface
     */
    private $map;

    /**
     * @var array
     */
    private $loadedClasses = [];

    /**
     * @var string[]
     */
    private $scanPaths = [];

    /**
     * @var string[]
     */
    private $excludingPaths = [];

    /**
     * @var string[]
     */
    private $overridePaths = [];

    /**
     * @var bool
     */
    private $scanned = false;

    /**
     * Autoload constructor.
     * @param Map\MapInterface $cleanMap
     * @param Cache\CacheItemPoolInterface|null $cachePool
     * @param string $cacheKeyNamespace
     */
    public function __construct(Map\MapInterface $cleanMap, Cache\CacheItemPoolInterface $cachePool = null, string $cacheKeyNamespace = '')
    {
        $this->cleanMap = $cleanMap;
        if ($cachePool) {
            $this->cachePool = $cachePool;
            $this->cacheItemKey = $cacheKeyNamespace . ($cacheKeyNamespace ? '_' : '') . 'movephp_classloader';
        }
    }

    //public function

    /**
     * @param string[] ...$scanPaths
     */
    public function setScanPaths(string ...$scanPaths): void
    {
        $this->scanPaths = $scanPaths;
    }

    /**
     * @param string[] ...$excludePaths
     */
    public function setExcludingPaths(string ...$excludePaths): void
    {
        $this->excludingPaths = $excludePaths;
    }

    /**
     * @param string[] ...$overridePaths
     */
    public function setOverridePaths(string ...$overridePaths): void
    {
        $this->overridePaths = $overridePaths;
    }

    /**
     *
     */
    public function makeMap(): void
    {
        $this->scanned = false;
        if (!($this->map = $this->loadMapFromCache())) {
            $this->map = clone($this->cleanMap);
            $this->scanAndSaveToCache();
        }
    }

    /**
     *
     */
    public function updateMap(): void
    {
        $this->scanAndSaveToCache();
    }

    /**
     * @return Map\MapInterface
     */
    public function map(): Map\MapInterface
    {
        if (!$this->map) {
            $this->makeMap();
        }
        return $this->map;
    }

    /**
     * @param string $className
     * @param Map\ItemInterface &$item
     * @return bool
     */
    public function isClassExists(string $className, Map\ItemInterface &$item = null): bool
    {
        $className = trim($className);
        if (!isset($this->map()->classes()[$className])) {
            return false;
        }
        $item = $this->map()->classes()[$className];
        return $item instanceof Map\ItemInterface;
    }

    /**
     *
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'load'], true, true);
    }

    /**
     * @param string $className
     */
    public function load(string $className): void
    {
        $className = trim($className);
        if (!$this->isClassExists($className, $item)) {
            return;
        }
        if ($item instanceof Map\ItemInterface && $item->isSafeInclude()) {
            $this->loadedClasses[$className] = 1;
            include_once($item->getFilePath());
        }
    }

    /**
     * @param string $className
     * @return bool
     */
    public function isClassLoaded(string $className): bool
    {
        $className = trim($className);
        return isset($this->loadedClasses[$className]);
    }

    /**
     * @return null|Cache\CacheItemPoolInterface
     */
    private function getCachePool(): ?Cache\CacheItemPoolInterface
    {
        return $this->cachePool;
    }

    /**
     * @return null|Cache\CacheItemInterface
     */
    private function getCacheItem(): ?Cache\CacheItemInterface
    {
        $cachePool = $this->getCachePool();
        return $cachePool ? $cachePool->getItem($this->cacheItemKey) : null;
    }

    /**
     * @return string[]
     * @throws Exception\ScanPathsRequired
     */
    private function getScanPaths(): array
    {
        if (!count($this->scanPaths)) {
            throw new Exception\ScanPathsRequired(
                sprintf(
                    'Use %s::setScanPaths() to specify one or more paths for scan',
                    __CLASS__
                )
            );
        }
        return $this->scanPaths;
    }

    /**
     * @return string[]
     */
    private function getExcludingPaths(): array
    {
        return $this->excludingPaths;
    }

    /**
     * @return string[]
     */
    private function getOverridePaths(): array
    {
        return $this->overridePaths;
    }

    /**
     * @return Map\MapInterface|null
     */
    private function loadMapFromCache(): ?Map\MapInterface
    {
        $cacheItem = $this->getCacheItem();
        if ($cacheItem && $cacheItem->isHit() && ($cachedMap = $cacheItem->get()) instanceof Map\MapInterface) {
            return $cachedMap;
        }
        return null;
    }

    /**
     *
     */
    private function scanAndSaveToCache(): void
    {
        if ($this->scanned) {
            return;
        }
        $this->scanned = true;
        $this->map()->scan(
            $this->getScanPaths(),
            $this->getExcludingPaths(),
            $this->getOverridePaths()
        );

        $cacheItem = $this->getCacheItem();
        if ($cacheItem) {
            $cacheItem->set($this->map());
            $this->getCachePool()->save($cacheItem);
        }
    }
}