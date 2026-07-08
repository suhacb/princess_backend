<?php

namespace App\Exceptions\PromptTemplate;

use InvalidArgumentException;

class MissingPlaceholderException extends InvalidArgumentException
{
    /**
     * @param array<int, string> $missing
     */
    public function __construct(array $missing)
    {
        parent::__construct('Missing required placeholder(s): '.implode(', ', $missing).'.');
    }
}
