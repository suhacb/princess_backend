<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);
    }

    public function test_index_returns_only_active_version_per_template_by_default(): void
    {
        $old = PromptTemplate::factory()->create(['name' => 'greeting', 'version' => 1, 'active' => false, 'created_by' => $this->person->id]);
        $new = PromptTemplate::factory()->create(['name' => 'greeting', 'version' => 2, 'active' => true, 'created_by' => $this->person->id]);

        $response = $this->getJson('/api/prompt-templates')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($new->id));
        $this->assertFalse($ids->contains($old->id));
    }

    public function test_index_filtered_by_name_returns_all_versions_newest_first(): void
    {
        PromptTemplate::factory()->create(['name' => 'greeting', 'version' => 1, 'active' => false, 'created_by' => $this->person->id]);
        PromptTemplate::factory()->create(['name' => 'greeting', 'version' => 2, 'active' => true, 'created_by' => $this->person->id]);

        $this->getJson('/api/prompt-templates?name=greeting')
            ->assertOk()
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.1.version', 1);
    }

    public function test_store_creates_first_version(): void
    {
        $this->postJson('/api/prompt-templates', [
            'name' => 'greeting',
            'body' => 'Hi {{name}}',
        ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.placeholders', ['name']);
    }

    public function test_store_bumps_version_and_deactivates_previous_when_name_repeats(): void
    {
        $first = $this->postJson('/api/prompt-templates', ['name' => 'greeting', 'body' => 'Hi {{name}}'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/prompt-templates', ['name' => 'greeting', 'body' => 'Hello {{name}}'])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertFalse(PromptTemplate::find($first)->active);
    }

    public function test_store_requires_name_and_body(): void
    {
        $this->postJson('/api/prompt-templates', [])->assertUnprocessable();
    }

    public function test_show_returns_specific_version(): void
    {
        $template = PromptTemplate::factory()->create(['created_by' => $this->person->id]);

        $this->getJson("/api/prompt-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->id);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        auth()->logout();

        $this->postJson('/api/prompt-templates', ['name' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }
}
