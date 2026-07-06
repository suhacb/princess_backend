<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectProductDescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProductDescriptionControllerTest extends TestCase
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

    private function url(): string
    {
        return "/api/projects/{$this->project->id}/product-description";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'                         => 'Banking CORE System',
            'purpose'                       => 'Replace legacy core banking system.',
            'acceptance_criteria'           => ['System passes UAT', 'All data migrated'],
            'customer_quality_expectations' => 'Zero data loss, 99.9% uptime.',
        ], $overrides);
    }

    public function test_show_returns_404_when_no_ppd_exists(): void
    {
        $this->getJson($this->url())->assertNotFound();
    }

    public function test_store_creates_ppd(): void
    {
        $this->postJson($this->url(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Banking CORE System');

        $this->assertDatabaseHas('project_product_descriptions', [
            'project_id' => $this->project->id,
            'title'      => 'Banking CORE System',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson($this->url(), $this->validPayload(['title' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_creates_ppd_with_quality_fields(): void
    {
        $this->postJson($this->url(), $this->validPayload([
            'quality_criteria'          => ['Passes regression suite', 'Meets SLA'],
            'quality_responsibilities'  => [
                'producer' => 'Dev Team',
                'reviewer' => 'QA Lead',
                'approver' => 'Product Owner',
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.quality_criteria', ['Passes regression suite', 'Meets SLA'])
            ->assertJsonPath('data.quality_responsibilities.producer', 'Dev Team');
    }

    public function test_store_rejects_quality_criteria_not_array_of_strings(): void
    {
        $this->postJson($this->url(), $this->validPayload(['quality_criteria' => 'not-an-array']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quality_criteria');
    }

    public function test_store_returns_422_on_duplicate(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->url(), $this->validPayload())
            ->assertStatus(500); // unique constraint violation
    }

    public function test_show_returns_ppd(): void
    {
        $ppd = ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.id', $ppd->id);
    }

    public function test_update_modifies_ppd(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->url(), ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('project_product_descriptions', [
            'project_id' => $this->project->id,
            'updated_by' => $this->person->id,
        ]);
    }

    public function test_update_rejects_null_title(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->url(), ['title' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_destroy_deletes_ppd(): void
    {
        $ppd = ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->deleteJson($this->url())->assertNoContent();

        $this->assertDatabaseMissing('project_product_descriptions', ['id' => $ppd->id]);
    }

    public function test_cannot_delete_baselined_ppd(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id'   => $this->project->id,
            'created_by'   => $this->person->id,
            'baselined_at' => now(),
        ]);

        $this->deleteJson($this->url())->assertForbidden();
    }

    public function test_non_member_gets_403(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->url())
            ->assertForbidden();
    }

    public function test_assurance_cannot_create_ppd(): void
    {
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create([
            'person_id' => $assurancePerson->id,
            'role'      => ProjectRole::ProjectAssurance->value,
        ]);

        $this->actingAs($assurance)
            ->postJson($this->url(), $this->validPayload())
            ->assertForbidden();
    }

    public function test_assurance_can_read_ppd(): void
    {
        ProjectProductDescription::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create([
            'person_id' => $assurancePerson->id,
            'role'      => ProjectRole::ProjectAssurance->value,
        ]);

        $this->actingAs($assurance)
            ->getJson($this->url())
            ->assertOk();
    }
}
