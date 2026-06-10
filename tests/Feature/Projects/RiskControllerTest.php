<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use App\Enums\RiskStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/risks";
    }

    private function riskUrl(Risk $risk): string
    {
        return "/api/projects/{$this->project->id}/risks/{$risk->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'           => 'Supplier delay risk',
            'description'     => 'Key supplier may be delayed.',
            'category'        => 'schedule',
            'probability'     => 3,
            'impact'          => 4,
            'proximity'       => RiskProximity::Near->value,
            'risk_owner'      => $this->person->id,
            'response_type'   => RiskResponseType::Reduce->value,
            'response_action' => 'Identify alternative suppliers.',
        ], $overrides);
    }

    public function test_index_lists_risks(): void
    {
        Risk::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
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

    public function test_store_creates_risk(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Supplier delay risk')
            ->assertJsonPath('data.status', RiskStatus::Open->value);

        $this->assertDatabaseHas('risks', [
            'project_id' => $this->project->id,
            'title'      => 'Supplier delay risk',
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

    public function test_show_returns_risk(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->getJson($this->riskUrl($risk))
            ->assertOk()
            ->assertJsonPath('data.id', $risk->id);
    }

    public function test_update_edits_risk(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['title' => 'Updated risk title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated risk title');
    }

    public function test_destroy_deletes_risk(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->deleteJson($this->riskUrl($risk))->assertNoContent();

        $this->assertDatabaseMissing('risks', ['id' => $risk->id]);
    }
}
