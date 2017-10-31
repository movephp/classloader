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

namespace Movephp\ClassLoader\Map;

use Movephp\ClassLoader\Exception;

/**
 * Class Map
 * @package Movephp\ClassLoader\Map
 */
class Map implements MapInterface
{
    /**
     * @var string
     */
    private $itemClass = Item::class;

    /**
     * @var ItemInterface[]
     */
    private $classes = [];

    /**
     * @var ItemInterface[]
     */
    private $ignored = [];

    /**
     * Map constructor.
     * @param string $itemClass
     * @throws Exception\ItemClassInvalid
     */
    public function __construct(string $itemClass = '')
    {
        if ($itemClass === '') {
            return;
        }
        if (!class_exists($itemClass)) {
            throw new Exception\ItemClassInvalid(
                sprintf(
                    'Class "%s" in not exists',
                    $itemClass
                )
            );
        }
        if (!is_subclass_of($itemClass, ItemInterface::class)) {
            throw new Exception\ItemClassInvalid(
                sprintf(
                    'Class "%s" is not implementing interface "%s"',
                    $itemClass,
                    ItemInterface::class
                )
            );
        }
        if (!(new \ReflectionClass($itemClass))->isInstantiable()) {
            throw new Exception\ItemClassInvalid(
                sprintf(
                    'Class "%s" is not instantiable',
                    $itemClass
                )
            );
        }
        $this->itemClass = $itemClass;
    }

    /**
     * @param string[] $scanPaths
     * @param string[] $excludePaths
     * @param string[] $overridePaths
     * @return int
     * @throws Exception\ClassDuplicateException
     */
    public function scan(array $scanPaths, array $excludePaths = [], array $overridePaths = []): int
    {
        /**
         * @var ItemInterface[] $oldFiles
         */
        $oldFiles = [];
        foreach ($this->classes as $item) {
            $oldFiles[$item->getFilePath()] = $item;
        }
        foreach ($this->ignored as $item) {
            $oldFiles[$item->getFilePath()] = $item;
        }
        $scanPaths = $this->normalizePathsArray($scanPaths);
        $excludePaths = $this->normalizePathsArray($excludePaths);
        $overridePaths = $this->normalizePathsArray($overridePaths);

        /**
         * @var ItemInterface[] $all
         */
        $all = [];
        $filesToScan = array_unique($this->glob($scanPaths, $excludePaths));
        foreach ($filesToScan as $file) {
            if (isset($oldFiles[$file])) {
                // File was parsed before, check if it was changed
                if ($this->checkDiff($file, $oldFiles[$file])) {
                    $item = new $this->itemClass($file, $this);
                    if (!$item->isValid()) {
                        continue;
                    }
                } else {
                    $item = $oldFiles[$file];
                    if (!$item instanceof ItemInterface) {
                        continue;
                    }
                    $item->onMapUpdate();
                }
            } else {
                // File was not parsed before. Do it
                $item = new $this->itemClass($file, $this);
                if (!$item->isValid()) {
                    continue;
                }
            }
            $name = $item->getName();
            if (isset($all[$name])) {
                $dup = $all[$name];
                foreach ($overridePaths as $overridePath) {
                    $overridePath .= DIRECTORY_SEPARATOR . '*';
                    if (fnmatch($overridePath, $dup->getFilePath())) {
                        continue(2);
                    }
                    if (fnmatch($overridePath, $item->getFilePath())) {
                        $all[$name] = $item;
                        continue(2);
                    }
                }
                throw new Exception\ClassDuplicateException(
                    sprintf(
                        'Class "%s" is duplicated in files "%s" and "%s"',
                        $name,
                        $dup->getFilePath(),
                        $item->getFilePath()
                    )
                );
            }
            $all[$name] = $item;
        }

        $this->classes = [];
        $this->ignored = [];
        foreach ($all as $item) {
            if ($item->isPHPUnitTest() || $item->isPartOfComposer()) {
                $this->ignored[$item->getName()] = $item;
            } else {
                $this->classes[$item->getName()] = $item;
            }
        }

        // Warm up caching methods
        foreach ($this->classes as $item) {
            $item->getParents();
            $item->getInheritors();
        }

        return count($this->classes);
    }

