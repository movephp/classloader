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

namespace Name1\Name2;

use ArrayObject;
use Name3\Name4 as Alias1;
use Name5\Name6 as Alias2, Name7;
use Name8\{Name9\Name10 as Alias3, Name11};

use function Name12\functionName;
use function Name13\functionName as func;
use const Name14\CONSTANT;

class SampleClass extends Alias1\SomeClass implements \SomeInterafce1, SomeInterafce2
{
    use SomeTrait1, Alias1\SomeTrait2, \SomeTrait3 {
        SomeTrait1::A insteadof B;
        SomeTrait1::C as private D;
    }

    public function f()
    {
        if(1) '{';
    }
}

eval('(); make this code non executable {{');