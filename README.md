[![Build Status](https://travis-ci.org/movephp/callback-container.svg?branch=master)](https://travis-ci.org/movephp/callback-container)

# Расширенный Autoloader для фреймворка Movephp

Это механизм для создания карты классов приложения.
Он сканирует указанные каталоги в поисках **\*.php** файлов
и составляет список всех найденных классов (в т.ч. абстрактных,
а также интерфейсов и трейтов).
Для каждого класса также определяется пространство имён,
родительский класс, используемые интерфейсы и трейты,
классы-потомки.

Полученный массив данных можно использовать как для обычной
автозагрузки, так и для поиска и анализа карты классов.

Например, можно найти все классы, имеющие в дереве родительских
классов указанный, или можно найти все классы, использующие
определённый трейт и т.д.

Это позволяет быстро анализировать код приложения и выполнять
препроцессинг.

## Оглавление

* [Установка](#Установка)
* [Быстрый старт](#Быстрый-старт)
* [Автозагрузчик](#Автозагрузчик)
* [Обновление карты](#Обновление-карты)
* [Настройки сканирования](#Настройки-сканирования)
* [Особые случаи](#Особые-случаи)
    * [Composer-пакеты](#composer-пакеты)
    * [PHPUnit и библиотека Composer](#phpunit-и-библиотека-composer)
    * [Классы с возможными ошибками](#Классы-с-возможными-ошибками)
* [Поиск и анализ классов](#Поиск-и-анализ-классов)
* [API Reference](#api-reference)
    * [`Movephp\ClassLoader\Autoload`](#movephpclassloaderautoload)
        * [Конструктор](#Конструктор)
        * [Методы](#Методы)
    * [`Movephp\ClassLoader\Map\Map`](#movephpclassloadermapmap)
        * [Конструктор](#Конструктор-1)
        * [Методы](#Методы-1)
    * [`Movephp\ClassLoader\Map\Item`](#movephpclassloadermapitem)
        * [Методы](#Методы-2)
* [TODO](#todo)

## Установка

Рекомендуемый способ установки - с использованием **Composer**.
Добавьте следующую инструкцию в ваш `composer.json` файл:

    "require": {
        "movephp/classloader": "~1.0"
    }

## Быстрый старт

Пример кода для быстрого старта:

    include_once('vendor/autoload.php');
    use Movephp\ClassLoader\{Autoload, Map};
    $autoload = new Autoload(
        new Map\Map()
    );
    $autoload->setScanPaths(__DIR__ . '/src', __DIR__ . '/vendor');
    $autoload->makeMap();
    var_dump($autoload->map()->classes());

## Автозагрузчик

Зарегистрировать функцию автозагрузки классов из составленной карты
можно методом `$autoload->register()`.

Для использования данной библиотеки в качестве автозагрузчика классов
рекомендуется использовать кеширование (PSR-6).

    $cachePool = new Symfony\Component\Cache\Adapter\FilesystemAdapter();
    $autoload = new Autoload(
        new Map\Map(),
        $cachePool
    );
    $autoload->setScanPaths(__DIR__ . '/src', __DIR__ . '/vendor');
    $autoload->makeMap();
    $autoload->register();
    
При использовании кеширования карта классов будет сформирована 
только один раз, а при следующих обращениях данные будут быстро 
восстановлены из кеша.

> По-умолчанию библиотека использует ключ `movephp_classloader` для 
получения `CacheItem` из переданного `CachePool`. Для предотвращения 
возможных коллизий третьим аргументом в конструктор класса 
`Autoload` можно передать пространство имён для ключа CacheItem: 
`$autoload = new Autoload($map, $cachePool, 'mynamespace')` - в 
этом случае для получения `CacheItem` будет использован ключ
`mynamespace_movephp_classloader`.

## Обновление карты

В dev-окружении или при работе над проектом может потребоваться
обновить карту классов:
 
    $cachePool = new Symfony\Component\Cache\Adapter\FilesystemAdapter();
    $autoload = new Autoload(
        new Map\Map(),
        $cachePool
    );
    $autoload->setScanPaths(__DIR__ . '/src', __DIR__ . '/vendor');
    $autoload->makeMap();
    if (...some_сondition_here...) {
        $autoload->updateMap();
    }
    $autoload->register();
    
Другой вариант, более медленный, - полностью сбросить кеш и 
сформировать карту заново:    
    
    $cachePool = new Symfony\Component\Cache\Adapter\FilesystemAdapter();
    if (...some_сondition_here...) {
        $cachePool->clear();
    }
    $autoload = new Autoload(
        new Map\Map(),
        $cachePool
    );
    $autoload->setScanPaths(__DIR__ . '/src', __DIR__ . '/vendor');
    $autoload->makeMap();
    $autoload->register();

## Настройки сканирования

Помимо метода `$autoload->setScanPaths()` для указания того,
какие файлы/каталоги должны быть просканированы, существует два 
дополнительных метода:

+ `$autoload->setExcludingPaths(string ...$excludePaths)` - исключает 
указанные файлы и каталоги (и всё их содержимое) из процесса 
сканирования. Имеет смысл использовать эту возможность для каталогов 
с тестами.
+ `$autoload->setOverridePaths(string ...$overridePaths)` - в случае,
если при сканировании проекта будет обнаружено несколько классов с
одинаковым именем (включая пространство имён), возникнет ошибка. 
Однако данный метод позволяет указать файлы/каталоги, в которых могут
содержаться классы, заменяющие одноимённые классы в других сканируемых 
файлах. В этом случае не будет ошибки, а в карту попадут классы из 
`$overridePaths`.

## Особые случаи

### Composer-пакеты

При сканировании файлов проекта особое внимание уделяются файлам
`composer.json`. При обнаружении такого файла, если он содержит
директиву `autoload`, сканироваться будут только каталоги и файлы, 
описанные в ней.

Остальные файлы в найденном composer-пакете будут пропущены,
т.к., вероятно, нужны там лишь для тестов или просто являются
мусором.

### PHPUnit и библиотека Composer

Класс не будет включён в карту классов и не будет доступен как
для автозагрузки, так и для анализа, в следующих случаях:

* Если класс является тестом **phpUnit**, т.е. если он или один
из его родителей унаследован от классов
`PHPUnit_Framework_TestCase` или `PHPUnit\Framework\TestCase`;
* Если класс является частью библиотеки **Composer**, т.е.
определён в пространстве имён `Composer` или в любом
подпространстве `Composer\...`.

### Классы с возможными ошибками

Класс будет включен в карту классов, но не будет доступен для
автозагрузки в следующих случаях:

* Если в коде файла с классом встречается оператор `exit()` (или `die()`)
вне тела класса, т.е. он может быть вызван в момент подключения
файла к приложению.
* Если класс унаследован от класса, который не представлен в
общей карте классов и не является встроенным в PHP классом.
Тоже касается используемых трейтов и интерфейсов.

> Такой класс получит особую отметку 
`$item->isSafeInclude() === false`,
которая означает, что подключение файла с данным классом
может привести к ошибке или неожиданному завершению скрипта.
Однако класс будет присутствовать в карте классов и будет
доступен для анализа.

## Поиск и анализ классов

Сформированную карту классов - объект `Map\Map` - можно получить 
методом `$autoload->map()`. Также, если вам не нужны функции 
автозагрузки и кеширования, вы можете сразу напрямую использовать 
класс `Map\Map` и его метод `scan()`:

    $map = new Map\Map();
    $map->scan([__DIR__ . '/src', __DIR__ . '/vendor']);

Метод `Map\Map::classes()` возвращает полный список всех классов в
карте в виде массива объектов `Map\Item`.

С помощью метода `Map\Map::find()` можно осуществлять поиск среди
классов в карте по заданным параметрам. Пример поиска неабстрактных 
классов, реализующих интерфейс `MyNamespace\MyInterface`, а также 
потомков этих классов:

    $map->find(Map\Item::TYPE_CLASS, MyNamespace\MyInterface::class);

Метод `find()` также возвращает массив объектов `Map\Item`.

Объекты `Map\Item` - элементы карты - имеют много методов-геттеров 
для получения всевозможной информации о представляемом ими классе.

## API Reference

### `Movephp\ClassLoader\Autoload`

Основной класс библиотеки. Используется для выполнения функции
автозагрузчика на основе карты классов, а также для управления 
объектом карты и его кешированием.

#### Конструктор

    __construct(Movephp\ClassLoader\Map\MapInterface $cleanMap, Psr\Cache\CacheItemPoolInterface $cachePool = null, string $cacheKeyNamespace = '')

Аргумент | Тип | По-умолчанию | Описание
---|---|---|---
`$cleanMap` | `Movephp\ClassLoader\Map\MapInterface` | | DI для создания объекта карты.
`$cachePool` | `Psr\Cache\CacheItemPoolInterface` | `null` | Объект кеша для кеширования карты. Кеширование не выполняется, если этот аргумент не задан.
`$cacheKeyNamespace` | `string` | Пустая строка | Пространство имён для ключа элемента кеша.

#### Методы

Метод | Описание
---|--- 
`setScanPaths(string ...$scanPaths): void` | Устанавливает пути к сканируемым каталогам и файлам. **Вызов этого метода обязателен до выполнения сканирования.**
`setExcludingPaths(string ...$excludePaths): void` | Указывает каталоги и файлы, которые должны быть пропущены при сканировании. **Метод следует вызывать до выполнения сканирования.**
`setOverridePaths(string ...$overridePaths): void` | В случае обнаружения нескольких классов с идентичными именами (включая пространство имён), классы в каталогах/файлах, указанных через данный метод попадут в карту. Если же данный метод не используется, при обнаружении дубликатов классов, в момент создания карты классов будет выброшено исключение `Movephp\ClassLoader\Exception\ClassDuplicateException`. **Метод следует вызывать до выполнения сканирования.**  
`makeMap(): void` | Пытается загрузить объект карты классов из кеша и, в случае неудачи, выполняет сканирование файлов, создаёт новую карту и сохраняет её в кеш.
`updateMap(): void` | Данный метод обновляет карту, загруженную из кеша при вызове `makeMap()`. Такое обновление выполняется значительно быстрее, чем составление карты с нуля. В целом карта может быть создана/обновлена только один раз за один запуск скрипта, поэтому многократные вызовы данного метода ничего не изменят.  Если кеширование не используется, данный метод не окажет никакого эффекта. 
`map(): Map\MapInterface` | Возвращает объект карты классов. **Если метод `makeMap()` не был вызван предварительно, он выполнится автоматически.**
`isClassExists(string $className, Map\ItemInterface &$item = null): bool` | По имени класса (fully qualified) определяет, имеется ли он в карте классов. Если передан второй аргумент, в него по ссылке записывается объект, представляющий соответствующий элемент карты (если класс найден в карте). **Если метод `makeMap()` не был вызван предварительно, он выполнится автоматически.**
`load(string $className): void` | Загружает файл с классом с помощью `include_once()`, если указанный класс существует в карте и если он безопасен для подключения (см. описание `Movephp\ClassLoader\Map\Item::isSafeInclude()`).
`isClassLoaded(string $className): bool` | Определяет, был ли указанный класс ранее загружен с помощью метод `load()`.
`register(): void` | Регистрирует метод `load()` в качестве автозагрузчика классов с помощью функции `spl_autoload_register(..., true, true)`.

### `Movephp\ClassLoader\Map\Map`

Объект данного класса представляет карту классов и используется для 
её составления (сканирования файлов проекта) и поиска по ней.

#### Конструктор

    __construct(string $itemClass = '')
    
Аргумент | Тип | По-умолчанию | Описание
---|---|---|---
`$itemClass` | `string` | Пустая строка | DI для указания имени класса элемента карты. Если не задан, будет использоваться класс `Movephp\ClassLoader\Map\Item`. Используется, в основном, для тестирования.

#### Методы

Метод | Описание
---|--- 
`scan(array $scanPaths, array $excludePaths = [], array $overridePaths = []): int` | Выполняет сканирование файлов проекта для формирования карты классов. Все три аргумента - массивы строк, по смыслу идентичные аргументам методов `setScanPaths()`, `setExcludingPaths()` и `setOverridePaths()` класса `Movephp\ClassLoader\Autoload`. Повторный вызов этого метода выполняет обновление карты, которое происходит быстрее, чем полное сканирование.
`classes(): array` | Возвращает полный список всех включённых в карту классов, найденных при сканировании, в виде массива объектов класса `Movephp\ClassLoader\Map\Item`.
`find(int $type = Movephp\ClassLoader\Map\ItemInterface::TYPE_ANY, string $parentClassName = '', bool $includableOnly = true): array` | Выполняет поиск среди элементов карты классов по заданным параметрам. `$type` - тип элементов, которые необходимо найти, возможные значения см. ниже. `$parentClassName` - если указано, будут найдены только потомки класса с этим именем. `$includableOnly` - если `false`, будут найдены также классы, небезопасные для подключения (см. `Movephp\ClassLoader\Map\Item::isSafeInclude()`).

**Возможные значения аргумента `$type` метода `find()`:** 
- `Movephp\ClassLoader\Map\ItemInterface::TYPE_CLASS`
- `Movephp\ClassLoader\Map\ItemInterface::TYPE_ABSTRACT`
- `Movephp\ClassLoader\Map\ItemInterface::TYPE_INTERFACE`
- `Movephp\ClassLoader\Map\ItemInterface::TYPE_TRAIT`
- или любая сумма этих констант (`Movephp\ClassLoader\Map\ItemInterface::TYPE_ANY` - это сумма их всех).

### `Movephp\ClassLoader\Map\Item`

Объект данного класса представляет один элемент карты: класс, 
интерфейс или трейт, найденный при сканировании.

#### Методы 

Метод | Описание
---|---
`getFilePath(): string` | Путь к файлу, где объяслен класс.
`getType(): int` | Одна из следующих констант: `Movephp\ClassLoader\Map\ItemInterface::TYPE_CLASS`, `Movephp\ClassLoader\Map\ItemInterface::TYPE_ABSTRACT`, `Movephp\ClassLoader\Map\ItemInterface::TYPE_INTERFACE`, `Movephp\ClassLoader\Map\ItemInterface::TYPE_TRAIT`
`getNamespace(): string` | Пространство имён класса.
`getName(): string` | Имя класса (fully qualified).
`getImports(): array` | Возвращает массив строк - импортируемые классом пространства имён.
`getParent(): string` | Имя родительского класса.
`getInterfaces(): array` | Возвращает массив строк - имена реализуемых классом интерфейсов.
`getTraits(): array` | Возвращает массив строк - имена используемых классом трейтов.
`getParents(): array` | Возвращает массив строк - полная цепочка родительских классов.
`getInheritors(): array` | Возвращает массив строк - полный список всех потомков (включая потомков их потомков и т.д.)
`isSafeInclude(): bool` | Может ли файл с классом быть безопасно подключен в приложение.
`isParsedJustNow(): bool` | Был ли данный класс обновлён только что, в ходе текущего запуска приолжения (`false`, если класс был получен из кеша).

---------------------------------------


## TODO

- Сделать возможным использование спец. символов `*` и `?` при указании списка сканируемых и исключаемых из сканирования директорий и файлов;
- Настройка расширений сканируемых файлов (по-умолчанию `*.php`);
- Избавиться от прямых обращений к функциям файловой системы (использовать `Flysystem` или аналог).