    /**
     * @return ItemInterface[]
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * @param int $type
     * @param string $parentClassName
     * @param bool $includableOnly
     * @return ItemInterface[]
     */
    public function find(
        int $type = ItemInterface::TYPE_ANY,
        string $parentClassName = '',
        bool $includableOnly = true
    ): array
    {
        if (!empty($parentClassName)) {
            $classes = [];
            if (isset($this->classes[$parentClassName])) {
                foreach ($this->classes[$parentClassName]->getInheritors() as $inheritorName) {
                    if (isset($this->classes[$inheritorName])) {
                        $classes[] = $this->classes[$inheritorName];
                    }
                }
            }
        } else {
            $classes = $this->classes;
        }
        $result = [];
        foreach ($classes as $mapItem) {
            if (ItemInterface::TYPE_ANY === $type || ($type & $mapItem->getType())) {
                if (!$includableOnly || $mapItem->isSafeInclude()) {
                    $result[] = $mapItem;
                }
            }
        }
        return $result;
    }

    /**
     * @param string $filePath
     * @param ItemInterface $itemOld
     * @return bool  TRUE, if file was changed
     */
    private function checkDiff(string $filePath, ItemInterface $itemOld): bool
    {
        return $this->itemClass::fileHash($filePath) !== $itemOld->getFileHash();
    }

