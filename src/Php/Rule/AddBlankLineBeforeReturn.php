<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

/**
 * Add a blank line before return and yield statements unless they appear
 * consecutively or at the beginning of a statement group
 *
 */
final class AddBlankLineBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 97;
    }

    public function getTokenTypes(): ?array
    {
        return [
            T_RETURN,
            T_YIELD,
            T_YIELD_FROM,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->prevStatementStart()->is([T_RETURN, T_YIELD, T_YIELD_FROM])) {
            return;
        }
        $prev = $token->prev();
        while ($prev->is(TokenType::COMMENT) && $prev->hasNewlineBefore()) {
            $prev->PinToCode = true;
            $prev            = $prev->prev();
        }
        $token->WhitespaceBefore |= WhitespaceType::BLANK | WhitespaceType::SPACE;
    }
}
