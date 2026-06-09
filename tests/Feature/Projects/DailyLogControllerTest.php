<?php

namespace Tests\Feature\Projects;

use App\Enums\DailyLogEntryType;
use App\Enums\ProjectRole;
use App\Models\DailyLogEntry;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLogControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/daily-log";
    }

    private function entryUrl(DailyLogEntry $entry): string
    {
        return "/api/projects/{$this->project->id}/daily-log/{$entry->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'date'       => '2026-06-09',
            'entry_type' => DailyLogEntryType::Note->value,
            'body'       => 'Discussed project status with team.',
        ], $overrides);
    }

    public function test_index_lists_entries(): void
    {
        DailyLogEntry::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
        ]);

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonCount(3, 'data');
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
            ->assertJsonPath('data.entry_type', DailyLogEntryType::Note->value)
            ->assertJsonPath('data.body', 'Discussed project status with team.');

        $this->assertDatabaseHas('daily_log_entries', [
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
            'body'       => 'Discussed project status with team.',
        ]);
    }

    public function test_store_forbidden_for_observer(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_show_returns_entry(): void
    {
        $entry = DailyLogEntry::factory()->create([
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
        ]);

        $this->getJson($this->entryUrl($entry))
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    public function test_update_edits_entry(): void
    {
        $entry = DailyLogEntry::factory()->create([
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
        ]);

        $this->putJson($this->entryUrl($entry), ['body' => 'Updated note.'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Updated note.');
    }

    public function test_destroy_deletes_entry(): void
    {
        $entry = DailyLogEntry::factory()->create([
            'project_id' => $this->project->id,
            'author_id'  => $this->person->id,
        ]);

        $this->deleteJson($this->entryUrl($entry))->assertNoContent();

        $this->assertDatabaseMissing('daily_log_entries', ['id' => $entry->id]);
    }
}
