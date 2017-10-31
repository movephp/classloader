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
    Autoload, Map, Exception
};
use Psr\Cache;

/**
 * Class AutoloadTest
 */
class AutoloadTest extends TestsHelper
{
    /**
     * @var string
     */
    private static $fixture = '/fixtures/Autoload/SampleClass.php';

    /**
     *
     */
    public static function setUpBeforeClass(): void
    {
        self::$fixture = __DIR__ . self::$fixture;
    }

    /**
     * @return Map\MapInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMapStub(): Map\MapInterface
    {
        return $this->getMockForAbstractClass(Map\MapInterface::class);
    }

    /**
     * @return Cache\CacheItemPoolInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private function getCacheStub(): Cache\CacheItemPoolInterface
    {
        $cacheItemStub = $this->getMockForAbstractClass(Cache\CacheItemInterface::class);
        $cachePoolStub = $this->getMockForAbstractClass(Cache\CacheItemPoolInterface::class);
        $cachePoolStub->method('getItem')->willReturn($cacheItemStub);
        return $cachePoolStub;
    }

    /**
     *
     */
    public function testConstructorCache(): void
    {
        $cachePoolMock = $this->getCacheStub();
        $cachePoolMock->expects($this->atLeastOnce())
            ->method('getItem')
            ->with($this->isType('string'));

        $autoload = new Autoload($this->getMapStub(), $cachePoolMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testConstructorCacheWithNamespace(): void
    {
        $cacheNamespace = 'cache-test-namespace';

        $cachePoolMock = $this->getCacheStub();
        $cachePoolMock->expects($this->atLeastOnce())
            ->method('getItem')
            ->with($this->stringContains($cacheNamespace));

        $autoload = new Autoload($this->getMapStub(), $cachePoolMock, $cacheNamespace);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testConstructorMap(): void
    {
        $mapMock = $this->getMapStub();
        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');

        $resultMap = $autoload->map();
        $this->assertInstanceOf(get_class($mapMock), $resultMap);
        $this->assertNotSame($mapMock, $resultMap);
    }

    /**
     *
     */
    public function testScanPaths(): void
    {
        $paths = ['scan-path1', 'scan-path2'];

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan')
            ->with($this->equalTo($paths));

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths(...$paths);
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testScanPathsEmpty(): void
    {
        $this->expectException(Exception\ScanPathsRequired::class);
        $autoload = new Autoload($this->getMapStub());
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testExcludingPaths(): void
    {
        $paths = ['excl-path1', 'excl-path2'];

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan')
            ->with(
                $this->anything(),
                $this->equalTo($paths)
            );

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->setExcludingPaths(...$paths);
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testOverridePaths(): void
    {
        $paths = ['over-path1', 'over-path2'];

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo($paths)
            );

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->setOverridePaths(...$paths);
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testMakeMapWithoutCache(): void
    {
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan');
        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testMakeMap(): void
    {
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan');

        $cacheItemMock = $this->getMockForAbstractClass(Cache\CacheItemInterface::class);
        $cacheItemMock->expects($this->once())
            ->method('set')
            ->with($this->isInstanceOf(get_class($mapMock)));
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(false);

        $cachePoolMock = $this->getMockForAbstractClass(Cache\CacheItemPoolInterface::class);
        $cachePoolMock->method('getItem')->willReturn($cacheItemMock);
        $cachePoolMock->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($cacheItemMock));

        $autoload = new Autoload($mapMock, $cachePoolMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testMakeMapFromCache(): void
    {
        $mapMock = $this->getMapStub();
        // Main assertion, Map::scan() must not be called
        $mapMock->expects($this->never())
            ->method('scan');

        $cacheItemMock = $this->getMockForAbstractClass(Cache\CacheItemInterface::class);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($mapMock);

        $cachePoolMock = $this->getMockForAbstractClass(Cache\CacheItemPoolInterface::class);
        $cachePoolMock->method('getItem')->willReturn($cacheItemMock);

        $autoload = new Autoload($mapMock, $cachePoolMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testMakeMapFromCacheInvalid(): void
    {
        $mapMock = $this->getMapStub();
        // Main assertion, Map::scan() must be called once
        $mapMock->expects($this->once())
            ->method('scan');

        $cacheItemMock = $this->getMockForAbstractClass(Cache\CacheItemInterface::class);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn('some invalid data, not the Map object');

        $cachePoolMock = $this->getMockForAbstractClass(Cache\CacheItemPoolInterface::class);
        $cachePoolMock->method('getItem')->willReturn($cacheItemMock);

        $autoload = new Autoload($mapMock, $cachePoolMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
    }

    /**
     *
     */
    public function testUpdateMapWithoutCache(): void
    {
        // Main assertion, Map::scan() must be called only once from makeMap()
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan');

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
        $autoload->updateMap();
    }

    /**
     *
     */
    public function testUpdateMap(): void
    {
        // Main assertion, Map::scan() must be called only once in updateMap() after loading from cache
        $mapMock1 = $this->getMapStub();
        $mapMock1->expects($this->once())
            ->method('scan');

        // 2nd map object - empty map sample, it must not be used
        $mapMock2 = $this->getMapStub();
        $mapMock2->expects($this->never())
            ->method('scan');

        $cacheItemMock = $this->getMockForAbstractClass(Cache\CacheItemInterface::class);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($mapMock1);

        $cachePoolMock = $this->getMockForAbstractClass(Cache\CacheItemPoolInterface::class);
        $cachePoolMock->method('getItem')->willReturn($cacheItemMock);

        $autoload = new Autoload($mapMock2, $cachePoolMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
        $autoload->updateMap();
    }

    /**
     *
     */
    public function testMapGetter(): void
    {
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->once())
            ->method('scan');

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $map1 = $autoload->map();
        $map2 = $autoload->map();
        $map3 = $autoload->map();
        $this->assertSame($map1, $map2);
        $this->assertSame($map1, $map3);
    }

    /**
     * @return array
     */
    public function isClassExistsDataProvider(): array
    {
        return [
            ['ExistentClass', true],
            ['NonexistentClass', false],
            ['NotClassItemElement', false]
        ];
    }

    /**
     * @dataProvider isClassExistsDataProvider
     * @param string $className
     * @param bool $expectedResult
     */
    public function testIsClassExists(string $className, bool $expectedResult): void
    {
        $itemStub = $this->getMockForAbstractClass(Map\ItemInterface::class);
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->any())
            ->method('classes')
            ->willReturn(
                [
                    'ExistentClass'       => $itemStub,
                    'NotClassItemElement' => 'bla-bla'
                ]
            );
        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->makeMap();
        $this->assertEquals(
            $expectedResult,
            $autoload->isClassExists($className, $item)
        );
        if ($expectedResult) {
            $this->assertSame($itemStub, $item);
        }
    }

    /**
     *
     */
    public function testRegister(): void
    {
        $autoload = $this->getMockBuilder(Autoload::class)
            ->disableOriginalConstructor()
            ->setMethods(['load'])
            ->getMock();
        $autoload->expects($this->once())
            ->method('load')
            ->with($this->equalTo('TestClassName'));
        $autoload->register();
        class_exists('TestClassName', true);
    }

    /**
     *
     */
    public function testLoad(): void
    {
        $this->expectExceptionMessage('TEST_EXCEPTION');

        $itemMock = $this->getMockForAbstractClass(Map\ItemInterface::class);
        $itemMock->expects($this->atLeastOnce())
            ->method('isSafeInclude')
            ->willReturn(true);
        $itemMock->expects($this->atLeastOnce())
            ->method('getFilePath')
            ->willReturn(self::$fixture);

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->atLeastOnce())
            ->method('classes')
            ->willReturn(['SomeClass' => $itemMock]);

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->load('SomeClass');
    }

    /**
     *
     */
    public function testLoadNotSafeInclude(): void
    {
        $itemMock = $this->getMockForAbstractClass(Map\ItemInterface::class);
        $itemMock->expects($this->atLeastOnce())
            ->method('isSafeInclude')
            ->willReturn(false);    // <---------
        $itemMock->expects($this->never())
            ->method('getFilePath');

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->atLeastOnce())
            ->method('classes')
            ->willReturn(['SomeClass' => $itemMock]);

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->load('SomeClass');
    }

    /**
     *
     */
    public function testLoadNonexistent(): void
    {
        $mapMock = $this->getMapStub();
        $mapMock->expects($this->atLeastOnce())
            ->method('classes')
            ->willReturn([]);
        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');
        $autoload->load('SomeClass');
    }

    /**
     *
     */
    public function testIsClassLoaded(): void
    {
        $itemMock = $this->getMockForAbstractClass(Map\ItemInterface::class);
        $itemMock->expects($this->atLeastOnce())
            ->method('isSafeInclude')
            ->willReturn(true);
        $itemMock->expects($this->atLeastOnce())
            ->method('getFilePath')
            ->willReturn(self::$fixture);

        $mapMock = $this->getMapStub();
        $mapMock->expects($this->atLeastOnce())
            ->method('classes')
            ->willReturn(['SomeClass' => $itemMock]);

        $autoload = new Autoload($mapMock);
        $autoload->setScanPaths('');

        $this->assertFalse($autoload->isClassLoaded('SomeClass'));
        try{
            $autoload->load('SomeClass');
        }catch(\Exception $e){
        }
        $this->assertTrue($autoload->isClassLoaded('SomeClass'));
    }
}