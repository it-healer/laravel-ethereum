<?php

namespace ItHealer\LaravelEthereum\Api;

use Closure;
use Generator;
use IteratorAggregate;

/**
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
class DTOPaginator implements IteratorAggregate
{
    protected Closure $callback;
    protected int $perPage;

    public function __construct(Closure $callback, int $perPage = 10)
    {
        $this->callback = $callback;
        $this->perPage = $perPage;
    }

    public function getIterator(): Generator
    {
        $page = 1;

        while (true) {
            $items = call_user_func($this->callback, $page);

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                yield $item;
            }

            if (count($items) < $this->perPage) {
                break;
            }

            $page++;
        }
    }
}
