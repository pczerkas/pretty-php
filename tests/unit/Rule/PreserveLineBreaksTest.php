<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

final class PreserveLineBreaksTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'logical operator after bracket' => [
                <<<'PHP'
<?php
return a($b) && a($c) &&
    strcmp((string) $b, (string) $c) === 0;

PHP,
                <<<'PHP'
<?php
return a($b) && a($c)
    && strcmp((string) $b, (string) $c) === 0;
PHP,
            ],
        ];
    }
}
