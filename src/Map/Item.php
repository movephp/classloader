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
 * Class Item
 * @package Movephp\ClassLoader\Map
 */
class Item implements ItemInterface
{
    /**
     * @var string
     */
    private $error = '';

    /**
     * @var MapInterface|null
     */
    private $map = null;

    /**
     * @var bool
     */
    private $loadedFromCache = false;

    /**
     * @var string
     */
    private $filePath = '';

    /**
     * @var int
     */
    private $filemtime;

    /**
     * @var string
     */
    private $fileHash = '';

    /**
     * @var int
     */
    private $type = ItemInterface::TYPE_CLASS;

    /**
     * @var string
     */
    private $namespace = '';

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string[]
     */
    private $imports = [];

    /**
     * @var string
     */
    private $parentName = '';

    /**
     * @var string[]|null
     */
    private $parents = null;

    /**
     * @var string[]
     */
    private $interfaces = [];

    /**
     * @var string[]
     */
    private $traits = [];

    /**
     * @var string[]|null
     */
    private $inheritors = null;

    /**
     * @var bool
     */
    private $containsExitToken = false;

    /**
     * @var bool|null
     */
    private $safeInclude = null;

    /**
     * @var boolean|null
     */
    private $isPHPUnitTest = null;

    /**
     * @param string $filePath
     * @return string
     */
    public static function fileHash(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new Exception\FileNotFountException(sprintf(
                'File %s is not exists',
                $filePath
            ));
        }
        $hash = md5_file($filePath);
        if (!$hash) {
            throw new \RuntimeException(sprintf(
                'Can\'t make md5 hash of file %s',
                $filePath
            ));
        }
        return $hash;
    }

    /**
     * @param string $filePath
     * @param MapInterface|null $map
     */
    public function __construct(string $filePath, MapInterface $map = null)
    {
        if (!is_file($filePath)) {
            $this->error = sprintf('File "%s" is not found', $filePath);
            return;
        }
        $code = file_get_contents($filePath);
        if (!$code) {
            $this->error = sprintf('File "%s" is empty or unreadable', $filePath);
            return;
        }
        $tokens = $this->splitToTokens($code);
        $this->type = $this->parseType($tokens, $typeTokenNum);
        if (!$this->type) {
            $this->error = sprintf('File "%s" does not contain any class, interface or trait', $filePath);
            return;
        }

        if ($map) {
            $this->map = $map;
        }
        $namespace = $this->parseNamespace($tokens);
        $this->namespace = $this->parseNamespace($tokens);
        $this->name = trim($namespace . '\\' . $this->parseName($tokens, $this->type), '\\');
        $this->filePath = realpath($filePath);
        $this->filemtime = filemtime($filePath);
        $this->fileHash = self::fileHash($filePath);
        $this->imports = $this->parseImports($tokens);
        $this->parentName = $this->parseParent($tokens, $this->type);
        $this->interfaces = $this->parseInterfaces($tokens, $this->type);
        $this->traits = $this->parseTraits($tokens, $this->type);
        if ($this->searchExitToken($tokens, $typeTokenNum)) {
            $this->containsExitToken = true;
        }
    }

    /**
     *
     */
    public function __wakeup(): void
    {
        $this->loadedFromCache = true;
    }

    /**
     * @param string|null &$error
     * @return bool
     */
    public function isValid(string &$error = null): bool
    {
        $error = $this->error;
        return $this->error === '';
    }

    /**
     * @return bool
     * @throws Exception\ParsingFailedException
     */
    public function isParsedJustNow(): bool
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return !$this->loadedFromCache;
    }

    /**
     * @return string
     * @throws Exception\ParsingFailedException
     */
    public function getFilePath(): string
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->filePath;
    }

    /**
     * @return int
     * @throws Exception\ParsingFailedException
     */
    public function getFilemtime(): int
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->filemtime;
    }

    /**
     * @return string
     * @throws Exception\ParsingFailedException
     */
    public function getFileHash(): string
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->fileHash;
    }

    /**
     * @return int
     * @throws Exception\ParsingFailedException
     */
    public function getType(): int
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->type;
    }

    /**
     * @return string
     * @throws Exception\ParsingFailedException
     */
    public function getNamespace(): string
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->namespace;
    }

    /**
     * @return string
     * @throws Exception\ParsingFailedException
     */
    public function getName(): string
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->name;
    }

    /**
     * @return string[]
     * @throws Exception\ParsingFailedException
     */
    public function getImports(): array
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->imports;
    }

    /**
     * @return string
     * @throws Exception\ParsingFailedException
     */
    public function getParent(): string
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->parentName;
    }

    /**
     * @return string[]
     * @throws Exception\ParsingFailedException
     */
    public function getInterfaces(): array
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->interfaces;
    }

    /**
     * @return string[]
     * @throws Exception\ParsingFailedException
     */
    public function getTraits(): array
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return $this->traits;
    }

    /**
     * @return bool
     * @throws Exception\ParsingFailedException
     */
    public function isPHPUnitTest(): bool
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        if (!is_null($this->isPHPUnitTest)) {
            return $this->isPHPUnitTest;
        }
        foreach ($this->getParents() as $parent) {
            if (strpos($parent, 'PHPUnit\\') === 0 || strpos($parent, 'PHPUnit_') === 0) {
                $this->isPHPUnitTest = true;
                return true;
            }
        }
        $this->isPHPUnitTest = false;
        return false;
    }

    /**
     * @return bool
     * @throws Exception\ParsingFailedException
     */
    public function isPartOfComposer(): bool
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        return
            $this->namespace === 'Composer' ||
            strpos($this->namespace, 'Composer\\') === 0 ||
            strpos($this->name, 'ComposerAutoloader') === 0;
    }

    /**
     * @return string[]
     * @throws \LogicException
     * @throws Exception\ParsingFailedException
     */
    public function getParents(): array
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        if (!$this->map) {
            throw new \LogicException(
                sprintf(
                    'Can\'t collect parents: object of %s must be passed in 2nd argument to %s::__construct()',
                    MapInterface::class,
                    self::class
                )
            );
        }
        if (is_array($this->parents)) {
            return $this->parents;
        }
        $allItems = $this->map->classes();
        $this->parents = [];
        $currentName = $this->getName();
        if (($parentName = $this->getParent()) !== '' && $parentName !== $currentName) {
            // Get parents
            $this->parents[] = $parentName;
            if (isset($allItems[$parentName])) {
                $parentItem = $allItems[$parentName];
                if ($parentItem instanceof ItemInterface) {
                    $this->parents = array_merge(
                        $this->parents,
                        $parentItem->getParents()
                    );
                }
            }
        }
        foreach ($this->getInterfaces() as $interface) {
            // Get interfaces and its parents
            if ($interface === $currentName) {
                continue;
            }
            $this->parents[] = $interface;
            if (isset($allItems[$interface])) {
                $interfaceItem = $allItems[$interface];
                if ($interfaceItem instanceof ItemInterface) {
                    $this->parents = array_merge(
                        $this->parents,
                        $interfaceItem->getParents()
                    );
                }
            }
        }
        foreach ($this->getTraits() as $trait) {
            // Get traits and its parents
            if ($trait === $currentName) {
                continue;
            }
            $this->parents[] = $trait;
            if (isset($allItems[$trait])) {
                $traitItem = $allItems[$trait];
                if ($traitItem instanceof ItemInterface) {
                    $this->parents = array_merge(
                        $this->parents,
                        $traitItem->getParents()
                    );
                }
            }
        }
        $this->parents = array_unique($this->parents);
        return $this->parents;
    }

    /**
     * @return string[]
     * @throws \LogicException
     * @throws Exception\ParsingFailedException
     */
    public function getInheritors(): array
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        if (!$this->map) {
            throw new \LogicException(
                sprintf(
                    'Can\'t collect inheritors: object of %s must be passed in 2nd argument to %s::__construct()',
                    MapInterface::class,
                    self::class
                )
            );
        }
        if (is_array($this->inheritors)) {
            return $this->inheritors;
        }
        $allItems = $this->map->classes();
        $this->inheritors = [];
        $currentName = $this->getName();
        foreach ($allItems as $item) {
            if ($item->getName() === $currentName) {
                continue;
            }
            if (
                $item->getParent() === $currentName ||
                in_array($currentName, $item->getInterfaces()) ||
                in_array($currentName, $item->getTraits())
            ) {
                $this->inheritors[] = $item->getName();
                $this->inheritors = array_merge(
                    $this->inheritors,
                    $item->getInheritors()
                );
            }
        }
        $this->inheritors = array_unique($this->inheritors);
        return $this->inheritors;
    }

    /**
     * @return bool
     * @throws \LogicException
     * @throws Exception\ParsingFailedException
     */
    public function isSafeInclude(): bool
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        if ($this->containsExitToken) {
            return false;
        }
        if (!$this->map) {
            throw new \LogicException(
                sprintf(
                    'Can\'t check safety of including: object of %s must be passed in 2nd argument to %s::__construct()',
                    MapInterface::class,
                    self::class
                )
            );
        }
        if (is_bool($this->safeInclude)) {
            return $this->safeInclude;
        }
        $this->safeInclude = true;
        $allItems = $this->map->classes();
        $currentName = $this->getName();
        if (($parentName = $this->getParent()) !== '' && $parentName !== $currentName) {
            if (!isset($allItems[$parentName]) && !class_exists($parentName, false)) {
                $this->safeInclude = false;
                return false;
            }
            if (isset($allItems[$parentName])) {
                $parentItem = $allItems[$parentName];
                if (!$parentItem instanceof ItemInterface || !$parentItem->isSafeInclude()) {
                    $this->safeInclude = false;
                    return false;
                }
            }
        }
        foreach ($this->getInterfaces() as $interface) {
            if ($interface === $currentName) {
                continue;
            }
            if (!isset($allItems[$interface]) && !interface_exists($interface, false)) {
                $this->safeInclude = false;
                return false;
            }
            if (isset($allItems[$interface])) {
                $interfaceItem = $allItems[$interface];
                if (!$interfaceItem instanceof ItemInterface || !$interfaceItem->isSafeInclude()) {
                    $this->safeInclude = false;
                    return false;
                }
            }
        }
        foreach ($this->getTraits() as $trait) {
            if ($trait === $currentName) {
                continue;
            }
            if (!isset($allItems[$trait]) && !interface_exists($trait, false)) {
                $this->safeInclude = false;
                return false;
            }
            if (isset($allItems[$trait])) {
                $traitItem = $allItems[$trait];
                if (!$traitItem instanceof ItemInterface || !$traitItem->isSafeInclude()) {
                    $this->safeInclude = false;
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @throws Exception\ParsingFailedException
     */
    public function onMapUpdate(): void
    {
        if (!$this->isValid($e)) {
            throw new Exception\ParsingFailedException($e);
        }
        $this->parents = null;
        $this->inheritors = null;
        $this->safeInclude = null;
    }

    /**
     * @param string $code
     * @return array
     */
    private function splitToTokens(string $code): array
    {
        static $savingTokensId = [
            T_ABSTRACT     => 1,
            T_AS           => 1,
            T_CLASS        => 1,
            T_CONST        => 1,
            T_EXIT         => 1,
            T_EXTENDS      => 1,
            T_FUNCTION     => 1,
            T_IMPLEMENTS   => 1,
            T_INTERFACE    => 1,
            T_NAMESPACE    => 1,
            T_NS_SEPARATOR => 1,
            T_STRING       => 1,
            T_TRAIT        => 1,
            T_USE          => 1,
            T_VARIABLE     => 1
        ];
        static $savingTokensStr = [
            '{' => 1,
            '}' => 1,
            ',' => 1,
            ';' => 1
        ];
        $tokens = token_get_all(trim($code));
        foreach ($tokens as $tn => $t) {
            if (is_string($t)) {
                if (!isset($savingTokensStr[$t])) {
                    unset($tokens[$tn]);
                } else {
                    $tokens[$tn] = [0, $t];
                }
            } else/*if (is_array($t))*/ {
                if (!isset($savingTokensId[$t[0]])) {
                    unset($tokens[$tn]);
                }
            }
        }
        return array_values($tokens);
    }

    /**
     * @param array $tokens
     * @param int &$typeTokenNum
     * @return int
     */
    private function parseType(array $tokens, int &$typeTokenNum = null): int
    {
        foreach ($tokens as $tn => $t) {
            if (T_ABSTRACT === $t[0]) {
                $typeTokenNum = $tn;
                return ItemInterface::TYPE_ABSTRACT;
            } elseif (T_CLASS === $t[0]) {
                $typeTokenNum = $tn;
                return ItemInterface::TYPE_CLASS;
            } elseif (T_INTERFACE === $t[0]) {
                $typeTokenNum = $tn;
                return ItemInterface::TYPE_INTERFACE;
            } elseif (T_TRAIT === $t[0]) {
                $typeTokenNum = $tn;
                return ItemInterface::TYPE_TRAIT;
            }
        }
        $typeTokenNum = 0;
        return 0;
    }

    /**
     * @param array $tokens
     * @return string
     */
    private function parseNamespace(array $tokens): string
    {
        foreach ($tokens as $tn => $t) {
            if (T_NAMESPACE === $t[0]) {
                return $this->getEntityName($tokens, $tn + 1);
            }
        }
        return '';
    }

    /**
     * @param array $tokens
     * @param int $type
     * @return string
     */
    private function parseName(array $tokens, int $type): string
    {
        if (ItemInterface::TYPE_INTERFACE === $type) {
            // Search for T_STRING token follows after T_INTERFACE
            foreach ($tokens as $tn => $t) {
                if (T_INTERFACE === $t[0]) {
                    return $this->getEntityName($tokens, $tn + 1);
                }
            }
        } elseif (ItemInterface::TYPE_TRAIT === $type) {
            // Search for T_STRING token follows after T_TRAIT
            foreach ($tokens as $tn => $t) {
                if (T_TRAIT === $t[0]) {
                    return $this->getEntityName($tokens, $tn + 1);
                }
            }
        } else {
            // Search for T_STRING token follows after T_CLASS
            foreach ($tokens as $tn => $t) {
                if (T_CLASS === $t[0]) {
                    return $this->getEntityName($tokens, $tn + 1);
                }
            }
        }
        return '';
    }

    /**
     * @param array $tokens
     * @return array
     * @todo: Parsing PHP7 syntax: use some\namespace\{ClassA, ClassB, ClassC as C}
     */
    private function parseImports(array $tokens): array
    {
        // Remove all tokes after class signature starts
        foreach ($tokens as $tn => $t) {
            if (in_array($t[0], [T_ABSTRACT, T_CLASS, T_INTERFACE, T_TRAIT])) {
                $tokens = array_slice($tokens, 0, $tn);
                break;
            }
        }

        // Search for T_STRING and T_NS_SEPARATOR tokens follows after T_USE
        $cnt = count($tokens);
        $imports = [];
        for ($tn = 0; $tn < $cnt - 1; $tn++) {
            $t = $tokens[$tn];
            if (T_USE === $t[0]) {
                $imports = array_merge(
                    $imports,
                    $this->getListOfNames($tokens, $tn + 1, true, $totalLength)
                );
                $tn += $totalLength + 1;
            }
        }
        return $imports;
    }

    /**
     * @param array $tokens
     * @param int $type
     * @return string
     */
    private function parseParent(array $tokens, int $type): string
    {
        // Interfaces and Trait has no parents
        // (Interfaces can extends some other Interfaces, but this case processing in parseInterfaces() method)
        if (ItemInterface::TYPE_INTERFACE === $type || ItemInterface::TYPE_TRAIT === $type) {
            return '';
        }
        // Search for T_STRING and T_NS_SEPARATOR tokens follows after T_EXTENDS
        foreach ($tokens as $tn => $t) {
            if (T_EXTENDS === $t[0]) {
                return $this->getFullyQualifiedName(
                    $this->getEntityName($tokens, $tn + 1)
                );
            }
        }
        return '';
    }

    /**
     * @param array $tokens
     * @param int $type
     * @return string[]
     */
    private function parseInterfaces(array $tokens, int $type): array
    {
        // Traits can't implements Interfaces
        if (ItemInterface::TYPE_TRAIT === $type) {
            return [];
        }

        $startN = -1;
        if (ItemInterface::TYPE_INTERFACE === $type) {
            // Interfaces extends each other with T_EXTENDS token
            foreach ($tokens as $tn => $t) {
                if (T_EXTENDS === $t[0]) {
                    $startN = $tn + 1;
                    break;
                }
            }
        } else {
            // Classes implements Interfaces with T_IMPLEMENTS token
            foreach ($tokens as $tn => $t) {
                if (T_IMPLEMENTS === $t[0]) {
                    $startN = $tn + 1;
                    break;
                }
            }
        }
        if (-1 === $startN || !isset($tokens[$startN])) {
            return [];
        }
        return array_map(
            function ($entityName) {
                return $this->getFullyQualifiedName($entityName);
            },
            $this->getListOfNames($tokens, $startN)
        );
    }

    /**
     * @param array $tokens
     * @param int $type
     * @return string[]
     */
    private function parseTraits(array $tokens, int $type): array
    {
        // Traits can't be used in Interfaces
        if (ItemInterface::TYPE_INTERFACE === $type) {
            return [];
        }

        // First of all, remove tokens before first "{" and tokens nested in {...} for more than 1 level
        // (to exclude T_USE tokens that is namespace imports)
        $openBracesCnt = 0;
        foreach ($tokens as $tn => $t) {
            if ($t[1] === '{') {
                $openBracesCnt++;
            } elseif ($t[1] === '}') {
                $openBracesCnt--;
            } elseif ($openBracesCnt === 0 || $openBracesCnt > 1) {
                unset($tokens[$tn]);
            }
        }
        $tokens = array_values($tokens);

        // Search for T_STRING and T_NS_SEPARATOR tokens follows after T_USE
        $cnt = count($tokens);
        $traits = [];
        for ($tn = 0; $tn < $cnt - 1; $tn++) {
            $t = $tokens[$tn];
            if (T_USE === $t[0]) {
                $traits = array_merge(
                    $traits,
                    $this->getListOfNames($tokens, $tn + 1, false, $totalLength)
                );
                $tn += $totalLength + 1;
            }
        }
        return array_map(
            function ($entityName) {
                return $this->getFullyQualifiedName($entityName);
            },
            $traits
        );
    }

    /**
     * @param array $tokens
     * @param int $bodyStartTokenNum
     * @return bool
     */
    private function searchExitToken(array $tokens, int $bodyStartTokenNum = 0): bool
    {
        $tn = 0;
        while (isset($tokens[$tn])) {
            if ($tn >= $bodyStartTokenNum) {
                $tn++;
                break;
            }
            if (T_EXIT === $tokens[$tn][0]) {
                return true;
            }
            $tn++;
        }

        $bracesOpenCnt = $bracesCloseCnt = 0;
        while (isset($tokens[$tn])) {
            if ('{' === $tokens[$tn][1]) {
                $bracesOpenCnt++;
                $tn++;
                break;
            }
            $tn++;
        }
        while (isset($tokens[$tn])) {
            if ('{' === $tokens[$tn][1]) {
                $bracesOpenCnt++;
            }
            if ('}' === $tokens[$tn][1]) {
                $bracesCloseCnt++;
            }
            if ($bracesOpenCnt === $bracesCloseCnt) {
                $tn++;
                break;
            }
            $tn++;
        }

        while (isset($tokens[$tn])) {
            if (T_EXIT === $tokens[$tn][0]) {
                return true;
            }
            $tn++;
        }

        return false;
    }

    /**
     * Getting entity name, including NS-separators "\" and aliases (like "Foo\Bar as Baz")
     * @param array $tokens
     * @param int $startPos
     * @param int &$length
     * @return string
     */
    private function getEntityName(array $tokens, int $startPos = 0, int &$length = null): string
    {
        $name = [];
        $tn = $startPos;
        while (isset($tokens[$tn]) && in_array($tokens[$tn][0], [T_STRING, T_NS_SEPARATOR, T_AS])) {
            if (T_AS === $tokens[$tn][0]) {
                $tokens[$tn][1] = ' ' . $tokens[$tn][1] . ' ';
            }
            $name[] = $tokens[$tn][1];
            $tn++;
        }
        $length = count($name);
        return implode('', $name);
    }

    /**
     * Getting list of entities names, separated by comma
     * @see Item::getEntityName()
     * @param array $tokens
     * @param int $startPos
     * @param bool $includingBraces Expand braces
     * @param int|null $totalLength
     * @return string[]
     */
    private function getListOfNames(array $tokens, int $startPos = 0, $includingBraces = false, int &$totalLength = null): array
    {
        $names = [];
        $tokens = array_slice($tokens, $startPos);
        $tn = 0;
        $totalLength = 0;
        if ($includingBraces) {
            $sub = false;
            $subNames = [];
            while (
                isset($tokens[$tn]) &&
                (
                    in_array($tokens[$tn][1], [',', '{', '}']) ||
                    in_array($tokens[$tn][0], [T_STRING, T_NS_SEPARATOR, T_AS])
                )
            ) {
                $token = $tokens[$tn];
                $name = $this->getEntityName($tokens, $tn, $length);
                $totalLength += $length;
                $tn += $length ?: 1;

                if ($token[1] === '{') {
                    $sub = true;
                }
                if ($token[1] === '}') {
                    $sub = false;
                    $name = array_pop($names);
                    $subNames = array_map(
                        function ($subName) use ($name) {
                            return $name . $subName;
                        },
                        $subNames
                    );
                    $names = array_merge($names, $subNames);
                    $subNames = [];
                    continue;
                }
                if (!$name) {
                    continue;
                }
                if ($sub) {
                    $subNames[] = $name;
                } else {
                    $names[] = $name;
                }
            }
        } else {
            while (isset($tokens[$tn]) && (',' === $tokens[$tn][1] || in_array($tokens[$tn][0], [T_STRING, T_NS_SEPARATOR, T_AS]))) {
                $name = $this->getEntityName($tokens, $tn, $length);
                $totalLength += $length;
                $tn += $length ?: 1;
                if (!$name) {
                    continue;
                }
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * @param string $entityName
     * @return string
     */
    private function getFullyQualifiedName(string $entityName): string
    {
        static $_as_ = ' as ';
        static $_sprtr = '\\';

        $entityName = trim($entityName);
        if ($entityName === '') {
            return '';
        }
        if (strpos($entityName, $_sprtr) === 0) {
            // Calling entity from global namespace
            return substr($entityName, strlen($_sprtr));
        }

        // Search entity in imported namespaces
        foreach ($this->getImports() as $importLine) {
            $aliasUsed = false !== ($_as_Pos = stripos($importLine, $_as_));
            if ($aliasUsed) {
                $importNs = substr($importLine, 0, $_as_Pos);
                $nsEndPart = substr($importLine, $_as_Pos + strlen($_as_));
            } else {
                $importNs = $importLine;
                $nsEndPart = array_reverse(explode($_sprtr, $importLine))[0];
            }
            if ($nsEndPart === $entityName) {
                // Entity name is equal to imported class name
                return $importNs;
            }
            if (strpos($entityName, $nsEndPart . $_sprtr) === 0) {
                // Entity name is relative to imported namespace
                return $importNs . substr($entityName, strlen($nsEndPart));
            }
        }

        // Search entity in the current namespace
        if ($this->getNamespace() !== '') {
            return $this->getNamespace() . $_sprtr . $entityName;
        }

        return $entityName;
    }
}