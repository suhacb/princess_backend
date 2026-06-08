<?php

namespace App\Contracts;

interface OllamaClientContract
{
    public function ping(): bool;
}
