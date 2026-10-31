<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        $templateFile = WAYFAIRS_ROOT . '/app/Views/' . $template . '.php';
        $layoutFile = WAYFAIRS_ROOT . '/app/Views/' . $layout . '.php';
        if (!is_file($templateFile) || !is_file($layoutFile)) {
            throw new \RuntimeException('View template is missing.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();
        require $layoutFile;
    }
}
