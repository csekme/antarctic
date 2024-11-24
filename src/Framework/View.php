<?php

namespace Framework;

use Exception;
use Framework\TwigExtensions\ResetFormExtension;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * View rendering class using Twig
 *
 * PHP version 8.0
 * @category Framework
 * @package  Framework
 * @since 1.0
 * @license GPL-3.0-or-later
 * @author Krisztián Csekme
 */
class View
{
    // We need this constant to show a modal window by id
    public static string $SHOW_MODAL_BY_ID = "showModalById";
    public static string $TWIG_GLOBAL_ARRAY = "twig_global_array";

    /**
     * Render a view file
     *
     * @param string $view The view file
     * @param array $args Associative array of data to display in the view (optional)
     *
     * @return void
     * @throws Exception
     */
    public static function render(string $view, array $args = []): void
    {
        extract($args, EXTR_SKIP);

        $file = dirname(__DIR__) . "/Application/Views/$view";
        if (!is_readable($file)) {
            $file = dirname(__DIR__) . "/Framework/Views/$view";
        }
        if (is_readable($file)) {
            require $file;
        } else {
            throw new Exception("$file not found");
        }
    }

    /**
     * Render a view template using Twig
     *
     * @param string $template The template file
     * @param array $args Associative array of data to display in the view (optional)
     *
     * @return string
     * @throws Exception
     */
    public static function renderTemplate(string $template, array $args = []): string
    {
        return static::getTemplate($template, $args);
    }

    /**
     * Get the contents of a view template using Twig
     *
     * @param string $template The template file
     * @param array $args Associative array of data to display in the view (optional)
     *
     * @return string The template content
     * @throws LoaderError When the template cannot be found
     * @throws RuntimeError When an error occurred during rendering
     * @throws SyntaxError When a syntax error occurred during tokenizing
     * @throws Exception When the template cannot be found
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
                throw new Exception("Template not found", 500);
            }
            $loader = new \Twig\Loader\FilesystemLoader($dir);
            $twig = new \Twig\Environment($loader, array('debug' => true));
            $twig->addExtension(new RoleExtension());

            //load external extensions
            $externalExtensions = dirname(__DIR__) . '/Application/TwigExtensions';
            if (is_dir($externalExtensions)) {
                $files = scandir($externalExtensions);
                foreach ($files as $file) {
                    if (is_file($externalExtensions . '/' . $file)) {
                        $extension = 'Application\\TwigExtensions\\' . pathinfo($file, PATHINFO_FILENAME);
                        $twig->addExtension(new $extension());
                    }
                }
            }
            $twig->addGlobal('session', $_SESSION);
            $twig->addGlobal('flash_messages', Flash::getMessages());
            $twig->addGlobal('base_path', "/");
            $twig->addGlobal('current_user', Auth::getUser());
            if (isset($_SESSION['csrf'])) {
                $twig->addGlobal('csrf_token', $_SESSION['csrf']->getValue());
            }
            if (isset($_SESSION[static::$TWIG_GLOBAL_ARRAY])) {
                foreach ($_SESSION[static::$TWIG_GLOBAL_ARRAY] as $key => $value) {
                    $twig->addGlobal($key, $value);
                }
            }
        }
        $html = $twig->render($template, $args);

        if (isset($_SESSION['csrf'])) {
            $csrfToken = $_SESSION['csrf']->getValue();
            $csrfExtension = new CsrfExtension($csrfToken);
            $html = $csrfExtension->addCsrfToForms($html);
        }

        $resetFormExtension = new ResetFormExtension();
        $html = $resetFormExtension->insertResetFormScript($html);
        return $html;
    }
}
