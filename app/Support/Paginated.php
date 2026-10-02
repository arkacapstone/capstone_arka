<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shapes a paginator into the `{data, meta}` structure the Pagination component expects.
 */
final class Paginated
{
    /**
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'links' => $paginator->linkCollection()->all(),
            ],
        ];
    }
}
