<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\QualityMethod;
use App\Models\Person;
use App\Models\Project;
use App\Models\QualityRegisterEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityRegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyFrontend::class);

        $this->person = Person::factory()->create();
        $this->user   = User::factory()->create(['person_id' => $this->person->id]);
        $this->actingAs($this->user);

        $this->project = Project::factory()->create(['created_by' => $this->person->id]);
        $this->project->members()->create([
            'person_id' => $this->person->id,
            'role'      => ProjectRole::ProjectManager->value,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/quality-register";
    }

    private function entryUrl(QualityRegisterEntry $entry): string
    {
        return "/api/projects/{$this->project->id}/quality-register/{$entry->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_name'   => 'Project Initiation Document',
            'quality_method' => QualityMethod::Review->value,
            'planned_date'   => '2026-07-01',
        ], $overrides);
    }

    public function test_index_lists_entries(): void
    {
        QualityRegisterEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
        ]);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->indexUrl())
            ->assertForbidden();
    }

    public function test_store_creates_entry(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.product_name', 'Project Initiation Document')
            ->assertJsonPath('data.quality_method', QualityMethod::Review->value);

        $this->assertDatabaseHas('quality_register_entries', [
            'project_id'   => $this->project->id,
            'product_name' => 'Project Initiation Document',
        ]);
    }

    public function test_store_forbidden_for_executive(): void
    {
        $execPerson = Person::factory()->create();
        $exec       = User::factory()->create(['person_id' => $execPerson->id]);
        $this->project->members()->create([
            'person_id' => $execPerson->id,
            'role'      => ProjectRole::Executive->value,
        ]);

        $this->actingAs($exec)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_senior_user_can_create_quality_entry(): void
    {
        $suPerson = Person::factory()->create();
        $su       = User::factory()->create(['person_id' => $suPerson->id]);
        $this->project->members()->create([
            'person_id' => $suPerson->id,
            'role'      => ProjectRole::SeniorUser->value,
        ]);

        $this->actingAs($su)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated();
    }

    public function test_show_returns_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->getJson($this->entryUrl($entry))
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    public function test_update_edits_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['product_name' => 'Updated Product'])
            ->assertOk()
            ->assertJsonPath('data.product_name', 'Updated Product');
    }

    public function test_destroy_deletes_entry(): void
    {
        $entry = QualityRegisterEntry::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->deleteJson($this->entryUrl($entry))->assertNoContent();

        $this->assertDatabaseMissing('quality_register_entries', ['id' => $entry->id]);
    }
}
