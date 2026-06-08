<?php

namespace App\Contracts;

interface QdrantClientContract
{
    public function ping(): bool;
}
