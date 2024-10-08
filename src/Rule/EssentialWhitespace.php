<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\RuleTrait;
use Lkrms\PrettyPHP\Contract\Rule;
use Salient\Utility\Regex;

/**
 * Add whitespace after tokens that would otherwise fail to parse
 *
 * This rule adds sufficient whitespace to ensure formatter output can be parsed
 * if an edge case not covered by other rules should arise.
 *
 * @api
 */
final class EssentialWhitespace implements Rule
{
    use RuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::BEFORE_RENDER:
                return 999;

            default:
                return null;
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($tokens as $token) {
            $next = $token->Next;
            if (!$next
                    || $token->String
                    || $next->String
                    || $token->hasNewlineAfter()) {
                continue;
            }

            /* Add newlines after one-line comments with no subsequent `?>` */
            if ($token->Flags & TokenFlag::ONELINE_COMMENT
                    && $next->id !== \T_CLOSE_TAG) {
                $token->WhitespaceAfter |= WhitespaceType::LINE;
                $token->WhitespaceMaskNext |= WhitespaceType::LINE;
                $next->WhitespaceMaskPrev |= WhitespaceType::LINE;
                continue;
            }

            if ($token->effectiveWhitespaceAfter()
                    || $this->Idx->SuppressSpaceAfter[$token->id]
                    || $this->Idx->SuppressSpaceBefore[$next->id]) {
                continue;
            }

            if ($token->id === \T_OPEN_TAG
                    || Regex::match(
                        '/^[a-zA-Z0-9\\\\_\x80-\xff]{2}$/',
                        ($token->text[-1] ?? '') . ($next->text[0] ?? '')
                    )) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            }
        }
    }
}
