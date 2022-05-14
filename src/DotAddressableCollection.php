<?php

/**
 * Provides an array collection where keys are dot-addressable, where subscripts are delimited with dots instead of
 * brackets, e.g.: ['a']['b']['c'] => 'a.b.c'
 *
 * Currently uses Adbar\Dot, but this can be replaced with some other implementation if that falls out of maintenance,
 * or if some other behavior is desired. The point is to protect ResultNotification from being tightly coupled with
 * any particular implementation, and to adhere to the Single Responsibility Principle.
 *
 * In case you're wondering, I didn't use Laravel's Collection class because only some of its methods support dot
 * notation, and because it's nice not to bring in a huge dependency. (Not everyone uses Laravel.)
 *
 * @license https://opensource.org/licenses/MIT
 */

namespace OctopusFarm\ResultNotification;

use Adbar\Dot;
use InvalidArgumentException;
use Throwable;


class DotAddressableCollection {

    protected Dot $dot;


    public function __construct(array $values = []) {
        $this->dot = dot($values);
    }

    public function set(string $key, mixed $val) : void {
        $this->dot->set($key, $val);
    }

    public function setAll(array $arr) : void {
        $this->dot->setArray($arr);
    }

    public function get(string $key, bool $nullIfNotFound = false) : mixed {
        if($this->dot->has($key)) {
            return $this->dot->get($key);
        }
        if($nullIfNotFound) {
            return null;
        }
        throw new InvalidArgumentException("Key $key not found.");
    }

    public function count(?string $key = null) : ?int {
        try {
            return $this->dot->count($key);
        } catch(Throwable) {
            return null;
        }
    }

    public function unset(string $key) : bool {
        $has = $this->dot->has($key);
        $this->dot->delete($key);
        return $has;
    }

    public function add(string $keyOrValue, mixed $val=null) : void {
        if(func_num_args() == 1) {
            $this->dot->push($keyOrValue);
            return;
        }
        if($this->dot->has($keyOrValue)) {
            if(!is_array($this->dot->get($keyOrValue))) {
                throw new InvalidArgumentException("Key $keyOrValue already exists and isn't an array.");
            }
        }
        $this->dot->push($keyOrValue, $val);
    }

    public function has(string $key) : bool {
        return $this->dot->has($key);
    }

    public function empty() : bool {
        return $this->dot->isEmpty();
    }

    public function all() : array {
        return $this->dot->all();
    }

    public function merge(array $arr) : self {
        $this->dot->mergeRecursive($arr);
        return $this;
    }

}
