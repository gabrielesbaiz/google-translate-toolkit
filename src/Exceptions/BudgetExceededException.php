<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

class BudgetExceededException extends GoogleTranslateException
{
    /**
     * Create a new exception for a day that has run out of budget.
     */
    public static function make(int $used, int $requested, int $budget): self
    {
        return new self(sprintf(
            'Daily Google Translate budget exceeded: %s characters already billed today, %s more requested, limit is %s. Raise "budget.max_characters_per_day" or wait for the counter to roll over.',
            number_format($used),
            number_format($requested),
            number_format($budget),
        ));
    }
}
