<?php

namespace App\Services\Dashboard\Contracts;

/**
 * A self-contained dashboard card that knows how to gather its own data.
 */
interface DashboardWidget
{
    /**
     * The prop name the widget is exposed under on the page.
     */
    public function key(): string;

    /**
     * @return array<string, mixed>
     */
    public function data(): array;
}