    /**
     * @param string[] $scanPaths
     * @param string[] $excludePaths
     * @return string[]
     */
    private function glob(array $scanPaths, array $excludePaths): array
    {
        $result = [];
        foreach ($scanPaths as $path) {
            foreach ($excludePaths as $excludePath) {
                $excludePath .= DIRECTORY_SEPARATOR . '*';
                if (fnmatch($excludePath, $path)) {
                    continue(2);
                }
            }
            if (is_file($path)) {
                $result[] = $path;
            } elseif (is_dir($path)) {
                $globFiles = false;
                $globDirs = false;
                $composerJsonFile = $path . DIRECTORY_SEPARATOR . 'composer.json';
                $dirs = $this->parseComposerFile($composerJsonFile, $excludePaths);
                if (count($dirs)) {
                    if (false !== ($k = array_search($path, $dirs))) {
                        // If composer.json contains "autoload": {"...": ""}, we must
                        // find subdirs and files in current dir, but should not again
                        // scan this dir with Map::glob() to prevent infinite recursion
                        unset($dirs[$k]);
                        $globDirs = true;
                        $globFiles = true;
                    }
                } else {
                    $globDirs = true;
                    $globFiles = true;
                }

                if ($globDirs) {
                    // Glob subdirectories in current dir
                    $dirs = array_unique(
                        array_merge(
                            $dirs,
                            glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) ?: []
                        )
                    );
                }
                if (count($dirs)) {
                    $result = array_merge(
                        $result,
                        $this->glob($dirs, $excludePaths)
                    );
                }

                if ($globFiles) {
                    // Glob files in current dir
                    $files = glob($path . DIRECTORY_SEPARATOR . '*.php', GLOB_NOSORT) ?: [];
                    $result = array_merge(
                        $result,
                        $this->glob($files, $excludePaths)
                    );
                }
            }
        }
        return $result;
    }

    /**
     * @param string $composerJsonFile
     * @param array &$excludePaths Paths from "exclude-from-classmap" directive will be added to this array
     * @return string[] Composer package source dir
     */
    private function parseComposerFile(string $composerJsonFile, array &$excludePaths): array
    {
        if (!is_file($composerJsonFile)) {
            return [];
        }
        $composerData = file_get_contents($composerJsonFile);
        if ($composerData === false) {
            throw new \RuntimeException(sprintf(
                'File "%s" is unreadable',
                $composerJsonFile
            ));
        }
        $composerData = json_decode($composerData, true);
        if (null === $composerData || !isset($composerData['autoload'])) {
            return [];
        }
        $composerAutoloadConfig = $composerData['autoload'];
        if (!is_array($composerAutoloadConfig) || !count($composerAutoloadConfig)) {
            return [];
        }

        $dirs = [];
        $currentDir = dirname($composerJsonFile) . DIRECTORY_SEPARATOR;
        foreach ($composerAutoloadConfig as $type => $config) {
            $type = trim(strtolower($type));
            if ('exclude-from-classmap' !== $type) {
                foreach ($config as $paths) {
                    if (!is_array($paths) && !is_string($paths)) {
                        continue;
                    }
                    if (!is_array($paths)) {
                        $paths = [$paths];
                    }
                    foreach ($paths as $path) {
                        $path = trim(ltrim($path, DIRECTORY_SEPARATOR));
                        $dirs[] = realpath($currentDir . $path);
                    }
                }
            } else {
                foreach ($config as $path) {
                    if (strpos($path, '*') !== false) {
                        $pattern = $this->composerExcludePattern($currentDir, $path);
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($currentDir),
                            \RecursiveIteratorIterator::SELF_FIRST
                        );
                        $iterator->rewind();
                        while ($iterator->valid()) {
                            if ($iterator->isDot()) {
                                $iterator->next();
                                continue;
                            }
                            $p = $iterator->getPathname();
                            if (preg_match($pattern, $p)) {
                                $excludePaths[] = $p;
                            }
                            $iterator->next();
                        }
                    } else {
                        $path = trim(ltrim($path, DIRECTORY_SEPARATOR));
                        $excludePaths[] = realpath($currentDir . $path);
                    }
                }
            }
        }
        return array_unique($dirs);
    }

    /**
     * @param string[] $paths
     * @return string[]
     * @throws \InvalidArgumentException
     * @throws Exception\PathNotFoundException
     */
    private function normalizePathsArray(array $paths): array
    {
        $paths = array_map(
            function ($path) {
                if (!is_string($path) || trim($path) === '') {
                    throw new \InvalidArgumentException (
                        sprintf(
                            'Path must be an non empty string, %s given',
                            __METHOD__, __CLASS__, gettype($path)
                        )
                    );
                }
                $real = realpath($path);
                if (!$real) {
                    throw new Exception\PathNotFoundException(
                        sprintf('File or directory "%s" is not found', $path)
                    );
                }
                return rtrim($real, DIRECTORY_SEPARATOR);
            },
            $paths
        );
        return array_values($paths);
    }

    /**
     * Parse exclude path with * and ** and returns pattern for preg_match()
     * Copied from
     * @link https://github.com/composer/composer/blob/1.4/src/Composer/Autoload/AutoloadGenerator.php#L843
     * @param string $currentDir
     * @param string $path
     * @return string
     */
    private function composerExcludePattern(string $currentDir, string $path): string
    {
        // first escape user input
        $path = preg_replace('{/+}', '/', preg_quote(trim(strtr($path, '\\', '/'), '/')));

        // add support for wildcards * and **
        $path = str_replace('\\*\\*', '.+?', $path);
        $path = str_replace('\\*', '[^/]+?', $path);

        // add support for up-level relative paths
        $updir = null;
        $path = preg_replace_callback(
            '{^((?:(?:\\\\\\.){1,2}+/)+)}',
            function ($matches) use (&$updir) {
                if (isset($matches[1])) {
                    // undo preg_quote for the matched string
                    $updir = str_replace('\\.', '.', $matches[1]);
                }
                return '';
            },
            $path
        );
        $resolvedPath = realpath($currentDir . '/' . $updir);
        $pattern = preg_quote(strtr($resolvedPath, '\\', '/')) . '/' . $path;
        $pattern = str_replace('/', preg_quote(DIRECTORY_SEPARATOR), $pattern);
        $pattern = '{' . $pattern . '}';
        return $pattern;
    }
}