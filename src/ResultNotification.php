<?php

namespace OctopusFarm\ResultNotification;

use BadMethodCallException;
use JetBrains\PhpStorm\Internal\TentativeType;
use JsonSerializable;
use OctopusFarm\ResultNotification\DotAddressableCollection as Collection;

/**
 * @method void setResult(string $key, mixed $val)
 * @method void addResult(string $keyOrVal, mixed $val = null)
 * @method mixed getResult(string $key, bool $nullIfNotFound = false)
 * @method bool unsetResult(string $key)
 * @method bool hasResult(string $key)
 * @method void setError(string $key, mixed $val)
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


    public function __construct(array $results = [], array $errors = []) {
        $this->results = new Collection($results);
        $this->errors = new Collection($errors);
        $this->valid = count($errors) == 0;
    }

    public function __call(string $method, array $args) : mixed {
        [ $action, $collectionName ] = $this->parseActionAndCollectionName($method);
        $collection = $this->collectionByName($collectionName);
        return $this->performAction($action, $collection, ...$args);
    }

    public function resultCount(?string $key=null) : ?int {
        return $this->results->count($key);
    }

    public function errorCount(?string $key=null) : ?int {
        return $this->errors->count($key);
    }

    public function merge(ResultNotification $otherResultNotification) : self {
        $this->results->merge($otherResultNotification->results());
        $this->errors->merge($otherResultNotification->errors());
        return $this;
    }

    protected function parseActionAndCollectionName(string $operation) : array {

        $segs = preg_split('/Result(s)?|Error(s)?/', $operation);

        // If no action is specified, default to null and figure it out below.
        $action = count($segs) > 1 ? $segs[0] : null;
        $collectionName = str_replace($action ?? '', '', $operation);

        $isPlural = str_ends_with($collectionName, 's');
        if(!$action) {
            $action = $isPlural ? 'all' : 'get';
        }

        return [ $action, strtolower($isPlural ? $collectionName : $collectionName . 's') ];

    }

    protected function collectionByName(string $collectionName) : ?Collection {

        return match($collectionName) {
            'results' => $this->results,
            'errors' => $this->errors,
            default => throw new BadMethodCallException("Invalid target collection $collectionName.")
        };

    }

    protected function performAction(string $action, Collection $collection, ...$args) : mixed {
        if(!method_exists($collection, $action)) {
            throw new BadMethodCallException("'$action' not understood.");
        }
        return $collection->$action(...$args);
    }

    public function valid() : bool {
        return $this->errors->count() == 0;
    }

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

    public function jsonSerialize(): array {
        return $this->serialize(false);
    }

    static public function fromArray(array $arr) : ResultNotification {
        return new ResultNotification($arr['results'] ?? [], $arr['errors'] ?? []);
    }

}
