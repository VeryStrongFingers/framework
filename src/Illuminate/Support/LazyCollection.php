<?php

namespace Illuminate\Support;

use Closure;
use stdClass;
use ArrayIterator;
use IteratorAggregate;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\EnumeratesValues;

class LazyCollection implements Enumerable
{
    use EnumeratesValues, Macroable;

    /**
     * The source from which to generate items.
     *
     * @var callable|static
     */
    public $source;

    /**
     * Create a new lazy collection instance.
     *
     * @param  mixed  $source
     * @return void
     */
    public function __construct($source = null)
    {
        if ($source instanceof Closure || $source instanceof self) {
            $this->source = $source;
        } elseif (is_null($source)) {
            $this->source = static::empty();
        } else {
            $this->source = $this->getArrayableItems($source);
        }
    }

    /**
     * Create a new instance with no items.
     *
     * @return static
     */
    public static function empty()
    {
        return new static([]);
    }

    /**
     * Create a new instance by invoking the callback a given amount of times.
     *
     * @param  int  $number
     * @param  callable  $callback
     * @return static
     */
    public static function times($number, callable $callback = null)
    {
        if ($number < 1) {
            return new static;
        }

        $instance = new static(function () use ($number) {
            for ($current = 1; $current <= $number; $current++) {
                yield $current;
            }
        });

        return is_null($callback) ? $instance : $instance->map($callback);
    }

    /**
     * Create an enumerable with the given range.
     *
     * @param  int  $from
     * @param  int  $to
     * @return static
     */
    public static function range($from, $to)
    {
        return new static(function () use ($from, $to) {
            for (; $from <= $to; $from++) {
                yield $from;
            }
        });
    }

