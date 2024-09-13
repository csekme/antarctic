<?php

namespace Framework;

class View
{
    /**
     * Render a view file
     *
     * @param string $view  The view file
     * @param array $args  Associative array of data to display in the view (optional)
     *
     * @return void
     */
    public static function render($view, $args = [])
    {
        extract($args, EXTR_SKIP);

        $file = dirname(__DIR__) . "/Application/Views/$view";
        if (!is_readable($file)) {
            $file = dirname(__DIR__) . "/Framework/Views/$view";
        }
        if (is_readable($file)) {
            require $file;
        } else {
            throw new \Exception("$file not found");
        }
    }

    /**
     * Render a view template using Twig
     *
     * @param string $template  The template file
     * @param array $args  Associative array of data to display in the view (optional)
     *
     * @return string
     */
    public static function renderTemplate($template, $args = []): string
    {
        return static::getTemplate($template, $args);
    }

    /**
     * Get the contents of a view template using Twig
     *
     * @param string $template  The template file
     * @param array $args  Associative array of data to display in the view (optional)
     *
     * @return string
     */
    public static function getTemplate(string $template, array $args = []): string
    {
        static $twig = null;

        if ($twig === null) {
            $dir = null;
            if (file_exists(dirname(__DIR__) . '/Application/Views/' . $template)) {
                $dir = dirname(__DIR__) . '/Application/Views';
            } else if (file_exists(dirname(__DIR__) . '/Framework/Views/' . $template)) {
                $dir = dirname(__DIR__) . '/Framework/Views';
            }
            if ($dir == null) {
                throw new \Exception("Template not found", 500);
            }
            $loader = new \Twig\Loader\FilesystemLoader($dir);
            $twig = new \Twig\Environment($loader, array('debug' => true));
            $twig->addExtension(new RoleExtension());
            $twig->addGlobal('session', $_SESSION);
            $twig->addGlobal('flash_messages', Flash::getMessages());
            $twig->addGlobal('base_path', "/");
            if (isset($_SESSION['csrf'])) {
                $twig->addGlobal('csrf_token', $_SESSION['csrf']->getValue());
            }

        }
        $html = $twig->render($template, $args);
        if (isset($_SESSION['csrf'])) {
            $csrfToken = $_SESSION['csrf']->getValue();
            $csrfExtension = new CsrfExtension($csrfToken);
            $html = $csrfExtension->addCsrfToForms($html);
        }
        return $html;
    }
}
