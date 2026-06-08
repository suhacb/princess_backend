<?php

namespace App\Services\User;

use App\Classes\Auth\TokenParser;
use App\Enums\PersonSide;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserService
{
    private const KNOWN_ROLES = [
        'executive',
        'senior_user',
        'senior_supplier',
        'project_manager',
        'project_assurance',
        'project_support',
        'change_authority',
        'team_manager',
        'team_member',
        'observer',
    ];

    private const GROUP_SIDE_MAP = [
        '/customer' => PersonSide::Customer,
        '/supplier' => PersonSide::Supplier,
        '/neutral'  => PersonSide::Neutral,
    ];

    public function __construct(private readonly TokenParser $parser) {}

    private function resolveSide(array $groups): ?PersonSide
    {
        foreach ($groups as $group) {
            if (isset(self::GROUP_SIDE_MAP[$group])) {
                return self::GROUP_SIDE_MAP[$group];
            }
        }

        return null;
    }

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

        $user = DB::transaction(function () use ($claims) {
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

            $side = $this->resolveSide($claims['groups'] ?? []);
            if ($side !== null) {
                $user->person->update(['side' => $side]);
            }

            return $user;
        }, 3);

        $roles = collect($claims['realm_access']['roles'] ?? [])
            ->intersect(self::KNOWN_ROLES)
            ->values()
            ->all();

        $user->syncRoles($roles);

        return $user;
    }
}
