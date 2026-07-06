<?php

namespace Tests\Feature\Projects;

use App\Enums\LessonSource;
use App\Enums\ProjectRole;
use App\Models\Lesson;
use App\Models\Person;
use App\Models\Project;
use App\Models\Stage;
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

    public function test_store_requires_description(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['description' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_store_requires_source(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['source' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_store_rejects_invalid_source(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['source' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_store_rejects_category_exceeding_max_length(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['category' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_store_accepts_valid_stage_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => $stage->id]))
            ->assertCreated()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_store_rejects_nonexistent_stage_id(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
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

    public function test_update_rejects_null_description(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['description' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_update_rejects_null_source(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['source' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_update_rejects_invalid_source(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['source' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_update_accepts_valid_source(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
            'source'     => LessonSource::Retrospective->value,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['source' => LessonSource::Incident->value])
            ->assertOk()
            ->assertJsonPath('data.source', LessonSource::Incident->value);
    }

    public function test_update_accepts_valid_stage_id(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);
        $stage = Stage::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->person->id]);

        $this->putJson($this->lessonUrl($lesson), ['stage_id' => $stage->id])
            ->assertOk()
            ->assertJsonPath('data.stage_id', $stage->id);
    }

    public function test_update_rejects_nonexistent_stage_id(): void
    {
        $lesson = Lesson::factory()->create([
            'project_id' => $this->project->id,
            'raised_by'  => $this->person->id,
        ]);

        $this->putJson($this->lessonUrl($lesson), ['stage_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
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
