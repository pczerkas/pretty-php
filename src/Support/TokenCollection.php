<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\Concept\TypedCollection;
use Lkrms\PrettyPHP\Token\Token;
use LogicException;
use Stringable;

/**
 * A collection of Tokens
 *
 * @extends TypedCollection<int,Token>
 */
final class TokenCollection extends TypedCollection implements Stringable
{
    protected const ITEM_CLASS = Token::class;

    /**
     * @var bool
     */
    private $Collected = false;

    public static function collect(Token $from, Token $to): self
    {
        $tokens = new self();
        $tokens->Collected = true;
        if ($from->Index > $to->Index || $from->IsNull || $to->IsNull) {
            return $tokens;
        }
        $tokens[] = $from;
        while ($from !== $to && $from->_next) {
            $tokens[] = $from = $from->_next;
        }

        return $tokens;
    }

    public function hasOneOf(int ...$types): bool
    {
        return $this->find(
            fn(Token $t) => $t->is($types)
        ) !== false;
    }

    public function getAnyOf(int ...$types): self
    {
        return $this->filter(
            fn(Token $t) => $t->is($types)
        );
    }

    public function getFirstOf(int ...$types): ?Token
    {
        return $this->find(
            fn(Token $t) => $t->is($types)
        ) ?: null;
    }

    public function getLastOf(int ...$types): ?Token
    {
        return $this->reverse()->getFirstOf(...$types);
    }

    /**
     * @return int[]
     */
    public function getTypes(): array
    {
        /** @var Token $token */
        foreach ($this as $token) {
            $types[] = $token->id;
        }

        return $types ?? [];
    }

    /**
     * True if any tokens in the collection are separated by one or more line
     * breaks
     *
     */
    public function hasNewlineBetweenTokens(): bool
    {
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($i++ && $token->hasNewlineBefore()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if any tokens in the collection are separated by a blank line
     *
     */
    public function hasBlankLineBetweenTokens(): bool
    {
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($i++ && $token->hasBlankLineBefore()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the collection will render over multiple lines, not including
     * leading or trailing whitespace
     *
     */
    public function hasNewline(): bool
    {
        $this->assertCollected();

        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if (strpos($token->text, "\n") !== false) {
                return true;
            }
            if ($i++ && $token->hasNewlineBefore()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render tokens in the collection, optionally removing leading
     * whitespace from the first token
     *
     * Leading newlines are always trimmed.
     *
     */
    public function render(bool $softTabs = false, bool $trim = true): string
    {
        $this->assertCollected();

        $first = $this->first();
        $last = $this->last();
        $code = $first->render($softTabs, $last);
        if ($trim) {
            if ($before = $first->renderWhitespaceBefore($softTabs, true)) {
                return substr($code, strlen($before));
            }
            return $code;
        }
        return ltrim($code, "\n");
    }

    public function __toString(): string
    {
        $code = '';

        /** @var Token $token */
        foreach ($this as $token) {
            $code .= $token->text;
        }

        return $code;
    }

    /**
     * @return $this
     */
    public function addWhitespaceBefore(int $type, bool $critical = false)
    {
        if ($critical) {
            /** @var Token $token */
            foreach ($this as $token) {
                $token->CriticalWhitespaceBefore |= $type;
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceBefore |= $type;
            $token->WhitespaceMaskPrev |= $type;
            if ($token->_prev) {
                $token->_prev->WhitespaceMaskNext |= $type;
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function addWhitespaceAfter(int $type, bool $critical = false)
    {
        if ($critical) {
            /** @var Token $token */
            foreach ($this as $token) {
                $token->CriticalWhitespaceAfter |= $type;
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceAfter |= $type;
            $token->WhitespaceMaskNext |= $type;
            if ($token->_next) {
                $token->_next->WhitespaceMaskPrev |= $type;
            }
        }
        return $this;
    }

    /**
     * Use T_AND_EQUAL ('&=') to apply a mask to all WhitespaceMaskPrev and
     * WhitespaceMaskNext values that cover whitespace before tokens in the
     * collection
     *
     * If `$critical` is set, operate on CriticalWhitespaceMaskPrev and
     * CriticalWhitespaceMaskNext instead.
     *
     * @return $this
     */
    public function maskWhitespaceBefore(int $mask, bool $critical = false)
    {
        if ($critical) {
            return $this->forEach(
                function (Token $t) use ($mask) {
                    $t->CriticalWhitespaceMaskPrev &= $mask;
                    $t->prev()->CriticalWhitespaceMaskNext &= $mask;
                }
            );
        }

        return $this->forEach(
            function (Token $t) use ($mask) {
                $t->WhitespaceMaskPrev &= $mask;
                $t->prev()->WhitespaceMaskNext &= $mask;
            }
        );
    }

    /**
     * Use T_AND_EQUAL ('&=') to apply a mask to all inward-facing
     * WhitespaceMaskPrev and WhitespaceMaskNext values in the collection
     *
     * If `$critical` is set, operate on CriticalWhitespaceMaskPrev and
     * CriticalWhitespaceMaskNext instead.
     *
     * @return $this
     */
    public function maskInnerWhitespace(int $mask, bool $critical = false)
    {
        $this->assertCollected();

        switch ($this->count()) {
            case 0:
            case 1:
                return $this;

            default:
                $this->nth(2)
                     ->collect($this->nth(-2))
                     ->forEach(
                         $critical
                             ? function (Token $t) use ($mask) {
                                 $t->CriticalWhitespaceMaskPrev &= $mask;
                                 $t->CriticalWhitespaceMaskNext &= $mask;
                             }
                             : function (Token $t) use ($mask) {
                                 $t->WhitespaceMaskPrev &= $mask;
                                 $t->WhitespaceMaskNext &= $mask;
                             }
                     );
                // No break
            case 2:
                if ($critical) {
                    $this->first()->CriticalWhitespaceMaskNext &= $mask;
                    $this->last()->CriticalWhitespaceMaskPrev &= $mask;
                } else {
                    $this->first()->WhitespaceMaskNext &= $mask;
                    $this->last()->WhitespaceMaskPrev &= $mask;
                }

                return $this;
        }
    }

    private function assertCollected(): void
    {
        if (!$this->Collected) {
            throw new LogicException(sprintf('Not collected by %s::collect()', static::class));
        }
        if ($this->isMutant()) {
            throw new LogicException(sprintf('Modified since collection by %s::collect()', static::class));
        }
    }
}
