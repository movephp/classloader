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
 * Interface ItemInterface
 * @package Movephp\ClassLoader\Map
 */
interface ItemInterface
{
    public const
        TYPE_CLASS = 1,
        TYPE_ABSTRACT = 2,
        TYPE_INTERFACE = 4,
        TYPE_TRAIT = 8,
        TYPE_ANY = 15; // For search only

    /**
     * @param string $filePath
     * @return string
     */
    public static function fileHash(string $filePath): string;

    /**
     * Parse given file.
     * On error it must not throws exception, but isValid() with that object must returns FALSE
     * @param string $filePath
     * @param MapInterface|null $map
     */
    public function __construct(string $filePath, MapInterface $map = null);

    /**
     * Is parsing was successful.
     * Calling of any other method of this class if isValid()===FALSE must throws an ParsingFailedException.
     * @param string|null &$error
     * @return bool
     */
    public function isValid(string &$error = null): bool;

    /**
     * Was this class parsed just now, during the current launch of the site
     * @return bool
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function isParsedJustNow(): bool;

    /**
     * @return string
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getFilePath(): string;

    /**
     * @return int
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getFilemtime(): int;

    /**
     * Must calculate hash the same way as method fileHash()
     * @see ItemInterface::fileHash()
     * @return string
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getFileHash(): string;

    /**
     * Must returns one of the constants, listed on top of this interface, except TYPE_ANY
     * @return int
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getType(): int;

    /**
     * @return string
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getNamespace(): string;

    /**
     * @return string
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getName(): string;

    /**
     * @return string[]
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getImports(): array;

    /**
     * @return string
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getParent(): string;

    /**
     * Interfaces, implementing by this class (but not implementing by its parent)
     * @return string[]
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getInterfaces(): array;

    /**
     * Traits, using by this class (but not using by its parent)
     * @return string[]
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getTraits(): array;

    /**
     * @return bool
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function isPHPUnitTest(): bool;

    /**
     * @return bool
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function isPartOfComposer(): bool;

    /**
     * Must return an array of fully-qualified names of all parents of class:
     * its own parent, parents of its parent, its traits & interfaces,
     * parents of its traits & interfaces etc.
     *
     * Collected results may be saved to some private field of this class,
     * but it must clearing in onMapUpdate().
     * @see ItemInterface::onMapUpdate()
     *
     * Must throws an exception when called in object, which was created
     * without the 2nd argument of the __constructor() - instance of MapInterface,
     * because its not possible to collect all parents without MapInterface::classes().
     * @see ItemInterface::__constructor()
     * @see MapInterface::classes()
     *
     * @return string[]
     * @throws \LogicException
     *
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getParents(): array;

    /**
     * Must return an array of fully-qualified names of all inheritors of the current class
     * (entities using current class as parent class, as interface or trait),
     * and inheritors of inheritors of the current class etc.
     *
     * Collected results may be saved to some private field of this class,
     * but it must clearing in onMapUpdate().
     * @see ItemInterface::onMapUpdate()
     *
     * Must throws an exception when called in object, which was created
     * without the 2nd argument of the __constructor() - instance of MapInterface,
     * because its not possible to collect all inheritors without MapInterface::classes().
     * @see ItemInterface::__constructor()
     * @see MapInterface::classes()
     *
     * @return string[]
     * @throws \LogicException
     *
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function getInheritors(): array;

    /**
     * Means that file with current entity may be included without the risk
     * to take any errors (if some of dependencies is not exists (e.g. parent class))
     * or stopping execution script (if there is "exit()" before or after class body).
     * This should not checking any possible errors, than may occurs during
     * usage of included file - its only about possibility to include it.
     *
     * Calculated result may be saved to some private field of this class,
     * but it must clearing in onMapUpdate().
     * @see ItemInterface::onMapUpdate()
     *
     * Must throws an exception when called in object, which was created
     * without the 2nd argument of the __constructor() - instance of MapInterface,
     * because its not possible to check all dependencies without MapInterface::classes().
     * @see ItemInterface::__constructor()
     * @see MapInterface::classes()
     *
     * @return bool
     * @throws \LogicException
     *
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function isSafeInclude(): bool;

    /**
     * If the results of getParents(), getInheritors() or isSafeInclude() are saving to
     * any private fields to improve performance, it must clearing by this method.
     * This method should be called during update loaded from cache map, because
     * there are new parents and inheritors may appears.
     *
     * If results of getParents(), getInheritors() and isSafeInclude() are not saving
     * anywhere and produces every time anew, this method may do nothing.
     *
     * @see ItemInterface::getParents()
     * @see ItemInterface::getInheritors()
     * @see ItemInterface::isSafeInclude()
     *
     * @throws Exception\ParsingFailedException  if isValid() === false
     * @see ItemInterface::isValid()
     */
    public function onMapUpdate(): void;
}