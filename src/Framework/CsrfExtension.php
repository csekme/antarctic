<?php
namespace Framework;
/**
 * CSRF protection extension for Twig
 */
class CsrfExtension extends \Twig\Extension\AbstractExtension
{
    private $csrfToken;

    public function __construct($csrfToken)
    {
        $this->csrfToken = $csrfToken;
    }

    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('add_csrf_to_forms', [$this, 'addCsrfToForms'], ['is_safe' => ['html']]),
        ];
    }

    public function addCsrfToForms($content): array|string|null
    {
        // CSRF input mező létrehozása
        $csrfInput = sprintf('<input type="hidden" name="_scrf" value="%s"/>', $this->csrfToken);

        // Az input mező automatikus beillesztése minden <form> elem végére
        return preg_replace('/(<\/form>)/i', $csrfInput . '$1', $content);
    }
}
