<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class BracePosition implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!($token->is("{") || ($token->is("}") && $token->OpenedBy->is("{"))))
        {
            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if ($token->is("{"))
        {
            $token->WhitespaceBefore  |= $token->isDeclaration() ? WhitespaceType::LINE : WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::ALL & ~WhitespaceType::BLANK;

            return;
        }
        $token->WhitespaceBefore  |= WhitespaceType::LINE;
        $token->WhitespaceMaskPrev = WhitespaceType::ALL & ~WhitespaceType::BLANK;
    }
}
