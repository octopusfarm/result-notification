<?php

/**
 * These are more integration than unit tests because in this case, I want to prove to myself that I understand how the
 * underlying library (Adbar\Dot) works.
 *
 * @license https://opensource.org/licenses/MIT
 */

namespace Tests;

use InvalidArgumentException;

use OctopusFarm\ResultNotification\DotAddressableCollection;
use PHPUnit\Framework\TestCase;

class DotAddressableCollectionTest extends TestCase {

    private array $arr = ['hello' => ['doctor' => 'name']];
    private string $key = 'hello.doctor';
    private string $value = 'name';
    private string $undefinedKey = 'some.undefined.key';


    public function test__construct() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertEquals($this->value, $test->get($this->key));
    }

    public function testSet() {
        $test = new DotAddressableCollection();
        $test->set($this->key, $this->value);
        $this->assertEquals($this->value, $test->get($this->key));
    }

    public function testGet__key_exists() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertEquals($this->value, $test->get($this->key));
    }

    public function testGet__key_doesnt_exist__null_allowed() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertNull($test->get($this->undefinedKey, true));
    }

    public function testGet__key_doesnt_exist__null_not_allowed() {
        $test = new DotAddressableCollection($this->arr);
        $this->expectException(InvalidArgumentException::class);
        $test->get($this->undefinedKey, false);
    }

    public function testCount() {
        $test = new DotAddressableCollection([
            'hello' => [
                'doctor' => [
                    'name',
                    'continue',
                    'yesterday',
                    'tomorrow'
                ]
            ]
        ]);
        $this->assertEquals(4, $test->count('hello.doctor'));
        $this->assertEquals(1, $test->count());
        $this->assertEquals(0, (new DotAddressableCollection())->count());
    }

    public function testUnset() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertTrue($test->has($this->key));
        $test->unset($this->key);
        $this->assertFalse($test->has($this->key));
    }

    public function testAdd() {
        $test = new DotAddressableCollection();
        $test->add('hello.doctor', 'name');
        $test->add('hello.doctor', 'continue');
        $test->add('yesterday');
        $test->add('tomorrow');
        $expected = [
            'hello' => [
                'doctor' => [
                    'name', 'continue'
                ]
            ],
            'yesterday',
            'tomorrow'
        ];
        $this->assertEquals($expected, $test->all());
    }

    public function testAdd__cant_overwrite_scalar() {
        $test = new DotAddressableCollection();
        $test->set('foo', 'bar');
        $this->expectException(InvalidArgumentException::class);
        $test->add('foo', 'baz');
    }

    public function testHas() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertTrue($test->has($this->key));
        $this->assertFalse($test->has($this->undefinedKey));
    }

    public function testEmpty() {
        $test = new DotAddressableCollection();
        $this->assertTrue($test->empty());
        $test->add('foo');
        $this->assertFalse($test->empty());
    }

    public function testAll() {
        $test = new DotAddressableCollection($this->arr);
        $this->assertEquals($this->arr, $test->all());
    }

    public function testMerge() {
        $test = new DotAddressableCollection([
            'pets' => [ 'cat' ],
            'sounds' => [ 'meow' ]
        ]);
        $with = new DotAddressableCollection([
            'pets' => [ 'dog' ],
            'sounds' => [ 'woof' ]
        ]);
        $test->merge($with->all());

        $this->assertEquals([ 'cat', 'dog' ], $test->get('pets'));
        $this->assertEquals([ 'meow', 'woof' ], $test->get('sounds'));
    }

}
