<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token data types
 *
 * @api
 */
interface TokenData
{
    /**
     * The content of a normalised T_COMMENT or T_DOC_COMMENT after removal of
     * delimiters, trailing whitespace and leading asterisks
     */
    public const COMMENT_CONTENT = 0;

    /**
     * The number of items associated with a LIST_PARENT token
     */
    public const LIST_ITEM_COUNT = 1;

    /**
     * The LIST_PARENT of the first token in a list item
     */
    public const LIST_PARENT = 2;

    /**
     * The T_COLON or T_QUESTION associated with a T_QUESTION or T_COLON flagged
     * as a TERNARY_OPERATOR
     */
    public const OTHER_TERNARY_OPERATOR = 3;

    /**
     * The first T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR in a chain
     * thereof
     */
    public const CHAIN_OPENED_BY = 4;

    /**
     * A collection of tokens that form a NAMED_DECLARATION
     */
    public const NAMED_DECLARATION_PARTS = 5;

    /**
     * The type applied to an open bracket by the HangingIndentation rule
     */
    public const HANGING_INDENT_PARENT_TYPE = 6;
}
