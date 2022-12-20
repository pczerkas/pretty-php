<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

class ReportUnnecessaryParentheses extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->is('(') ||
            !($token->isStartOfExpression() ||
                (($start = $token->prevCode())->isStartOfExpression() &&
                    $start->isOneOf(...TokenType::HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES))) ||
            $token->endOfExpression() !== $token->ClosedBy) {
            return;
        }
        $start = $start ?? $token;
        $inner = $token->inner();
        if (!count($inner)) {
            return;
        }
        /** @var Token $first */
        $first = $inner->first();
        $last  = $inner->last();
        if (!$first->isStartOfExpression() ||
                $first->endOfExpression() !== $last) {
            return;
        }
        $prev = $start->prevCode();
        $next = $token->ClosedBy->nextCode();
        if (!(($prev->isStatementPrecursor() || $prev->isOneOf(...TokenType::OPERATOR_ASSIGNMENT)) &&
                ($prev->ClosedBy === $next || $next->isStatementPrecursor()))) {
            return;
        }
        $this->Formatter->reportProblem('Unnecessary parentheses', $first, $last);
    }
}
