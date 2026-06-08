<?php

namespace App\Contracts;

interface GraphClientContract
{
    public function ping(): bool;
}
