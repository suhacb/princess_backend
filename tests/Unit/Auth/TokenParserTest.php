<?php

namespace Tests\Unit\Auth;

use App\Classes\Auth\TokenParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenParserTest extends TestCase
{
    private TokenParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TokenParser();
    }

    private function buildToken(array $claims): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        return "fakeheader.{$payload}.fakesig";
    }

    public function test_parses_valid_jwt_and_returns_claims_array(): void
    {
        $claims = ['sub' => 'uuid-123', 'email' => 'user@example.com', 'name' => 'Test User'];

        $result = $this->parser->parse($this->buildToken($claims));

        $this->assertSame($claims, $result);
    }

    public function test_throws_for_token_with_only_one_segment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('onlyone');
    }

    public function test_throws_for_token_with_two_segments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('one.two');
    }

    public function test_throws_for_non_json_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $badPayload = base64_encode('not-json-at-all');
        $this->parser->parse("header.{$badPayload}.sig");
    }
}
