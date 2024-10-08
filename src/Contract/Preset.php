<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Formatter;

/**
 * @api
 */
interface Preset
{
    /**
     * Get a formatter for the preset
     *
     * @param int-mask-of<FormatterFlag::*> $flags
     */
    public static function getFormatter(int $flags = 0): Formatter;
}
