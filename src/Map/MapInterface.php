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
 * Interface MapInterface
 * @package Movephp\ClassLoader\Map
 */
interface MapInterface
{
    /**
     * @param string $itemClass
     * @throws Exception\ItemClassInvalid
     */
    public function __construct(string $itemClass = '');

    /**
     * @param string[] $scanPaths
     * @param string[] $excludePaths
     * @param string[] $overridePaths
     * @return int
     * @throws Exception\ClassDuplicateException
     */
    public function scan(array $scanPaths, array $excludePaths = [], array $overridePaths = []): int;

    /**
     * @return ItemInterface[] Keys are classes names
     * @see ItemInterface::getName()
     */
    public function classes(): array;

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
    ): array;
}