<?php
namespace Framework\TwigExtensions;
use Twig\Extension\AbstractExtension;

class BasePathExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('base_path', [$this, 'extractBasePath'], ['is_safe' => ['html']]),
        ];
    }

    public function extractBasePath()
    {
        $currentUrl = $_SERVER['REQUEST_URI'];
        $currentUrl = parse_url($currentUrl, PHP_URL_PATH);
        $segments = explode('/', trim($currentUrl, '/'));
        return $segments[0] ?? '';
    }
}