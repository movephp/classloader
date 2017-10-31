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

use Movephp\ClassLoader\{
    Map, Exception, Map\ItemInterface
};

/**
 * Class MapTest
 */
class MapTest extends TestsHelper
{
    /**
     * @var string
     */
    private static $fixturePath = '/../fixtures/Map/Map/';

    /**
     * @var array
     */
    private static $fixtureGroups = [
        'tree'       => 'Tree/',
        'exclude'    => 'Tree/Exclude',
        'duplicates' => 'Duplicates',
        'composer'   => [
            'basic'            => 'Composer/Basic',
            'only-dir'         => 'Composer/OnlyDir',
            'with-exclude'     => 'Composer/WithExclude',
            'invalid-json'     => 'Composer/InvalidJson',
            'without-autoload' => 'Composer/WithoutAutoload'
        ],
        'rescan'     => [
            'src'  => '../temp',
            'set1' => 'Rescan/set1',
            'set2' => 'Rescan/set2'
        ],
        'find'       => 'Find'
    ];

    /**
     * @var string
     */
    private static $itemStubClass = '';

    /**
     * @var Map\Map
     */
    private $oMap;

    /**
     *
     */
    public static function setUpBeforeClass(): void
    {
        self::$fixtureGroups = array_map(
            function ($group){
                if (is_array($group)) {
                    return array_map(
                        function ($subgroup){
                            return __DIR__ . self::$fixturePath . $subgroup;
                        },
                        $group
                    );
                }else {
                    return __DIR__ . self::$fixturePath . $group;
                }
            },
            self::$fixtureGroups
        );

        // Stub for Map\Item: no parsing, all results based on file name
        self::$itemStubClass = get_class(
            new class ('') extends Map\Item
            {
                private $filePath = '';

                public static function fileHash(string $filePath): string
                {
                    return md5($filePath);
                }

                public function __construct(string $filePath, Map\MapInterface $map = null)
                {
                    $this->filePath = $filePath;
                }

                public function isValid(string &$error = null): bool
                {
                    return true;
                }

                public function getFilePath(): string
                {
                    return $this->filePath;
                }

                public function getFileHash(): string
                {
                    return self::fileHash($this->filePath);
                }

                public function getType(): int
                {
                    if (stripos($this->filePath, 'abstract') !== false) {
                        return ItemInterface::TYPE_ABSTRACT;
                    }
                    if (stripos($this->filePath, 'interface') !== false) {
                        return ItemInterface::TYPE_INTERFACE;
                    }
                    if (stripos($this->filePath, 'trait') !== false) {
                        return ItemInterface::TYPE_TRAIT;
                    }
                    return ItemInterface::TYPE_CLASS;
                }

                public function getName(): string
                {
                    return pathinfo($this->filePath, PATHINFO_FILENAME);
                }

                public function isPHPUnitTest(): bool
                {
                    return false;
                }

                public function isPartOfComposer(): bool
                {
                    return false;
                }

                public function getParents(): array
                {
                    return [];
                }

                public function getInheritors(): array
                {
                    if (stripos($this->getName(), '_inherit_by_') === false) {
                        return [];
                    }
                    list(, $parent) = explode('_inherit_by_', $this->getName());
                    return [$parent];
                }

                public function isSafeInclude(): bool
                {
                    return stripos($this->filePath, 'notincludable') === false;
                }

                public function onMapUpdate(): void
                {
                }
            }
        );
    }

    /**
     *
     */
    public static function tearDownAfterClass(): void
    {
        self::clearDir(self::$fixtureGroups['rescan']['src']);
    }

    /**
     *
     */
    public function setUp(): void
    {
        $this->oMap = new Map\Map(self::$itemStubClass);
    }

    /**
     *
     */
    public function tearDown(): void
    {
        $this->oMap = null;
    }

    /**
     *
     */
    public function testSetItemClassNonExisting(): void
    {
        $this->expectException(Exception\ItemClassInvalid::class);
        $oMap = new Map\Map('asdasdasd');
    }

    /**
     *
     */
    public function testSetItemClassNotImplementsInterface(): void
    {
        $this->expectException(Exception\ItemClassInvalid::class);
        $oMap = new Map\Map(self::class);
    }

    /**
     *
     */
    public function testSetItemClassNotInstantiable(): void
    {
        include_once(__DIR__ . self::$fixturePath . 'NotInstantiableItemClass.php');
        $this->expectException(Exception\ItemClassInvalid::class);
        $oMap = new Map\Map(NotInstantiableItemClass::class);
    }

    /**
     * Simple test, just scan directory
     */
    public function testScan(): void
    {
        $this->assertEquals(
            3,
            $this->oMap->scan([self::$fixtureGroups['tree']])
        );
    }

    /**
     *
     */
    public function testScanNonExistingPath(): void
    {
        $this->expectException(Exception\PathNotFoundException::class);
        $this->oMap->scan(['ljsdfdbglsdbfgu']);
    }

    /**
     *
     */
    public function testScanInvalidPathname(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->oMap->scan(['']);
    }

