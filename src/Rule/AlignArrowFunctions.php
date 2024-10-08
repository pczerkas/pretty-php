<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align arrow function expressions with their definitions
 *
 * @api
 */
final class AlignArrowFunctions implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 380;

            case self::CALLBACK:
                return 710;

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_FN => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $arrow = $token->nextSiblingOf(\T_DOUBLE_ARROW);
            /** @var Token */
            $body = $this->Formatter->NewlineBeforeFnDoubleArrows
                ? $arrow
                : $arrow->NextCode;

            if (!$body->hasNewlineBefore()) {
                continue;
            }

            // If the arrow function's arguments break over multiple lines,
            // align with the start of the previous line
            assert($body->Prev && $token->EndStatement);
            /** @var Token */
            $alignWith = $token->collect($body->Prev)
                               ->reverse()
                               ->find(fn(Token $t) =>
                                          $t === $token
                                              || ($t->Flags & TokenFlag::CODE && $t->hasNewlineBefore()));

            $body->AlignedWith = $alignWith;
            $this->Formatter->registerCallback(
                static::class,
                $body,
                fn() => $this->alignBody($body, $alignWith, $token->EndStatement),
            );
        }
    }

    private function alignBody(Token $body, Token $alignWith, Token $until): void
    {
        $offset = $alignWith->alignmentOffset(false) + $this->Formatter->TabSize;
        $delta = $body->indentDelta($alignWith);
        $delta->LinePadding += $offset;

        foreach ($body->collect($until) as $token) {
            $delta->apply($token);
        }
    }
}
