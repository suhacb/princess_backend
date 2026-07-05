<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RequirementPriority;
use App\Enums\RequirementType;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementVersionControllerTest extends TestCase
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

    private function versionsUrl(Requirement $requirement): string
    {
        return "/api/projects/{$this->project->id}/requirements/{$requirement->id}/versions";
    }

    private function requirementUrl(Requirement $requirement): string
    {
        return "/api/projects/{$this->project->id}/requirements/{$requirement->id}";
    }

    private function makeRequirement(array $attributes = []): Requirement
    {
        return Requirement::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $attributes));
    }

    public function test_lists_version_history_newest_first(): void
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/requirements", [
            'type'     => RequirementType::Classic->value,
            'title'    => 'v1 title',
            'priority' => RequirementPriority::Must->value,
        ])->assertCreated();

        $req = Requirement::find($response->json('data.id'));

        $this->putJson($this->requirementUrl($req), ['title' => 'v2 title'])->assertOk();
        $this->putJson($this->requirementUrl($req->fresh()), ['title' => 'v3 title'])->assertOk();

        $this->getJson($this->versionsUrl($req))
            ->assertOk()
            ->assertJsonPath('data.0.version_number', 3)
            ->assertJsonPath('data.0.title', 'v3 title')
            ->assertJsonPath('data.1.version_number', 2)
            ->assertJsonPath('data.2.version_number', 1)
            ->assertJsonPath('data.2.title', 'v1 title');
    }

    public function test_is_paginated(): void
    {
        $req = $this->makeRequirement();

        $this->getJson($this->versionsUrl($req))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_forbidden_for_non_member(): void
    {
        $req      = $this->makeRequirement();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->versionsUrl($req))
            ->assertForbidden();
    }

    public function test_allowed_for_read_only_role(): void
    {
        $req            = $this->makeRequirement();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->getJson($this->versionsUrl($req))
            ->assertOk();
    }

    public function test_returns_404_for_requirement_from_another_project(): void
    {
        $other      = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignReq = Requirement::factory()->create(['project_id' => $other->id, 'created_by' => $this->person->id]);

        $this->getJson("/api/projects/{$this->project->id}/requirements/{$foreignReq->id}/versions")
            ->assertNotFound();
    }
}
