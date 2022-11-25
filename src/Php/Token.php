<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

class Token implements JsonSerializable
{
    /**
     * @var int
     */
    public $Index;

    /**
     * @var int|string
     */
    public $Type;

    /**
     * @var string
     */
    public $Code;

    /**
     * @var int
     */
    public $Line;

    /**
     * @var Token[]
     */
    public $BracketStack;

    /**
     * @var string
     */
    public $TypeName;

    /**
     * @var Token|null
     */
    public $OpenedBy;

    /**
     * @var Token|null
     */
    public $ClosedBy;

    /**
     * @var string[]
     */
    public $Tags = [];

    /**
     * @var int
     */
    public $Indent = 0;

    /**
     * @var int
     */
    public $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * @var int
     */
    public $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * @var int
     */
    public $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * @var int
     */
    public $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * @var Formatter
     */
    private $Formatter;

    /**
     * @var Token|null
     */
    private $_prev;

    /**
     * @var Token|null
     */
    private $_next;

    /**
     * @param array|string $token
     * @param Token[] $bracketStack
     */
    public function __construct(int $index, $token, ?Token $prev, array $bracketStack, Formatter $formatter)
    {
        if (is_array($token))
        {
            list ($this->Type, $this->Code, $this->Line) = $token;
            if ($this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS))
            {
                $code = rtrim($this->Code);
            }
            elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS))
            {
                $code = ltrim($this->Code);
            }
            elseif (!$this->isOneOf(...TokenType::DO_NOT_MODIFY))
            {
                $code = trim($this->Code);
            }
            if (isset($code) && $code !== $this->Code)
            {
                $this->Code   = $code;
                $this->Tags[] = "trimmed";
            }
        }
        else
        {
            $this->Type = $this->Code = $token;

            // To get the original line number, add the last known line number
            // to the number of newlines since, using `$formatter->PlainTokens`
            // in case there was whitespace between `$prev` and `$this`
            $lastLine = 1;
            $code     = "";
            $i        = $index;

            while ($i--)
            {
                $plain = $formatter->PlainTokens[$i];
                $code  = ($plain[1] ?? $plain) . $code;
                if (is_array($plain))
                {
                    $lastLine = $plain[2];
                    break;
                }
            }

            $this->Line = $lastLine + substr_count($code, "\n");
        }

        $this->Index        = $index;
        $this->BracketStack = $bracketStack;
        $this->TypeName     = is_int($this->Type) ? token_name($this->Type) : $this->Type;
        $this->Formatter    = $formatter;

        if ($prev)
        {
            $this->_prev        = $prev;
            $this->_prev->_next = $this;
        }
    }

    public function jsonSerialize(): array
    {
        $a = get_object_vars($this);
        foreach ($a["BracketStack"] as &$t)
        {
            $t = $t->Index;
        }
        $a["OpenedBy"] = $a["OpenedBy"]->Index ?? null;
        $a["ClosedBy"] = $a["ClosedBy"]->Index ?? null;
        $a["_prev"]    = $a["_prev"]->Index ?? null;
        $a["_next"]    = $a["_next"]->Index ?? null;
        if (empty($a["Tags"]))
        {
            unset($a["Tags"]);
        }
        return $a;
    }

    public function wasFirstOnLine(): bool
    {
        $prev = $this->prev();

        return $prev->isNull() || $this->Line > $prev->Line;
    }

    public function wasLastOnLine(): bool
    {
        $next = $this->next();
        if ($next->isNull() || $this->Line < $next->Line)
        {
            return true;
        }
        if (!$next->startsNewStatement())
        {
            return false;
        }
        $next = $this->next(2);

        return $next->isNull() || $this->Line < $next->Line;
    }

    public function wasBetweenTokensOnLine(): bool
    {
        return !$this->wasFirstOnLine() && !$this->wasLastOnLine();
    }

    public function prev(int $offset = 1): Token
    {
        $prev = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $prev = $prev->_prev ?? null;
        }

        return ($prev ?: new NullToken());
    }

    public function next(int $offset = 1): Token
    {
        $next = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $next = $next->_next ?? null;
        }

        return ($next ?: new NullToken());
    }

    public function parent(): Token
    {
        return (end($this->BracketStack) ?: new NullToken());
    }

    public function isNull(): bool
    {
        return false;
    }

    public function outer(): TokenCollection
    {
        $current = $this->OpenedBy ?: $this;
        $last    = $this->ClosedBy ?: $this;

        return $this->collect($current, $last);
    }

    public function startOfStatement(): Token
    {
        $current = $this;
        while (!$current->prev()->startsNewStatement() && !$current->prev()->isNull())
        {
            if ($current->OpenedBy)
            {
                $current = $current->OpenedBy;
                continue;
            }
            $current = $current->prev();
        }

        return $current;
    }

    public function sinceLastStatement(): TokenCollection
    {
        return $this->collect($this->startOfStatement(), $this);
    }

    /**
     * @todo Reimplement after building keyword token list
     */
    public function wordsSinceLastStatement(): TokenCollection
    {
        $tokens = new TokenCollection();
        /** @var Token $token */
        foreach ($this->sinceLastStatement() as $token)
        {
            if ($token->isOpenBracket())
            {
                break;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    public function effectiveWhitespaceBefore(): int
    {
        return ($this->WhitespaceBefore & $this->prev()->WhitespaceMaskNext) | ($this->prev()->WhitespaceAfter & $this->WhitespaceMaskPrev);
    }

    public function effectiveWhitespaceAfter(): int
    {
        return ($this->WhitespaceAfter & $this->next()->WhitespaceMaskPrev) | ($this->next()->WhitespaceBefore & $this->WhitespaceMaskNext);
    }

    public function hasNewlineBefore(): bool
    {
        return (bool)($this->effectiveWhitespaceBefore() & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool)($this->effectiveWhitespaceAfter() & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewLineBeforeOrAfter(): bool
    {
        return $this->hasNewlineBefore() || $this->hasNewlineAfter();
    }

    public function hasWhitespaceBefore(): bool
    {
        return (bool)$this->effectiveWhitespaceBefore();
    }

    public function hasWhitespaceAfter(): bool
    {
        return (bool)$this->effectiveWhitespaceAfter();
    }

    public function hasNewline(): bool
    {
        return strpos($this->Code, "\n") !== false;
    }

    /**
     * @param int|string $type
     */
    public function is($type): bool
    {
        return $this->Type === $type;
    }

    /**
     * @param int|string ...$types
     */
    public function isOneOf(...$types): bool
    {
        return in_array($this->Type, $types, true);
    }

    public function startsNewStatement(): bool
    {
        return $this->isOneOf(";", "{", "}", T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO);
    }

    public function isOpenBracket(): bool
    {
        return $this->isOneOf("(", "[", "{", T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES);
    }

    public function isCloseBracket(): bool
    {
        return $this->isOneOf(")", "]", "}");
    }

    public function isOneLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->isOneOf(...TokenType::COMMENT) && !$this->hasNewline()
            : $this->is(T_COMMENT) && preg_match('@^(//|#)@', $this->Code);
    }

    public function isMultiLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->isOneOf(...TokenType::COMMENT) && $this->hasNewline()
            : ($this->is(T_DOC_COMMENT) ||
                ($this->is(T_COMMENT) && preg_match('@^/\*@', $this->Code)));
    }

    public function isOperator()
    {
        // TODO: return false if part of a declaration

        // OPERATOR_EXECUTION is excluded because for formatting purposes,
        // commands between backticks are equivalent to double-quoted strings
        return $this->isOneOf(
            ...TokenType::OPERATOR_ARITHMETIC,
            ...TokenType::OPERATOR_ASSIGNMENT,
            ...TokenType::OPERATOR_BITWISE,
            ...TokenType::OPERATOR_COMPARISON,
            ...TokenType::OPERATOR_TERNARY,
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT,
            ...TokenType::OPERATOR_LOGICAL,
            ...TokenType::OPERATOR_STRING,
            ...TokenType::OPERATOR_INSTANCEOF
        );

    }

    public function isUnaryOperator(): bool
    {
        if ($this->isOneOf(
            "~", "!",
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
        ))
        {
            return true;
        }

        // TODO: check if this is a unary "+" or "-", e.g. "$a = -$b"

        return false;
    }

    public function isTernaryOperator(): bool
    {
        return $this->isOneOf(...TokenType::OPERATOR_TERNARY);
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isDeclaration(): bool
    {
        return $this->wordsSinceLastStatement()->hasTokenWithTypeInList(...TokenType::DECLARATION);
    }

    public function indent(): string
    {
        return $this->Indent
            ? str_repeat($this->Formatter->Tab, $this->Indent)
            : "";
    }

    public function render(): string
    {
        if ($this->isOneOf(...TokenType::DO_NOT_MODIFY))
        {
            return $this->Code;
        }

        if (!$this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS))
        {
            $code = WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore());
            if (substr($code, -1) === "\n" && $this->Indent)
            {
                $code .= $this->indent();
            }
        }

        $code = ($code ?? "") . ($this->isMultiLineComment(true)
            ? $this->renderComment()
            : $this->Code);

        if ((is_null($this->_next) || $this->next()->isOneOf(...TokenType::DO_NOT_MODIFY)) &&
            !$this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS))
        {
            $code .= WhitespaceType::toWhitespace($this->WhitespaceAfter);
        }

        return $code;
    }

    private function renderComment(): string
    {
        // Remove trailing whitespace from each line
        $code = preg_replace('/\h+$/m', "", $this->Code);
        switch ($this->Type)
        {
            case T_DOC_COMMENT:
                $indent = "\n" . $this->indent();
                return preg_replace([
                    '/\n\h*(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
                    '/\n\h*\*?$/m',
                    '/\n\h*\*\//',
                ], [
                    $indent . " * ",
                    $indent . " *",
                    $indent . " */",
                ], $code);

            case T_COMMENT:
                return $code;
        }

        throw new RuntimeException("Not a T_COMMENT or T_DOC_COMMENT");
    }

    private function collect(Token $from, Token $to): TokenCollection
    {
        $tokens = new TokenCollection();

        $tokens[] = $from;
        while ($from !== $to)
        {
            $tokens[] = $from = $from->next();
        }

        return $tokens;
    }

}
