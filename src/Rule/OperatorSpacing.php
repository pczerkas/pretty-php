<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply whitespace to operators
 *
 * - Suppress whitespace after ampersands related to passing, assigning and
 *   returning by reference
 * - Suppress whitespace around operators in union, intersection and DNF types
 * - Suppress whitespace around exception delimiters in `catch` blocks (unless
 *   in strict PSR-12 mode)
 * - Suppress whitespace after `?` in nullable types
 * - Suppress whitespace between `++` and `--` and the variables they operate on
 * - Suppress whitespace after unary operators
 * - Collapse ternary operators with nothing between `?` and `:`
 *
 * Otherwise, add a space after each operator, and before operators except
 * non-ternary `:`.
 */
final class OperatorSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 80;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return $idx->Operator;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->Parent
                    && $token->Parent->PrevCode
                    && $token->Parent->PrevCode->id === \T_DECLARE) {
                continue;
            }

            // Suppress whitespace after ampersands related to passing,
            // assigning and returning by reference
            if ($this->Idx->Ampersand[$token->id]
                && $token->Next->Flags & TokenFlag::CODE
                // `function &getValue()`
                && (($token->PrevCode
                    && ($token->PrevCode->id === \T_FUNCTION
                        || $token->PrevCode->id === \T_FN))
                    // `[&$variable]`, `$a = &getValue()`
                    || $token->inUnaryContext()
                    // `function foo(&$bar)`, `function foo($bar, &...$baz)`
                    || (($token->Next->id === \T_VARIABLE
                            || $token->Next->id === \T_ELLIPSIS)
                        && $token->inParameterList()
                        // Not `function getValue($param = $a & $b)`
                        && !$token->sinceStartOfStatement()->hasOneOf(\T_VARIABLE)))) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace around operators in union, intersection and
            // DNF types
            if ($this->Idx->TypeDelimiter[$token->id]
                && (($inTypeContext = $this->inTypeContext($token))
                    || ($token->id === \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
                        && $token->Parent
                        && $token->Parent->id === \T_OPEN_PARENTHESIS
                        && (($token->Parent->PrevCode
                                && $token->Parent->PrevCode->id === \T_OR)
                            || $token->Parent->ClosedBy->NextCode->id === \T_OR)
                        && $this->inTypeContext($token->Parent)))) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;

                if ($inTypeContext) {
                    continue;
                }

                // Add a leading space to DNF types with opening parentheses
                // (e.g. `(A&B)|null`)
                $parent = $token->Parent;
                if (!$parent->PrevCode || $parent->PrevCode->id !== \T_OR) {
                    $parent->WhitespaceBefore |= WhitespaceType::SPACE;
                }
                continue;
            }

            // Suppress whitespace around exception delimiters in `catch` blocks
            // (unless in strict PSR-12 mode)
            if ($token->id === \T_OR
                    && $token->Parent
                    && $token->Parent->PrevCode
                    && $token->Parent->PrevCode->id === \T_CATCH
                    && !$this->Formatter->Psr12) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace after `?` in nullable types
            if (
                $token->id === \T_QUESTION
                && !($token->Flags & TokenFlag::TERNARY_OPERATOR)
            ) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace between `++` and `--` and the variables they
            // operate on
            if ($token->id === \T_INC || $token->id === \T_DEC) {
                if ($token->Prev && $this->Idx->VariableEnd[$token->Prev->id]) {
                    $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                } else {
                    $token->WhitespaceMaskNext = WhitespaceType::NONE;
                }
            }

            // Suppress whitespace after unary operators
            if ($token->isUnaryOperator()
                && $token->Next
                && $token->Next->Flags & TokenFlag::CODE
                && (!$this->Idx->Operator[$token->Next->id]
                    || $token->Next->isUnaryOperator())) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;

                continue;
            }

            $token->WhitespaceAfter |= WhitespaceType::SPACE;

            if (
                $token->id === \T_COLON
                && !($token->Flags & TokenFlag::TERNARY_OPERATOR)
            ) {
                continue;
            }

            // Collapse ternary operators with nothing between `?` and `:`
            if (
                ($token->Flags & TokenFlag::TERNARY_OPERATOR)
                && ($token->id === \T_QUESTION
                    ? $token
                    : $token->Data[TokenData::OTHER_TERNARY_OPERATOR]) === $token->Prev
            ) {
                $token->WhitespaceBefore = WhitespaceType::NONE;
                $token->Prev->WhitespaceAfter = WhitespaceType::NONE;

                continue;
            }

            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        }
    }

    /**
     * Check if the token is part of a declaration (i.e. a property type or
     * function return type), parameter type, or arrow function return type
     */
    private function inTypeContext(Token $token): bool
    {
        return $token->inDeclaration()
            || ($token->inParameterList()
                && !$token->sinceStartOfStatement()->hasOneOf(\T_VARIABLE))
            || (($prev = $token->prevCodeWhile($this->Idx->ValueType)->last())
                && ($prev = $prev->PrevCode)
                && $prev->id === \T_COLON
                && ($prev = $prev->PrevSibling)
                && ($prev = $prev->PrevSibling)
                && $prev->skipPrevSiblingsFrom($this->Idx->Ampersand)->id === \T_FN);
    }
}
