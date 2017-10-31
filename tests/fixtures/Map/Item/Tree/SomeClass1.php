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

namespace Name1_1\Name1_2;

use Name2_1\Name2_2;

class SomeClass1 extends Name2_2\SomeAbstract implements Name2_2\SomeInterface, Name1_3\SomeNonExistentInterface1
{
    use Name2_2\SomeTrait, \SomeNonExistentNamespace\SomeNonExistentTrait;
}