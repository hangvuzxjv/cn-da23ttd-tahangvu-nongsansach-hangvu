<?php

declare(strict_types=1);

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'client'): void
    {
        View::render($template, $data, $layout);
    }
}
