<?php

namespace Framework\TwigExtensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ResetFormExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('insert_reset_form_script', [$this, 'insertResetFormScript'], ['is_safe' => ['html']])
        ];
    }

    /**
     * Insert resetFormInputs script before </body> tag
     *
     * @param string $html The original HTML content
     * @return string The modified HTML content with the script inserted
     */
    public function insertResetFormScript(string $html): string
    {
        $script = <<<EOT
        <script>
            function resetFormInputs(formId) {
                const form = document.getElementById(formId);
                if (form) {
                    const inputs = form.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            input.checked = false;
                        } else {
                            if (input.name !== '_csrf') {
                                input.value = '';
                            }
                        }
                    });
                }
            }
        </script>
        EOT;
        // Insert the script before </body>
        return preg_replace('/<\/body>/i', $script . '</body>', $html);
    }
}

