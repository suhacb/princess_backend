<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\AcceptanceCriterion;
use App\Models\Person;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestScenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as BaseTestCase;

class TestCaseControllerTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private TestScenario $scenario;

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

        $this->scenario = TestScenario::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);
    }

    private function indexUrl(): string
    {
        return "/api/projects/{$this->project->id}/test-scenarios/{$this->scenario->id}/test-cases";
    }

    private function testCaseUrl(TestCase $tc): string
    {
        return "/api/projects/{$this->project->id}/test-scenarios/{$this->scenario->id}/test-cases/{$tc->id}";
    }

    private function makeTestCase(array $attributes = []): TestCase
    {
        return TestCase::factory()->create(array_merge([
            'test_scenario_id' => $this->scenario->id,
            'project_id'       => $this->project->id,
            'created_by'       => $this->person->id,
        ], $attributes));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'           => 'Navigate to login page',
            'steps'           => ['Open browser', 'Go to /login', 'Verify form is visible'],
            'expected_result' => 'Login form is displayed with username and password fields.',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_test_cases(): void
    {
        $this->makeTestCase();
        $this->makeTestCase();

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

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_test_case(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())
            ->assertCreated()
            ->assertJsonPath('data.ref', 'TC-001')
            ->assertJsonPath('data.title', 'Navigate to login page')
            ->assertJsonPath('data.steps', ['Open browser', 'Go to /login', 'Verify form is visible'])
            ->assertJsonPath('data.expected_result', 'Login form is displayed with username and password fields.');

        $this->assertDatabaseHas('test_cases', [
            'test_scenario_id' => $this->scenario->id,
            'project_id'       => $this->project->id,
            'ref'              => 'TC-001',
            'created_by'       => $this->person->id,
        ]);
    }

    public function test_store_ref_increments_per_project(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload())->assertCreated()->assertJsonPath('data.ref', 'TC-001');
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => 'Second']))->assertCreated()->assertJsonPath('data.ref', 'TC-002');
    }

    public function test_store_forbidden_for_read_only_role(): void
    {
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->postJson($this->indexUrl(), $this->storePayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store – validation
    // -------------------------------------------------------------------------

    public function test_store_requires_title(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_requires_steps(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['steps' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('steps');
    }

    public function test_store_rejects_empty_steps_array(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['steps' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('steps');
    }

    public function test_store_rejects_non_array_steps(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['steps' => 'just a string']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('steps');
    }

    public function test_store_requires_expected_result(): void
    {
        $this->postJson($this->indexUrl(), $this->storePayload(['expected_result' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_result');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_test_case(): void
    {
        $tc = $this->makeTestCase();

        $this->getJson($this->testCaseUrl($tc))
            ->assertOk()
            ->assertJsonPath('data.id', $tc->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $tc       = $this->makeTestCase();
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->testCaseUrl($tc))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_edits_test_case(): void
    {
        $tc = $this->makeTestCase();

        $this->putJson($this->testCaseUrl($tc), [
            'title' => 'Updated title',
            'steps' => ['Step A', 'Step B'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.steps', ['Step A', 'Step B']);
    }

    public function test_update_forbidden_for_read_only_role(): void
    {
        $tc             = $this->makeTestCase();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->putJson($this->testCaseUrl($tc), ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_test_case(): void
    {
        $tc = $this->makeTestCase();

        $this->deleteJson($this->testCaseUrl($tc))->assertNoContent();
        $this->assertSoftDeleted('test_cases', ['id' => $tc->id]);
    }

    public function test_destroy_forbidden_for_read_only_role(): void
    {
        $tc             = $this->makeTestCase();
        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->deleteJson($this->testCaseUrl($tc))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // test case scoped to scenario
    // -------------------------------------------------------------------------

    public function test_cannot_access_test_case_from_another_scenario(): void
    {
        $otherScenario = TestScenario::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $tc = TestCase::factory()->create([
            'test_scenario_id' => $otherScenario->id,
            'project_id'       => $this->project->id,
            'created_by'       => $this->person->id,
        ]);

        // Accessing via wrong scenario should 404 (scoped binding)
        $this->getJson("/api/projects/{$this->project->id}/test-scenarios/{$this->scenario->id}/test-cases/{$tc->id}")
            ->assertNotFound();
    }
}
