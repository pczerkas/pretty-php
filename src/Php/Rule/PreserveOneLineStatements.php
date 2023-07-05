<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;
use Lkrms\Utility\Test;

/**
 * Suppress newlines in statements and control structures that start and end on
 * the same line, including individual case statements
 *
 * Examples:
 *
 * ```php
 * // Short anonymous functions
 * $callback = function ($value) { $result = doSomethingWith($value); return $result; };
 *
 * // Case statements
 * switch ($value) {
 *     case 1: $result = doSomething(); break;
 *     case 2: $result = doSomethingElse(); break;
 *     default: $result = doDefaultThing(); break;
 * }
 * ```
 *
 */
final class PreserveOneLineStatements implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 95;
    }

    public function processToken(Token $token): void
    {
        if ($token->Statement === $token &&
                !$this->preserveOneLine(
                    $token,
                    $until = $token->pragmaticEndOfExpression(false, false)
                ) &&
                $token->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT])) {
            $this->preserveOneLine(
                $token->skipAnySiblingsOf(T_ATTRIBUTE, T_ATTRIBUTE_COMMENT),
                $until
            );
        }
    }
}
