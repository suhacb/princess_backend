<?php

namespace Tests\Feature\Projects;

use App\Enums\LessonSource;
use App\Enums\ProjectRole;
use App\Models\Lesson;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/lessons";
    }

    private function lessonUrl(Lesson $lesson): string
    {
        return "/api/projects/{$this->project->id}/lessons/{$lesson->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'category'       => 'planning',
            'description'    => 'Early stakeholder engagement is critical.',
            'recommendation' => 'Include stakeholders from project initiation.',
            'source'         => LessonSource::Retrospective->value,
        ], $overrides);
    }

    public function test_index_lists_lessons(): void
    {
        Lesson::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
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

    public function test_store_creates_lesson(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.category', 'planning')
            ->assertJsonPath('data.source', LessonSource::Retrospective->value);

        $this->assertDatabaseHas('lessons', [
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'category'   => 'planning',
        ]);
    }

    public function test_store_forbidden_for_team_member_without_permission(): void
    {
        $memberPerson = Person::factory()->create();
        $member       = User::factory()->create(['person_id' => $memberPerson->id]);
        $this->project->members()->create([
            'person_id' => $memberPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($member)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_team_member_can_create_lesson(): void
    {
        $memberPerson = Person::factory()->create();
        $member       = User::factory()->create(['person_id' => $memberPerson->id]);
        $this->project->members()->create([
            'person_id' => $memberPerson->id,
            'role'      => ProjectRole::TeamMember->value,
        ]);

        $this->actingAs($member)
            ->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated();
    }

    public function test_show_returns_lesson(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->getJson($this->lessonUrl($lesson))
            ->assertOk()
            ->assertJsonPath('data.id', $lesson->id);
    }

    public function test_update_edits_lesson(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['description' => 'Updated description.'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated description.');
    }

    public function test_destroy_deletes_lesson(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->deleteJson($this->lessonUrl($lesson))->assertNoContent();

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }
}
