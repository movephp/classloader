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

include_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Class TestsHelper
 */
class TestsHelper extends PHPUnit\Framework\TestCase
{
    /**
     * @param array $expected
     * @param array $actual
     * @param string $message
     */
    protected function assertEqualsArrayValues(
        array $expected,
        array $actual,
        $message = 'Failed asserting that two arrays has equal values.'
    ){
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param string $dir
     */
    protected static function clearDir($dir)
    {
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isFile()) {
                if ('.gitkeep' === $item->getFilename()) {
                    continue;
                }
                unlink($item->getPathname());
            }elseif ($item->isDir() && !$item->isDot()) {
                self::clearDir($item->getPathname());
                rmdir($item->getPathname());
            }
        }
    }

    /**
     * @param string $src
     * @param string $dest
     */
    protected static function copyDir($src, $dest)
    {
        $src = realpath($src);
        $dest = realpath($dest);
        foreach (new DirectoryIterator($src) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $srcPath = $item->getPathname();
            $destPath = str_replace($src, $dest, $srcPath);
            if ($item->isFile()) {
                copy($srcPath, $destPath);
            }elseif ($item->isDir() && !$item->isDot()) {
                mkdir($destPath);
                self::copyDir($srcPath, $destPath);
            }
        }
    }
}