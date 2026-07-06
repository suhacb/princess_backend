<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use App\Enums\RiskStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Stage;
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

    public function test_store_fails_when_title_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['title']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_fails_when_title_too_long(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['title' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_fails_when_category_too_long(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['category' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_store_fails_when_probability_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['probability']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('probability');
    }

    public function test_store_fails_when_probability_not_integer(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['probability' => 'high']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('probability');
    }

    public function test_store_fails_when_probability_below_minimum(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['probability' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('probability');
    }

    public function test_store_fails_when_probability_above_maximum(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['probability' => 6]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('probability');
    }

    public function test_store_fails_when_impact_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['impact']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact');
    }

    public function test_store_fails_when_impact_below_minimum(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['impact' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact');
    }

    public function test_store_fails_when_impact_above_maximum(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['impact' => 6]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact');
    }

    public function test_store_fails_when_proximity_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['proximity']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proximity');
    }

    public function test_store_fails_when_proximity_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['proximity' => 'someday']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proximity');
    }

    public function test_store_fails_when_risk_owner_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['risk_owner']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('risk_owner');
    }

    public function test_store_fails_when_risk_owner_does_not_exist(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['risk_owner' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('risk_owner');
    }

    public function test_store_fails_when_response_type_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['response_type']);

        $this->postJson($this->indexUrl(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response_type');
    }

    public function test_store_fails_when_response_type_invalid(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['response_type' => 'ignore']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response_type');
    }

    public function test_store_fails_when_stage_id_does_not_exist(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_store_creates_risk_with_valid_stage_id(): void
    {
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->postJson($this->indexUrl(), $this->validPayload(['stage_id' => $stage->id]))
            ->assertCreated()
            ->assertJsonPath('data.stage_id', $stage->id);
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

    public function test_update_fails_when_title_empty(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_update_fails_when_probability_out_of_range(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['probability' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('probability');
    }

    public function test_update_fails_when_impact_out_of_range(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['impact' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact');
    }

    public function test_update_fails_when_proximity_invalid(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['proximity' => 'later'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proximity');
    }

    public function test_update_fails_when_risk_owner_does_not_exist(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['risk_owner' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('risk_owner');
    }

    public function test_update_fails_when_response_type_invalid(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['response_type' => 'ignore'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response_type');
    }

    public function test_update_fails_when_residual_probability_out_of_range(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['residual_probability' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('residual_probability');
    }

    public function test_update_fails_when_residual_impact_out_of_range(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['residual_impact' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('residual_impact');
    }

    public function test_update_fails_when_status_invalid(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_fails_when_stage_id_does_not_exist(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);

        $this->putJson($this->riskUrl($risk), ['stage_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage_id');
    }

    public function test_update_edits_risk_with_valid_residual_values_and_status(): void
    {
        $risk = Risk::factory()->create([
            'project_id' => $this->project->id,
            'risk_owner' => $this->person->id,
        ]);
        $stage = Stage::factory()->create(['project_id' => $this->project->id]);

        $this->putJson($this->riskUrl($risk), [
            'residual_probability' => 2,
            'residual_impact'      => 2,
            'status'               => RiskStatus::Mitigated->value,
            'stage_id'             => $stage->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.residual_probability', 2)
            ->assertJsonPath('data.residual_impact', 2)
            ->assertJsonPath('data.status', RiskStatus::Mitigated->value)
            ->assertJsonPath('data.stage_id', $stage->id);
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
