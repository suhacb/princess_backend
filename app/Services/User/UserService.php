<?php

namespace App\Services\User;

use App\Classes\Auth\TokenParser;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserService
{
    public function __construct(private readonly TokenParser $parser) {}

    public function handleUserFromToken(string $token): User
    {
        $claims = $this->parser->parse($token);

        if (empty($claims['sub'])) {
            throw new InvalidArgumentException('Token missing required claim: sub');
        }
        if (empty($claims['email'])) {
            throw new InvalidArgumentException('Token missing required claim: email');
        }
        if (empty($claims['preferred_username'])) {
            throw new InvalidArgumentException('Token missing required claim: preferred_username');
        }
        if (empty($claims['name'])) {
            throw new InvalidArgumentException('Token missing required claim: name');
        }

        return DB::transaction(function () use ($claims) {
            $user = User::firstOrCreate(
                ['external_id' => $claims['sub']],
                [
                    'username' => $claims['preferred_username'],
                    'email'    => $claims['email'],
                    'name'     => $claims['name'],
                    'fname'    => $claims['given_name'] ?? null,
                    'lname'    => $claims['family_name'] ?? null,
                ]
            );

            if (is_null($user->person_id)) {
                $person = Person::firstOrCreate(
                    ['email' => $claims['email']],
                    ['name'  => $claims['name']]
                );
                $user->update(['person_id' => $person->id]);
            }

            return $user;
        }, 3);
    }
}
