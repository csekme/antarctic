<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Minimalista console runner. Parancsokat fogad, név alapján dispatch-el,
 * exit code-ot ad vissza. Nem tartalmaz interaktivitást — minden bemenet
 * argumentumon vagy stdin-en érkezik.
 */
final class Application
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * @param list<string> $argv A teljes $argv (a script neve a [0]).
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? null;

        if ($name === null || $name === 'list' || $name === 'help' || $name === '--help' || $name === '-h') {
            $this->printHelp();
            return 0;
        }

        if (!isset($this->commands[$name])) {
            fwrite(STDERR, sprintf("Unknown command: %s\n\n", $name));
            $this->printHelp();
            return 1;
        }

        $args = array_slice($argv, 2);
        /** @var list<string> $args */
        return $this->commands[$name]->run($args);
    }

    private function printHelp(): void
    {
        echo "Antarctic console\n";
        echo "Usage: bin/console <command> [arguments]\n\n";
        echo "Commands:\n";
        if ($this->commands === []) {
            echo "  (none registered)\n";
            return;
        }
        $width = max(array_map('strlen', array_keys($this->commands)));
        foreach ($this->commands as $command) {
            echo sprintf("  %-{$width}s   %s\n", $command->name(), $command->description());
        }
    }
}
