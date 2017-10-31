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
    Item, ItemInterface
};

/**
 * Class ItemTypeClassTest
 */
class ItemTypeClassTest extends TestsHelper
{
    /**
     * @var Item
     */
    private $item;

    /**
     * @var string
     */
    private static $fixture = '/../fixtures/Map/Item/SampleClass.php';

    /**
     *
     */
    public function setUp(): void
    {
        $this->item = new Item(__DIR__ . self::$fixture);
    }

    /**
     *
     */
    public function tearDown(): void
    {
        $this->item = null;
    }

    /**
     *
     */
    public function testType(): void
    {
        $this->assertEquals(
            ItemInterface::TYPE_CLASS,
            $this->item->getType()
        );
    }

    /**
     *
     */
    public function testNamespace(): void
    {
        $this->assertEquals(
            'Name1\Name2',
            $this->item->getNamespace()
        );
    }

    /**
     *
     */
    public function testName(): void
    {
        $this->assertEquals(
            'Name1\Name2\SampleClass',
            $this->item->getName()
        );
    }

    /**
     *
     */
    public function testImports(): void
    {
        $this->assertEqualsArrayValues(
            [
                'ArrayObject',
                'Name3\Name4 as Alias1',
                'Name5\Name6 as Alias2',
                'Name7',
                'Name8\Name9\Name10 as Alias3',
                'Name8\Name11'
            ],
            $this->item->getImports()
        );
    }

    /**
     *
     */
    public function testParentName(): void
    {
        $this->assertEquals(
            'Name3\Name4\SomeClass',
            $this->item->getParent()
        );
    }

    /**
     *
     */
    public function testInterfaces(): void
    {
        $this->assertEqualsArrayValues(
            ['SomeInterafce1', 'Name1\Name2\SomeInterafce2'],
            $this->item->getInterfaces()
        );
    }

    /**
     */
    public function testTraits(): void
    {
        $this->assertEqualsArrayValues(
            ['Name1\Name2\SomeTrait1', 'Name3\Name4\SomeTrait2', 'SomeTrait3'],
            $this->item->getTraits()
        );
    }
}