    /**
     * Scan two directories with duplicate classes
     */
    public function testScanDuplicates(): void
    {
        $this->expectException(Exception\ClassDuplicateException::class);
        $this->oMap->scan(
            [
                self::$fixtureGroups['tree'],
                self::$fixtureGroups['duplicates']
            ]
        );
    }

    /**
     * Scan with excluding subdirectory
     */
    public function testScanExclude(): void
    {
        $this->assertEquals(
            2,
            $this->oMap->scan(
                [self::$fixtureGroups['tree']],
                [self::$fixtureGroups['exclude']]
            )
        );
    }

    /**
     * Scan with overriding
     */
    public function testScanOverride(): void
    {
        $this->assertEquals(
            3,
            $this->oMap->scan(
                [self::$fixtureGroups['tree'], self::$fixtureGroups['duplicates']],
                [],
                [self::$fixtureGroups['duplicates']]
            )
        );
    }

    /**
     * Scan whole package
     */
    public function testScanComposer(): void
    {
        $this->assertEquals(
            4,
            $this->oMap->scan(
                [self::$fixtureGroups['composer']['basic']]
            )
        );
    }

    /**
     * Scan specific paths in the package
     */
    public function testScanComposerOnlyDir(): void
    {
        $this->assertEquals(
            2,
            $this->oMap->scan(
                [self::$fixtureGroups['composer']['only-dir']]
            )
        );
    }

    /**
     * Scan package with "exclude-from-classmap" in composer.json
     */
    public function testScanComposerWithExclude(): void
    {
        $this->assertEquals(
            1,
            $this->oMap->scan(
                [self::$fixtureGroups['composer']['with-exclude']]
            )
        );
    }

    /**
     * Scan package with invalid composer.json
     */
    public function testScanComposerWithInvalidJson(): void
    {
        $this->assertEquals(
            2,
            $this->oMap->scan(
                [self::$fixtureGroups['composer']['invalid-json']]
            )
        );
    }

    /**
     * Scan package with composer.json without "autoload" directive
     */
    public function testScanComposerWithoutAutoload(): void
    {
        $this->assertEquals(
            2,
            $this->oMap->scan(
                [self::$fixtureGroups['composer']['without-autoload']]
            )
        );
    }

    /**
     * Rescan with a little diff
     */
    public function testReScan(): void
    {
        self::clearDir(self::$fixtureGroups['rescan']['src']);
        self::copyDir(
            self::$fixtureGroups['rescan']['set1'],
            self::$fixtureGroups['rescan']['src']
        );
        $this->oMap->scan([self::$fixtureGroups['rescan']['src']]);
        self::clearDir(self::$fixtureGroups['rescan']['src']);

        self::copyDir(
            self::$fixtureGroups['rescan']['set2'],
            self::$fixtureGroups['rescan']['src']
        );
        $this->assertEquals(
            3,
            $this->oMap->scan([self::$fixtureGroups['rescan']['src']])
        );
        self::clearDir(self::$fixtureGroups['rescan']['src']);
    }

    /**
     *
     */
    public function testClasses(): void
    {
        $this->oMap->scan([self::$fixtureGroups['tree']]);
        $this->assertContainsOnlyInstancesOf(
            Map\Item::class,
            $this->oMap->classes()
        );
    }

    /**
     *
     */
    public function testFind(): void
    {
        $this->oMap->scan([self::$fixtureGroups['find']]);
        $findResult = $this->oMap->find();
        $this->assertEqualsArrayValues(
            ['SampleClass1_inherit_by_SampleClass2', 'SampleClass2', 'SampleInterface', 'SampleAbstract'],
            array_map(
                function (Map\Item $oItem){
                    return $oItem->getName();
                },
                $findResult
            )
        );
    }

    /**
     *
     */
    public function testFindByType(): void
    {
        $this->oMap->scan([self::$fixtureGroups['find']]);
        $findResult = $this->oMap->find(ItemInterface::TYPE_CLASS | ItemInterface::TYPE_INTERFACE);
        $this->assertEqualsArrayValues(
            ['SampleClass1_inherit_by_SampleClass2', 'SampleClass2', 'SampleInterface'],
            array_map(
                function (Map\Item $oItem){
                    return $oItem->getName();
                },
                $findResult
            )
        );
    }

    /**
     *
     */
    public function testFindByParent(): void
    {
        $this->oMap->scan([self::$fixtureGroups['find']]);
        $findResult = $this->oMap->find(ItemInterface::TYPE_ANY, 'SampleClass1_inherit_by_SampleClass2');
        $this->assertEqualsArrayValues(
            ['SampleClass2'],
            array_map(
                function (Map\Item $oItem){
                    return $oItem->getName();
                },
                $findResult
            )
        );
    }

    /**
     *
     */
    public function testFindNotIncludable(): void
    {
        $this->oMap->scan([self::$fixtureGroups['find']]);
        $findResult = $this->oMap->find(ItemInterface::TYPE_CLASS, '', false);
        $this->assertEqualsArrayValues(
            ['SampleClass1_inherit_by_SampleClass2', 'SampleClass2', 'SampleClassNotIncludable'],
            array_map(
                function (Map\Item $oItem){
                    return $oItem->getName();
                },
                $findResult
            )
        );
    }
}