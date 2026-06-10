<?php

namespace Tests\Feature\Projects;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProjectRole;
use App\Models\Person;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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
        return "/api/projects/{$this->project->id}/products";
    }

    private function productUrl(Product $product): string
    {
        return "/api/projects/{$this->project->id}/products/{$product->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'identifier' => 'P01',
            'title'      => 'Core Banking Platform',
            'type'       => ProductType::Specialist->value,
            'purpose'    => 'The primary system product.',
        ], $overrides);
    }

    public function test_index_lists_products(): void
    {
        Product::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
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

    public function test_store_creates_product(): void
    {
        $this->postJson($this->indexUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Core Banking Platform')
            ->assertJsonPath('data.status', ProductStatus::Draft->value);

        $this->assertDatabaseHas('products', [
            'project_id' => $this->project->id,
            'identifier' => 'P01',
            'created_by' => $this->person->id,
        ]);
    }

    public function test_store_creates_child_product(): void
    {
        $parent = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['parent_id' => $parent->id, 'identifier' => 'P01.1']))
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_store_rejects_parent_from_another_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->person->id]);
        $foreignParent = Product::factory()->create([
            'project_id' => $otherProject->id,
            'created_by' => $this->person->id,
        ]);

        $this->postJson($this->indexUrl(), $this->validPayload(['parent_id' => $foreignParent->id]))
            ->assertUnprocessable();
    }

    public function test_tree_returns_nested_structure(): void
    {
        $root = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'identifier' => 'P01',
        ]);
        Product::factory()->create([
            'project_id' => $this->project->id,
            'parent_id'  => $root->id,
            'created_by' => $this->person->id,
            'identifier' => 'P01.1',
        ]);

        $response = $this->getJson("/api/projects/{$this->project->id}/products/tree")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertCount(1, $response->json('data.0.children'));
    }

    public function test_show_returns_product(): void
    {
        $product = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->getJson($this->productUrl($product))
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_update_edits_product(): void
    {
        $product = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
        ]);

        $this->putJson($this->productUrl($product), ['title' => 'Updated title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_destroy_deletes_draft_product(): void
    {
        $product = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => ProductStatus::Draft->value,
        ]);

        $this->deleteJson($this->productUrl($product))->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_cannot_delete_baselined_product(): void
    {
        $product = Product::factory()->create([
            'project_id'   => $this->project->id,
            'created_by'   => $this->person->id,
            'status'       => ProductStatus::Baselined->value,
            'baselined_at' => now(),
        ]);

        $this->deleteJson($this->productUrl($product))->assertForbidden();
    }

    public function test_baseline_transitions_status(): void
    {
        $product = Product::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => ProductStatus::Draft->value,
        ]);

        $this->postJson("/api/projects/{$this->project->id}/products/{$product->id}/baseline")
            ->assertOk()
            ->assertJsonPath('data.status', ProductStatus::Baselined->value);

        $this->assertDatabaseHas('products', [
            'id'     => $product->id,
            'status' => ProductStatus::Baselined->value,
        ]);
    }

    public function test_observer_cannot_create_product(): void
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

    public function test_assurance_can_create_product(): void
    {
        $assurancePerson = Person::factory()->create();
        $assurance       = User::factory()->create(['person_id' => $assurancePerson->id]);
        $this->project->members()->create([
            'person_id' => $assurancePerson->id,
            'role'      => ProjectRole::ProjectAssurance->value,
        ]);

        $this->actingAs($assurance)
            ->postJson($this->indexUrl(), $this->validPayload(['identifier' => 'P99']))
            ->assertCreated();
    }
}
