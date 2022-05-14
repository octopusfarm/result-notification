<?php

/**
 * Built on Martin Fowler's "replace exceptions with notifications" idea. Pass, return, and merge results and errors
 * without boilerplate. Supports dot-notation array keys.
 *
 * Please see ../README.md for more details, or run (and step through) ../demo.php if you'd like to see some practical
 * examples.
 *
 * @license https://opensource.org/licenses/MIT
 */

namespace OctopusFarm\ResultNotification;

use BadMethodCallException;
use JsonSerializable;
use OctopusFarm\ResultNotification\DotAddressableCollection as Collection;

/**
 * @method void setResult(string $key, mixed $val)
 * @method void setResults(array $results)
 * @method void addResult(string $keyOrVal, mixed $val = null)
 * @method mixed getResult(string $key, bool $nullIfNotFound = false)
 * @method bool unsetResult(string $key)
 * @method bool hasResult(string $key)
 * @method void setError(string $key, mixed $val)
 * @method void setErrors(array $errors)
 * @method void addError(string $keyOrVal, mixed $val = null)
 * @method mixed getError(string $key, bool $nullIfNotFound = false)
 * @method bool unsetError(string $key)
 * @method bool hasError(string $key)
 * @method array all()
 * @method array results()
 * @method array errors()
 */
class ResultNotification implements JsonSerializable {

    protected bool $valid;
    protected Collection $results;
    protected Collection $errors;


    /**
     * @param array $results
     * @param array $errors
     */
    public function __construct(array $results = [], array $errors = []) {
        $this->results = new Collection($results);
        $this->errors = new Collection($errors);
        $this->valid = count($errors) == 0;
    }

    /**
     * In order to avoid copypasta, most calls require parsing.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call(string $method, array $args) : mixed {
        [ $action, $collectionName ] = $this->parseActionAndCollectionName($method);
        $collection = $this->collectionByName($collectionName);
        return $this->performAction($action, $collection, ...$args);
    }

    /**
     * @param string|null $key
     * @return int|null
     */
    public function resultCount(?string $key=null) : ?int {
        return $this->results->count($key);
    }

    /**
     * @param string|null $key
     * @return int|null
     */
    public function errorCount(?string $key=null) : ?int {
        return $this->errors->count($key);
    }

    /**
     * Merge another ResultNotification into this one.
     * - Please note that objects aren't deep-copied, and references aren't de-referenced.
     *
     * @param ResultNotification $otherResultNotification
     * @return $this
     */
    public function merge(ResultNotification $otherResultNotification) : self {
        $this->results->merge($otherResultNotification->results());
        $this->errors->merge($otherResultNotification->errors());
        return $this;
    }

    /**
     * Given an operation like "setError", "results," etc., figure out which collection the method applies to, and
     * which method to call on DotAddressableCollection.
     * - See the doc block just above this class for a list.
     *
     * @param string $operation
     * @return array
     */
    protected function parseActionAndCollectionName(string $operation) : array {

        // Operation format: [add|set|unset](Result[s]|Error[s])
        //                   If no add|set|unset, "get" is implied.
        $segs = preg_split('/Result(s)?|Error(s)?/', $operation);

        // If add|set|unset isn't specified, default to null and figure it out below.
        $action = count($segs) > 1 ? $segs[0] : null;

        $collectionName = str_replace($action ?? '', '', $operation);
        $isPlural = str_ends_with($collectionName, 's');

        // Get one: result($key), error($key)
        // Get all: results(), errors()
        if(!$action) {
            $action = $isPlural ? 'all' : 'get';
        }

        // setResults(), setErrors()
        if($isPlural && $action === 'set') {
            $action = 'setAll';
        }

        return [ $action, strtolower($isPlural ? $collectionName : $collectionName . 's') ];

    }

    /**
     * Return the collection by its name if valid, else throw.
     *
     * @param string $collectionName
     * @return DotAddressableCollection|null
     */
    protected function collectionByName(string $collectionName) : ?Collection {
        if(property_exists($this, $collectionName) && $this->$collectionName instanceof DotAddressableCollection) {
            return $this->$collectionName;
        }
        throw new BadMethodCallException("Invalid target collection $collectionName.");
    }

    /**
     * Perform an action (set a result, get an error, etc.) on a DotAddressableCollection.
     *
     * @param string $action
     * @param DotAddressableCollection $collection
     * @param ...$args
     * @return mixed
     */
    protected function performAction(string $action, Collection $collection, ...$args) : mixed {
        if(!method_exists($collection, $action)) {
            throw new BadMethodCallException("'$action' not understood.");
        }
        return $collection->$action(...$args);
    }

    /**
     * True when nothing has gone wrong, so far. This is the state when no errors have been set.
     *
     * @return bool
     */
    public function valid() : bool {
        return $this->errors->count() == 0;
    }

    /**
     * Turn this ResultNotification into an array.
     *
     * @param bool $terse
     * @return array
     */
    public function serialize(bool $terse = false) : array {
        $out = [];
        if(!$terse) {
            $out['valid'] = $this->valid();
        }
        if(!$terse || !$this->results->empty()) {
            $out['results'] = $this->results->all();
        }
        if(!$terse || !$this->errors->empty()) {
            $out['errors'] = $this->errors->all();
        }
        return $out;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->serialize(false);
    }

    /**
     * Turn a serialized ResultNotification into a live object.
     *
     * @param array $arr
     * @return ResultNotification
     */
    static public function fromArray(array $arr) : ResultNotification {
        return new ResultNotification($arr['results'] ?? [], $arr['errors'] ?? []);
    }

}
