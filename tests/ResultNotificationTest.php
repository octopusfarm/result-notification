<?php

/**
 * @license https://opensource.org/licenses/MIT
 */

namespace Tests;

use BadMethodCallException;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use OctopusFarm\ResultNotification\DotAddressableCollection;
use OctopusFarm\ResultNotification\ResultNotification as Result;


class ResultNotificationTest extends TestCase {

    private array $results = [ "yay" => [ "these", "things", "worked" ] ];
    private array $errors = [ "oh no" => [ "these", "things", "didn't", "work" ] ];


    public function test__construct__no_args() {
        $test = new Result();
        $this->assertTrue($test->valid());
    }

    public function test__construct__with_args() {

        // ResultNotifications with no errors are valid.
        $test = new Result(results: $this->results);
        $this->assertTrue($test->valid());

        // ResultNotifications with errors are invalid.
        $test = new Result(results: $this->results, errors: $this->errors);
        $this->assertFalse($test->valid());

    }

    public function test__call() {
        $errors = Mockery::mock(DotAddressableCollection::class);
        $test = Mockery::mock(Result::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('parseActionAndCollectionName')
                ->once()->with('addError')->andReturn(['get', 'errors'])
            ->shouldReceive('collectionByName')
                ->once()->with('errors')->andReturn($errors)
            ->shouldReceive('performAction')
                ->once()->with('get', $errors, 'foo')->andReturn('bar')
            ->getMock();
        $this->assertEquals("bar", $test->__call('addError', ['foo']));
    }

    public function testCounting() {

        $test = new Result($this->results, $this->errors);
        $test->addResult('there are two results');
        $test->addError('there are');
        $test->addError('three errors');

        // Test counting without keys (which means "count the root subscript.")
        $this->assertEquals(2, $test->resultCount());
        $this->assertEquals(3, $test->errorCount());

        // Test counting with keys.
        $this->assertEquals(3, $test->resultCount('yay'));
        $this->assertEquals(4, $test->errorCount('oh no'));

        // Test counting something that isn't there.
        $this->assertNull($test->resultCount('foo'));
        $this->assertNull($test->errorCount('bar'));

    }

    public function testMerge() {
        $a = new Result(['results' => 'a'], ['errors' => 'c']);
        $b = new Result(['results' => 'b'], ['errors' => 'd']);
        $a->merge($b);
        $expected = new Result(['results' => ['a', 'b']], ['errors' => ['c', 'd']]);
        $this->assertEquals($expected, $a);
        $this->assertFalse($a->valid());
    }

    public function testParseActionAndCollectionName() {
        $test = new Result();
        $expectations = [
            'addResult' => ['add', 'results'],
            'setResult' => ['set', 'results'],
            'error' => ['get', 'errors'],
            'results' => ['all', 'results'],
            'errors' => ['all', 'errors']
        ];
        foreach($expectations as $call => $returns) {
            $this->assertEquals($returns, $this->callProtected($test, 'parseActionAndCollectionName', $call));
        }
    }

    public function testCollectionByName__valid() {
        $test = new Result($this->results);
        $collection = $this->callProtected($test, 'collectionByName', 'results');
        $this->assertEquals($this->results, $collection->all());
    }

    public function testCollectionByName__invalid() {
        $test = new Result($this->results);
        $this->expectException(BadMethodCallException::class);
        $this->callProtected($test, 'collectionByName', 'something other than results or errors');
    }

    public function testPerformAction__not_understood() {
        $collection = new DotAddressableCollection();
        $test = new Result();
        $this->expectException(BadMethodCallException::class);
        $this->callProtected($test, 'performAction', 'an invalid action', $collection);
    }

    public function testPerformAction() {
        $collection = new DotAddressableCollection(['foo' => 'bar']);
        $test = new Result();
        $this->assertEquals('bar', $this->callProtected($test, 'performAction', 'get', $collection, 'foo'));
    }

    public function testValid() {
        $test = new Result();
        $this->assertTrue($test->valid());
        $test->addResult("I can add a result to this without invalidating it.");
        $this->assertTrue($test->valid());
        $test->addError("However, if I add an error, it will become invalid.");
        $this->assertFalse($test->valid());
    }

    public function testSerialize() {
        $test = new Result($this->results, $this->errors);
        $this->assertEquals($this->serializedResult(), $test->serialize(false));
    }

    public function testJsonSerialize() {
        $test = new Result($this->results, $this->errors);
        $expected = json_encode($this->serializedResult());
        $this->assertEquals($expected, json_encode($test));
    }

    public function testFromArray() {
        $test = Result::fromArray($this->serializedResult());
        $this->assertEquals($this->results, $test->results());
        $this->assertEquals($this->errors, $test->errors());
        $this->assertFalse($test->valid());
    }

    public function serializedResult() : array {
        return [
            'valid' => false,
            'results' => $this->results,
            'errors' => $this->errors
        ];
    }

    /**
     * Allows directly calling protected methods, which have nothing to hide from their own tests.
     *
     * @param object $object
     * @param string $method
     * @param ...$args
     * @return mixed
     * @throws ReflectionException
     * @codeCoverageIgnore
     */
    public function callProtected(object $object, string $method, ...$args) : mixed {
        $reflectionMethod = new ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);
        if($reflectionMethod->isStatic()) {
            $object = null;
        }
        return $reflectionMethod->invoke($object, ...$args);
    }

}
