<?php

namespace App\Contracts;

interface AuthGatewayClientContract
{
    public function ping(): bool;
}
