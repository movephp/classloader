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

use Movephp\ClassLoader\Map\{
    Map, Item
};

/**
 * Class ItemPostProcessTest
 */
class ItemPostProcessTest extends TestsHelper
{
    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * @var Item
     */
    private $itemWithoutMap;

    /**
     * @var bool
     */
    private $mapMockReturnEmptyArray = false;

    /**
     * @var string
     */
    private static $fixture = '/../fixtures/Map/Item/Tree/';

    /**
     *
     */
    public function setUp(): void
    {
        $mapMock = $this->getMockBuilder(Map::class)
            ->setMethods(['classes'])
            ->getMock();

        foreach ((new DirectoryIterator(__DIR__ . self::$fixture)) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if (!$this->itemWithoutMap) {
                $this->itemWithoutMap = new Item($file->getPathname());
            }
            $item = new Item($file->getPathname(), $mapMock);
            $this->items[$item->getName()] = $item;
        }

        $mapMock->expects($this->any())
            ->method('classes')
            ->will(
                $this->returnCallback(
                    function (){
                        return !$this->mapMockReturnEmptyArray ? $this->items : [];
                    }
                )
            );
    }

    /**
     *
     */
    public function tearDown(): void
    {
        $this->items = [];
    }

    /**
     * @return array
     */
    public function isPHPUnitDataProvider(): array
    {
        return [
            ['Name1_1\Name1_2\SomeClass1', false],
            ['UnitTestClass1', true],
            ['UnitTestClass2', true]
        ];
    }

    /**
     * @param string $className
     * @param bool $expected
     * @dataProvider isPHPUnitDataProvider
     */
    public function testIsPHPUnitTest(string $className, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->items[$className]->isPHPUnitTest()
        );
    }

    /**
     * @return array
     */
    public function isPartOfComposerDataProvider(): array
    {
        return [
            ['Name1_1\Name1_2\SomeClass1', false],
            ['ComposerAutoloaderInit262ed32601f26095a89c05228c04bb65', true],
            ['Composer\Autoload\ClassLoader', true]
        ];
    }

    /**
     * @param string $className
     * @param bool $expected
     * @dataProvider isPartOfComposerDataProvider
     */
    public function testIsPartOfComposer(string $className, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->items[$className]->isPartOfComposer()
        );
    }

    /**
     *
     */
    public function testParents(): void
    {
        $this->assertEqualsArrayValues(
            [
                'Name1_1\Name1_2\SomeClass1',
                'Name2_1\Name2_2\SomeAbstract',
                'SomeNonExistentClass',
                'Name2_1\Name2_2\SomeInterface',
                'Name2_1\Name2_2\SomeNonExistentInterface2',
                'Name2_1\Name2_2\SomeTrait',
                'Name2_1\Name2_2\SomeNonExistentTrait2',
                'Name1_1\Name1_2\Name1_3\SomeNonExistentInterface1',
                'SomeNonExistentNamespace\SomeNonExistentTrait'
            ],
            $this->items['Name1_1\Name1_2\SomeClass2']->getParents()
        );
    }

    /**
     *
     */
    public function testInheritors(): void
    {
        $this->assertEqualsArrayValues(
            ['Name1_1\Name1_2\SomeClass2'],
            $this->items['Name1_1\Name1_2\SomeClass1']->getInheritors(),
            'Error while testing Item::getInheritors()'
        );
    }

    /**
     *
     */
    public function testClassIsSafeInclude(): void
    {
        $this->assertTrue(
            $this->items['Composer\Autoload\ClassLoader']->isSafeInclude()
        );
    }

    /**
     *
     */
    public function testClassIsSafeIncludeIfHasNonExistingDependencies(): void
    {
        $this->assertFalse(
            $this->items['Name1_1\Name1_2\SomeClass2']->isSafeInclude()
        );
    }

    /**
     *
     */
    public function testInterfaceIsSafeIncludeIfHasNonExistingDependencies(): void
    {
        $this->assertFalse(
            $this->items['Name2_1\Name2_2\SomeInterface']->isSafeInclude()
        );
    }

    /**
     *
     */
    public function testTraitIsSafeIncludeIfHasNonExistingDependencies(): void
    {
        $this->assertFalse(
            $this->items['Name2_1\Name2_2\SomeTrait']->isSafeInclude()
        );
    }

    /**
     *
     */
    public function testIsSafeIncludeIfHasExitToken(): void
    {
        $this->assertFalse(
            $this->items['SomeClass4']->isSafeInclude()
        );
    }

    /**
     *
     */
    public function testOnMapUpdate(): void
    {
        $item = $this->items['Name1_1\Name1_2\SomeClass1'];
        $beforeClearing = $item->getParents();

        $this->mapMockReturnEmptyArray = true;
        $item->onMapUpdate();

        $this->assertNotEquals(
            $beforeClearing,
            $item->getParents()
        );
    }

    /**
     *
     */
    public function testGetParentsWithoutMap(): void
    {
        $this->expectException(\LogicException::class);
        $this->itemWithoutMap->getParents();
    }

    /**
     *
     */
    public function testGetInheritorsWithoutMap(): void
    {
        $this->expectException(\LogicException::class);
        $this->itemWithoutMap->getInheritors();
    }

    /**
     *
     */
    public function testIsSafeIncludeWithoutMap(): void
    {
        $this->expectException(\LogicException::class);
        $this->itemWithoutMap->isSafeInclude();
    }
}