    /**
     * Get all items in the enumerable.
     *
     * @return array
     */
    public function all()
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        return iterator_to_array($this->getIterator());
    }

    /**
     * Collect the values into a collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collect()
    {
        return new Collection($this->all());
    }

    /**
     * Get the average value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function avg($callback = null)
    {
        return $this->collect()->avg($callback);
    }

    /**
     * Get the median of a given key.
     *
     * @param  string|array|null $key
     * @return mixed
     */
    public function median($key = null)
    {
        return $this->collect()->median($key);
    }

    /**
     * Get the mode of a given key.
     *
     * @param  string|array|null  $key
     * @return array|null
     */
    public function mode($key = null)
    {
        return $this->collect()->mode($key);
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        $original = clone $this;

        return new static(function () use ($original) {
            foreach ($original as $values) {
                if (is_array($values) || $values instanceof Enumerable) {
                    foreach ($values as $value) {
                        yield $value;
                    }
                }
            }
        });
    }

    /**
     * Determine if an item exists in the enumerable.
     *
     * @param  mixed  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1 && $this->useAsCallable($key)) {
            $placeholder = new stdClass;

            return $this->first($key, $placeholder) !== $placeholder;
        }

        if (func_num_args() === 1) {
            $needle = $key;

            foreach ($this as $value) {
                if ($value == $needle) {
                    return true;
                }
            }

            return false;
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Cross join the given iterables, returning all possible permutations.
     *
     * @param  array  ...$arrays
     * @return static
     */
    public function crossJoin(...$arrays)
    {
        return $this->passthru('crossJoin', func_get_args());
    }

    /**
     * Get the items that are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diff($items)
    {
        return $this->passthru('diff', func_get_args());
    }

    /**
     * Get the items that are not present in the given items, using the callback.
     *
     * @param  mixed  $items
     * @param  callable  $callback
     * @return static
     */
    public function diffUsing($items, callable $callback)
    {
        return $this->passthru('diffUsing', func_get_args());
    }

    /**
     * Get the items whose keys and values are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffAssoc($items)
    {
        return $this->passthru('diffAssoc', func_get_args());
    }

    /**
     * Get the items whose keys and values are not present in the given items.
     *
     * @param  mixed  $items
     * @param  callable  $callback
     * @return static
     */
    public function diffAssocUsing($items, callable $callback)
    {
        return $this->passthru('diffAssocUsing', func_get_args());
    }

    /**
     * Get the items whose keys are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffKeys($items)
    {
        return $this->passthru('diffKeys', func_get_args());
    }

    /**
     * Get the items whose keys are not present in the given items.
     *
     * @param  mixed   $items
     * @param  callable  $callback
     * @return static
     */
    public function diffKeysUsing($items, callable $callback)
    {
        return $this->passthru('diffKeysUsing', func_get_args());
    }

    /**
     * Retrieve duplicate items.
     *
     * @param  callable|null  $callback
     * @param  bool  $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false)
    {
        return $this->passthru('duplicates', func_get_args());
    }

    /**
     * Retrieve duplicate items using strict comparison.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function duplicatesStrict($callback = null)
    {
        return $this->passthru('duplicatesStrict', func_get_args());
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function except($keys)
    {
        return $this->passthru('except', func_get_args());
    }

    /**
     * Run a filter over each of the items.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null)
    {
        if (is_null($callback)) {
            $callback = function ($value) {
                return (bool) $value;
            };
        }

        $original = clone $this;

        return new static(function () use ($original, $callback) {
            foreach ($original as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Get the first item from the enumerable passing the given truth test.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        $iterator = $this->getIterator();

        if (is_null($callback)) {
            if (! $iterator->valid()) {
                return value($default);
            }

            return $iterator->current();
        }

        foreach ($iterator as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    /**
     * Get a flattened list of the items in the collection.
     *
     * @param  int  $depth
     * @return static
     */
    public function flatten($depth = INF)
    {
        $original = clone $this;

        $instance = new static(function () use ($original, $depth) {
            foreach ($original as $item) {
                if (! is_array($item) && ! $item instanceof Enumerable) {
                    yield $item;
                } elseif ($depth === 1) {
                    yield from $item;
                } else {
                    yield from (new static($item))->flatten($depth - 1);
                }
            }
        });

        return $instance->values();
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        $original = clone $this;

        return new static(function () use ($original) {
            foreach ($original as $key => $value) {
                yield $value => $key;
            }
        });
    }

    /**
     * Remove an item by key.
     *
     * @param  string|array  $keys
     * @return $this
     */
    public function forget($keys)
    {
        $original = clone $this;

        $this->source = function () use ($original, $keys) {
            $keys = array_flip((array) $keys);

            foreach ($original as $key => $value) {
                if (! array_key_exists($key, $keys)) {
                    yield $key => $value;
                }
            }
        };

        return $this;
    }

    /**
     * Get an item by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (is_null($key)) {
            return;
        }

        foreach ($this as $outerKey => $outerValue) {
            if ($outerKey == $key) {
                return $outerValue;
            }
        }

        return value($default);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param  array|callable|string  $groupBy
     * @param  bool  $preserveKeys
     * @return static
     */
    public function groupBy($groupBy, $preserveKeys = false)
    {
        return $this->passthru('groupBy', func_get_args());
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param  callable|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy)
    {
        $original = clone $this;

        return new static(function () use ($original, $keyBy) {
            $keyBy = $this->valueRetriever($keyBy);

            foreach ($original as $key => $item) {
                $resolvedKey = $keyBy($item, $key);

                if (is_object($resolvedKey)) {
                    $resolvedKey = (string) $resolvedKey;
                }

                yield $resolvedKey => $item;
            }
        });
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key)
    {
        $keys = array_flip(is_array($key) ? $key : func_get_args());
        $count = count($keys);

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $keys) && --$count == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        return $this->collect()->implode(...func_get_args());
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function intersect($items)
    {
        return $this->passthru('intersect', func_get_args());
    }

    /**
     * Intersect the collection with the given items by key.
     *
     * @param  mixed  $items
     * @return static
     */
    public function intersectByKeys($items)
    {
        return $this->passthru('intersectByKeys', func_get_args());
    }

    /**
     * Determine if the items is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return ! $this->getIterator()->valid();
    }

    /**
     * Join all items from the collection using a string. The final items can use a separate glue string.
     *
     * @param  string  $glue
     * @param  string  $finalGlue
     * @return string
     */
    public function join($glue, $finalGlue = '')
    {
        return $this->collect()->join(...func_get_args());
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys()
    {
        $original = clone $this;

        return new static(function () use ($original) {
            foreach ($original as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * Get the last item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        $needle = $placeholder = new stdClass;

        foreach ($this as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $needle = $value;
            }
        }

        return $needle === $placeholder ? value($default) : $needle;
    }

    /**
     * Get the values of a given key.
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        $original = clone $this;

        return new static(function () use ($original, $value, $key) {
            [$value, $key] = $this->explodePluckParameters($value, $key);

            foreach ($original as $item) {
                $itemValue = data_get($item, $value);

                if (is_null($key)) {
                    yield $itemValue;
                } else {
                    $itemKey = data_get($item, $key);

                    if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }

                    yield $itemKey => $itemValue;
                }
            }
        });
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback)
    {
        $original = clone $this;

        return new static(function () use ($original, $callback) {
            foreach ($original as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapToDictionary(callable $callback)
    {
        return $this->passthru('mapToDictionary', func_get_args());
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $original = clone $this;

        return new static(function () use ($original, $callback) {
            foreach ($original as $key => $value) {
                yield from $callback($value, $key);
            }
        });
    }

    /**
     * Merge the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function merge($items)
    {
        return $this->passthru('merge', func_get_args());
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function mergeRecursive($items)
    {
        return $this->passthru('mergeRecursive', func_get_args());
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @param  mixed  $values
     * @return static
     */
    public function combine($values)
    {
        $original = clone $this;

        return new static(function () use ($original, $values) {
            $values = $this->makeIterator($values);

            $errorMessage = 'Both parameters should have an equal number of elements';

            foreach ($original as $key) {
                if (! $values->valid()) {
                    trigger_error($errorMessage, E_USER_WARNING);

                    break;
                }

                yield $key => $values->current();

                $values->next();
            }

            if ($values->valid()) {
                trigger_error($errorMessage, E_USER_WARNING);
            }
        });
    }

    /**
     * Union the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function union($items)
    {
        return $this->passthru('union', func_get_args());
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param  int  $step
     * @param  int  $offset
     * @return static
     */
    public function nth($step, $offset = 0)
    {
        $original = clone $this;

        return new static(function () use ($original, $step, $offset) {
            $position = 0;

            foreach ($original as $item) {
                if ($position % $step === $offset) {
                    yield $item;
                }

                $position++;
            }
        });
    }

    /**
     * Get the items with the specified keys.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function only($keys)
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (! is_null($keys)) {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        $original = clone $this;

        return new static(function () use ($original, $keys) {
            if (is_null($keys)) {
                yield from $original;
            } else {
                $keys = array_flip($keys);

                foreach ($original as $key => $value) {
                    if (array_key_exists($key, $keys)) {
                        yield $key => $value;
                    }
                }
            }
        });
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed
     */
    public function pop()
    {
        $items = $this->collect();

        $result = $items->pop();

        $this->source = $items;

        return $result;
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param  mixed  $value
     * @param  mixed  $key
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $original = clone $this;

        $this->source = function () use ($original, $value, $key) {
            $instance = new static(function () use ($original, $value, $key) {
                yield $key => $value;

                yield from $original;
            });

            if (is_null($key)) {
                $instance = $instance->values();
            }

            yield from $instance;
        };

        return $this;
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @param  iterable  $source
     * @return static
     */
    public function concat($source)
    {
        $original = clone $this;

        return (new static(function () use ($original, $source) {
            yield from $original;
            yield from $source;
        }))->values();
    }

    /**
     * Put an item in the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return $this
     */
    public function put($key, $value)
    {
        $original = clone $this;

        if (is_null($key)) {
            $this->source = function () use ($original, $value) {
                foreach ($original as $innerKey => $innerValue) {
                    yield $innerKey => $innerValue;
                }

                yield $value;
            };
        } else {
            $this->source = function () use ($original, $key, $value) {
                $found = false;

                foreach ($original as $innerKey => $innerValue) {
                    if ($innerKey == $key) {
                        yield $key => $value;

                        $found = true;
                    } else {
                        yield $innerKey => $innerValue;
                    }
                }

                if (! $found) {
                    yield $key => $value;
                }
            };
        }

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param  int|null  $number
     * @return static|mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null)
    {
        $result = $this->collect()->random(...func_get_args());

        return is_null($number) ? $result : new static($result);
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;

        foreach ($this as $value) {
            $result = $callback($result, $value);
        }

        return $result;
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function replace($items)
    {
        $original = clone $this;

        return new static(function () use ($original, $items) {
            $items = $this->getArrayableItems($items);
            $usedItems = [];

            foreach ($original as $key => $value) {
                if (array_key_exists($key, $items)) {
                    yield $key => $items[$key];

                    $usedItems[$key] = true;
                } else {
                    yield $key => $value;
                }
            }

            foreach ($items as $key => $value) {
                if (! array_key_exists($key, $usedItems)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function replaceRecursive($items)
    {
        return $this->passthru('replaceRecursive', func_get_args());
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return $this->passthru('reverse', func_get_args());
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param  mixed  $value
     * @param  bool  $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        $predicate = $this->useAsCallable($value)
            ? $value
            : function ($item) use ($value, $strict) {
                return $strict ? $item === $value : $item == $value;
            };

        foreach ($this as $key => $item) {
            if ($predicate($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return tap($this->first(), function () {
            $this->source = $this->skip(1);
        });
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param  int  $seed
     * @return static
     */
    public function shuffle($seed = null)
    {
        return $this->passthru('shuffle', func_get_args());
    }

    /**
     * Skip the first {$count} items.
     *
     * @param  int  $count
     * @return static
     */
    public function skip($count)
    {
        $original = clone $this;

        return new static(function () use ($original, $count) {
            $iterator = $original->getIterator();

            while ($iterator->valid() && $count--) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * Get a slice of items from the enumerable.
     *
     * @param  int  $offset
     * @param  int  $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        if ($offset < 0 || $length < 0) {
            return $this->passthru('slice', func_get_args());
        }

        $instance = $this->skip($offset);

        return is_null($length) ? $instance : $instance->take($length);
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param  int  $numberOfGroups
     * @return static
     */
    public function split($numberOfGroups)
    {
        return $this->passthru('split', func_get_args());
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param  int  $size
     * @return static
     */
    public function chunk($size)
    {
        if ($size <= 0) {
            return static::empty();
        }

        $original = clone $this;

        return new static(function () use ($original, $size) {
            $iterator = $original->getIterator();

            while ($iterator->valid()) {
                $values = [];

                for ($i = 0; $iterator->valid() && $i < $size; $i++, $iterator->next()) {
                    $values[$iterator->key()] = $iterator->current();
                }

                yield new static($values);
            }
        });
    }

    /**
     * Sort through each item with a callback.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function sort(callable $callback = null)
    {
        return $this->passthru('sort', func_get_args());
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        return $this->passthru('sortBy', func_get_args());
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return static
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->passthru('sortByDesc', func_get_args());
    }

    /**
     * Sort the collection keys.
     *
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortKeys($options = SORT_REGULAR, $descending = false)
    {
        return $this->passthru('sortKeys', func_get_args());
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @param  int $options
     * @return static
     */
    public function sortKeysDesc($options = SORT_REGULAR)
    {
        return $this->passthru('sortKeysDesc', func_get_args());
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param  int  $offset
     * @param  int|null  $length
     * @param  mixed  $replacement
     * @return static
     */
    public function splice($offset, $length = null, $replacement = [])
    {
        $items = $this->collect();

        $extracted = $items->splice(...func_get_args());

        $this->source = function () use ($items) {
            yield from $items;
        };

        return new static($extracted);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param  int  $limit
     * @return static
     */
    public function take($limit)
    {
        if ($limit < 0) {
            return $this->passthru('take', func_get_args());
        }

        $original = clone $this;

        return new static(function () use ($original, $limit) {
            $iterator = $original->getIterator();

            for (; $iterator->valid() && $limit--; $iterator->next()) {
                yield $iterator->key() => $iterator->current();
            }
        });
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function transform(callable $callback)
    {
        $original = clone $this;

        $this->source = function () use ($original, $callback) {
            yield from $original->map($callback);
        };

        return $this;
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values()
    {
        $original = clone $this;

        return new static(function () use ($original) {
            foreach ($original as $item) {
                yield $item;
            }
        });
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new LazyCollection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param  mixed ...$items
     * @return static
     */
    public function zip($items)
    {
        $iterables = func_get_args();

        $original = clone $this;

        return new static(function () use ($original, $iterables) {
            $iterators = Collection::make($iterables)->map(function ($iterable) {
                return $this->makeIterator($iterable);
            })->prepend($original->getIterator());

            while ($iterators->contains->valid()) {
                yield new static($iterators->map->current());

                $iterators->each->next();
            }
        });
    }

    /**
     * Pad collection to the specified length with a value.
     *
     * @param  int  $size
     * @param  mixed  $value
     * @return static
     */
    public function pad($size, $value)
    {
        if ($size < 0) {
            return $this->passthru('pad', func_get_args());
        }

        $original = clone $this;

        return new static(function () use ($original, $size, $value) {
            $yielded = 0;

            foreach ($original as $index => $item) {
                yield $index => $item;

                $yielded++;
            }

            while ($yielded++ < $size) {
                yield $value;
            }
        });
    }

    /**
     * Get the values iterator.
     *
     * @return \Traversable
     */
    public function getIterator()
    {
        return $this->makeIterator($this->source);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        if (is_array($this->source)) {
            return count($this->source);
        }

        return iterator_count($this->getIterator());
    }

    /**
     * Add an item to the collection.
     *
     * @param  mixed  $item
     * @return $this
     */
    public function add($item)
    {
        $original = clone $this;

        $this->source = function () use ($original, $item) {
            foreach ($original as $value) {
                yield $value;
            }

            yield $item;
        };

        return $this;
    }

    /**
     * Make an iterator from the given source.
     *
     * @param  mixed  $source
     * @return \Traversable
     */
    protected function makeIterator($source)
    {
        if ($source instanceof IteratorAggregate) {
            return $source->getIterator();
        }

        if (is_array($source)) {
            return new ArrayIterator($source);
        }

        return $source();
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    protected function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * Pass this lazy collection through a method on the collection class.
     *
     * @param  string  $method
     * @param  array  $params
     * @return static
     */
    protected function passthru($method, array $params)
    {
        $original = clone $this;

        return new static(function () use ($original, $method, $params) {
            yield from $original->collect()->$method(...$params);
        });
    }

    /**
     * Finish cloning the collection instance.
     *
     * @return void
     */
    public function __clone()
    {
        if (! is_array($this->source)) {
            $this->source = clone $this->source;
        }
    }
}