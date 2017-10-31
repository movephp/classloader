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
    Map\Item, Exception
};

/**
 * Class ItemBaseTest
 */
class ItemBaseTest extends TestsHelper
{
    /**
     * @var string
     */
    private static $fixturePath = '/../fixtures/Map/Item/';

    /**
     * @var array
     */
    private static $fixtureFiles = [
        'empty'          => 'Empty.php',
        'non-class'      => 'NonClass.php',
        'non-executable' => 'NonExecutable.php',
        'class'          => 'SampleClass.php',
        'abstract'       => 'SampleAbstract.php',
        'interface'      => 'SampleInterface.php',
        'trait'          => 'SampleTrait.php'
    ];

    /**
     *
     */
    public static function setUpBeforeClass(): void
    {
        self::$fixtureFiles = array_map(
            function ($f){
                return __DIR__ . self::$fixturePath . $f;
            },
            self::$fixtureFiles
        );
    }

    /**
     * @return Item
     */
    public function testNonexistentFile(): Item
    {
        $oItem = new Item('asd.sadfsdfsfd/*\\');
        $this->assertFalse($oItem->isValid());
        return $oItem;
    }

    /**
     *
     */
    public function testEmptyFile(): void
    {
        $oItem = new Item(self::$fixtureFiles['empty']);
        $this->assertFalse($oItem->isValid());
    }

    /**
     *
     */
    public function testNonClassFile(): void
    {
        $oItem = new Item(self::$fixtureFiles['non-class']);
        $this->assertFalse($oItem->isValid());
    }

    /**
     *
     */
    public function testNonExecutableFile(): void
    {
        $oItem = new Item(self::$fixtureFiles['non-executable']);
        $this->assertTrue($oItem->isValid());
    }

    /**
     * @return Item
     */
    public function testClassParse(): Item
    {
        $oItem = new Item(self::$fixtureFiles['class']);
        $this->assertTrue($oItem->isValid());
        return $oItem;
    }

    /**
     *
     */
    public function testAbstractParse(): void
    {
        $oItem = new Item(self::$fixtureFiles['abstract']);
        $this->assertTrue($oItem->isValid());
    }

    /**
     *
     */
    public function testInterfaceParse(): void
    {
        $oItem = new Item(self::$fixtureFiles['interface']);
        $this->assertTrue($oItem->isValid());
    }

    /**
     *
     */
    public function testTraitParse(): void
    {
        $oItem = new Item(self::$fixtureFiles['trait']);
        $this->assertTrue($oItem->isValid());
    }

    /**
     * @depends testClassParse
     * @param Item $oItem
     * @return Item
     */
    public function testIsParsedJustNow(Item $oItem): Item
    {
        $this->assertTrue($oItem->isParsedJustNow());
        return $oItem;
    }

    /**
     * @param Item $oItem
     * @depends testIsParsedJustNow
     */
    public function testIsParsedJustNowAfterUnserialize(Item $oItem): void
    {
        $oItem = unserialize(serialize($oItem));
        $this->assertFalse($oItem->isParsedJustNow());
    }

    /**
     * @depends testClassParse
     * @param Item $oItem
     */
    public function testFilePath(Item $oItem): void
    {
        $this->assertEquals(
            realpath(self::$fixtureFiles['class']),
            $oItem->getFilePath()
        );
    }

    /**
     * @depends testClassParse
     * @param Item $oItem
     */
    public function testFilemtime(Item $oItem): void
    {
        $this->assertEquals(
            filemtime(self::$fixtureFiles['class']),
            $oItem->getFilemtime()
        );
    }

    /**
     * @depends testClassParse
     * @param Item $oItem
     */
    public function testFileHash(Item $oItem): void
    {
        $this->assertRegExp(
            '/^[0-9a-f]{32}$/',
            $oItem->getFileHash()
        );
    }

    /**
     * @return array
     */
    public function gettersDataProvider(): array
    {
        return [
            ['isParsedJustNow'],
            ['getFilePath'],
            ['getFilemtime'],
            ['getFileHash'],
            ['getType'],
            ['getNamespace'],
            ['getName'],
            ['getImports'],
            ['getParent'],
            ['getInterfaces'],
            ['getTraits'],
            ['isPHPUnitTest'],
            ['isPartOfComposer'],
            ['getParents'],
            ['getInheritors'],
            ['isSafeInclude'],
            ['onMapUpdate']
        ];
    }

    /**
     * @depends      testNonexistentFile
     * @dataProvider gettersDataProvider
     * @param string $method
     * @param Item $oInvalidItem
     */
    public function testGettersIfInvalid(string $method, Item $oInvalidItem): void
    {
        $this->expectException(Exception\ParsingFailedException::class);
        call_user_func([$oInvalidItem, $method]);
    }
}