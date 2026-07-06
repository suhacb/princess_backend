<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RequirementType;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceCriterionVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private Requirement $requirement;

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

        $this->requirement = Requirement::factory()->create([
            'project_id' => $this->project->id,
            'type'       => RequirementType::Classic->value,
            'created_by' => $this->person->id,
        ]);
    }

    private function versionsUrl(AcceptanceCriterion $ac): string
    {
        return "/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}/versions";
    }

    private function criterionUrl(AcceptanceCriterion $ac): string
    {
        return "/api/projects/{$this->project->id}/acceptance-criteria/{$ac->id}";
    }

    private function makeCriterion(array $attributes = []): AcceptanceCriterion
    {
        return AcceptanceCriterion::factory()->create(array_merge([
            'project_id'     => $this->project->id,
            'requirement_id' => $this->requirement->id,
            'created_by'     => $this->person->id,
        ], $attributes));
    }

    public function test_lists_version_history_newest_first(): void
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/acceptance-criteria", [
            'requirement_id' => $this->requirement->id,
            'title'          => 'v1 title',
            'description'    => 'v1 description',
        ])->assertCreated();

        $ac = AcceptanceCriterion::find($response->json('data.id'));

        $this->putJson($this->criterionUrl($ac), ['title' => 'v2 title'])->assertOk();
        $this->putJson($this->criterionUrl($ac->fresh()), ['title' => 'v3 title'])->assertOk();

        $this->getJson($this->versionsUrl($ac))
            ->assertOk()
            ->assertJsonPath('data.0.version_number', 3)
            ->assertJsonPath('data.0.title', 'v3 title')
            ->assertJsonPath('data.1.version_number', 2)
            ->assertJsonPath('data.2.version_number', 1)
            ->assertJsonPath('data.2.title', 'v1 title');
    }

    public function test_is_paginated(): void
    {
        $ac = $this->makeCriterion();

        $this->getJson($this->versionsUrl($ac))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_forbidden_for_non_member(): void
    {
        $ac       = $this->makeCriterion();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->versionsUrl($ac))
            ->assertForbidden();
    }

    public function test_allowed_for_read_only_role(): void
    {
        $ac             = $this->makeCriterion();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->getJson($this->versionsUrl($ac))
            ->assertOk();
    }

    public function test_returns_404_for_criterion_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignReq = Requirement::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);
        $foreignAc  = AcceptanceCriterion::factory()->create([
            'project_id'     => $other->id,
            'requirement_id' => $foreignReq->id,
            'created_by'     => $this->person->id,
        ]);

        $this->getJson("/api/projects/{$this->project->id}/acceptance-criteria/{$foreignAc->id}/versions")
            ->assertNotFound();
    }
}
