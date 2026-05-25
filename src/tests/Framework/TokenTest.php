<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testGeneratesRandom32HexCharacterTokenByDefault(): void
    {
        $token = new Token();

        $value = $token->getValue();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $value);
    }

    public function testGeneratedTokensAreUnique(): void
    {
        $a = new Token();
        $b = new Token();

        $this->assertNotSame($a->getValue(), $b->getValue());
    }

    public function testPreservesExplicitlyProvidedValue(): void
    {
        $token = new Token('explicit-value');

        $this->assertSame('explicit-value', $token->getValue());
    }
}
