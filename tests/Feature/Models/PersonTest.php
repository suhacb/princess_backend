<?php

namespace Tests\Feature\Models;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_person_with_required_fields_only(): void
    {
        $person = Person::create(['name' => 'Jane Doe']);

        $this->assertDatabaseHas('people', ['name' => 'Jane Doe']);
        $this->assertNull($person->email);
    }

    public function test_email_must_be_unique(): void
    {
        Person::factory()->create(['email' => 'jane@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Person::factory()->create(['email' => 'jane@example.com']);
    }

    public function test_soft_delete_does_not_remove_record(): void
    {
        $person = Person::factory()->create();
        $person->delete();

        $this->assertSoftDeleted('people', ['id' => $person->id]);
        $this->assertNotNull(Person::withTrashed()->find($person->id));
    }

    public function test_has_one_user_relationship(): void
    {
        $person = Person::factory()->create();
        $user   = User::factory()->create(['person_id' => $person->id, 'email' => $person->email]);

        $this->assertTrue($person->user->is($user));
    }
}
