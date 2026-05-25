<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Egyetlen console parancs interfésze. Új parancsot implementálsz,
 * regisztrálsz az {@see Application}-ben, és a bin/console hívja.
 */
interface Command
{
    /** Pl. "keys:generate" */
    public function name(): string;

    /** Egy mondatos összefoglaló a parancs listához. */
    public function description(): string;

    /**
     * Futtatás. A `$argv` az adott parancs UTÁNI argumentumokat tartalmazza
     * (a parancsnév nem szerepel benne). Visszatérési érték = OS exit code.
     *
     * @param list<string> $argv
     */
    public function run(array $argv): int;
}
