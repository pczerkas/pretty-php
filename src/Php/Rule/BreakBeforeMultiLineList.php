<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add a newline after the open bracket of a multi-line delimited list
 *
 */
final class BreakBeforeMultiLineList implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 380;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if (!$items->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        $owner->WhitespaceAfter            |= WhitespaceType::LINE;
        $owner->WhitespaceMaskNext         |= WhitespaceType::LINE;
        $owner->next()->WhitespaceMaskPrev |= WhitespaceType::LINE;
    }
}
