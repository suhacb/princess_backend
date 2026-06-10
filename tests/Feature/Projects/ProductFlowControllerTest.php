<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductDependency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFlowControllerTest extends TestCase
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

    private function flowUrl(): string
    {
        return "/api/projects/{$this->project->id}/product-flow";
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ], $overrides));
    }

    public function test_show_returns_products_and_dependencies(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);
        $b = $this->makeProduct(['identifier' => 'P02']);
        ProductDependency::create([
            'project_id'     => $this->project->id,
            'predecessor_id' => $a->id,
            'successor_id'   => $b->id,
        ]);

        $response = $this->getJson($this->flowUrl())->assertOk();

        $this->assertCount(2, $response->json('products'));
        $this->assertCount(1, $response->json('dependencies'));
        $this->assertEquals($a->id, $response->json('dependencies.0.predecessor_id'));
    }

    public function test_store_adds_dependency(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);
        $b = $this->makeProduct(['identifier' => 'P02']);

        $this->postJson($this->flowUrl(), [
            'predecessor_id' => $a->id,
            'successor_id'   => $b->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('product_dependencies', [
            'project_id'     => $this->project->id,
            'predecessor_id' => $a->id,
            'successor_id'   => $b->id,
        ]);
    }

    public function test_self_reference_rejected(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);

        $this->postJson($this->flowUrl(), [
            'predecessor_id' => $a->id,
            'successor_id'   => $a->id,
        ])->assertUnprocessable();
    }

    public function test_cycle_rejected(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);
        $b = $this->makeProduct(['identifier' => 'P02']);
        $c = $this->makeProduct(['identifier' => 'P03']);

        // A → B → C already exists
        ProductDependency::create(['project_id' => $this->project->id, 'predecessor_id' => $a->id, 'successor_id' => $b->id]);
        ProductDependency::create(['project_id' => $this->project->id, 'predecessor_id' => $b->id, 'successor_id' => $c->id]);

        // Adding C → A would create a cycle
        $this->postJson($this->flowUrl(), [
            'predecessor_id' => $c->id,
            'successor_id'   => $a->id,
        ])->assertUnprocessable();
    }

    public function test_products_from_different_project_rejected(): void
    {
        $a            = $this->makeProduct(['identifier' => 'P01']);
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $b            = Product::factory()->create([
            'project_id' => $otherProject->id,
            'created_by' => $this->person->id,
            'identifier' => 'P01',
        ]);

        $this->postJson($this->flowUrl(), [
            'predecessor_id' => $a->id,
            'successor_id'   => $b->id,
        ])->assertUnprocessable();
    }

    public function test_destroy_removes_dependency(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);
        $b = $this->makeProduct(['identifier' => 'P02']);

        $dep = ProductDependency::create([
            'project_id'     => $this->project->id,
            'predecessor_id' => $a->id,
            'successor_id'   => $b->id,
        ]);

        $this->deleteJson("{$this->flowUrl()}/{$dep->id}")->assertNoContent();

        $this->assertDatabaseMissing('product_dependencies', ['id' => $dep->id]);
    }

    public function test_non_member_gets_403(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->getJson($this->flowUrl())
            ->assertForbidden();
    }

    public function test_observer_cannot_add_dependency(): void
    {
        $a = $this->makeProduct(['identifier' => 'P01']);
        $b = $this->makeProduct(['identifier' => 'P02']);

        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create([
            'person_id' => $observerPerson->id,
            'role'      => ProjectRole::Observer->value,
        ]);

        $this->actingAs($observer)
            ->postJson($this->flowUrl(), [
                'predecessor_id' => $a->id,
                'successor_id'   => $b->id,
            ])->assertForbidden();
    }
}
