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
 * Class ItemTypeAbstractTest
 */
class ItemTypeAbstractTest extends TestsHelper
{
    /**
     * @var Item
     */
    private $item;

    /**
     * @var string
     */
    private static $fixture = '/../fixtures/Map/Item/SampleAbstract.php';

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
            ItemInterface::TYPE_ABSTRACT,
            $this->item->getType()
        );
    }

    /**
     *
     */
    public function testNamespace(): void
    {
        $this->assertEquals(
            '',
            $this->item->getNamespace()
        );
    }

    /**
     *
     */
    public function testName(): void
    {
        $this->assertEquals(
            'SampleAbstract',
            $this->item->getName()
        );
    }

    /**
     *
     */
    public function testParentName(): void
    {
        $this->assertEquals(
            '',
            $this->item->getParent()
        );
    }
}