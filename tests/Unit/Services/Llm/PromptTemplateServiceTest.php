<?php

namespace Tests\Unit\Services\Llm;

use App\Exceptions\PromptTemplate\MissingPlaceholderException;
use App\Models\Person;
use App\Models\PromptTemplate;
use App\Services\Llm\PromptTemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptTemplateService $service;
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromptTemplateService();
        $this->person  = Person::factory()->create();
    }

    public function test_create_version_starts_at_version_one(): void
    {
        $template = $this->service->createVersion('greeting', 'Hi {{name}}', $this->person->id);

        $this->assertSame(1, $template->version);
        $this->assertTrue($template->active);
    }

    public function test_create_version_bumps_version_and_deactivates_previous(): void
    {
        $v1 = $this->service->createVersion('greeting', 'Hi {{name}}', $this->person->id);
        $v2 = $this->service->createVersion('greeting', 'Hello {{name}}', $this->person->id);

        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->active);
        $this->assertFalse($v1->fresh()->active);
    }

    public function test_resolve_without_version_returns_active_template(): void
    {
        $this->service->createVersion('greeting', 'Hi {{name}}', $this->person->id);
        $v2 = $this->service->createVersion('greeting', 'Hello {{name}}', $this->person->id);

        $resolved = $this->service->resolve('greeting');

        $this->assertTrue($resolved->is($v2));
    }

    public function test_resolve_with_version_returns_pinned_template_even_if_inactive(): void
    {
        $v1 = $this->service->createVersion('greeting', 'Hi {{name}}', $this->person->id);
        $this->service->createVersion('greeting', 'Hello {{name}}', $this->person->id);

        $resolved = $this->service->resolve('greeting', 1);

        $this->assertTrue($resolved->is($v1));
    }

    public function test_resolve_throws_when_template_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->resolve('missing');
    }

    public function test_render_substitutes_placeholders(): void
    {
        $template = PromptTemplate::factory()->create([
            'body'       => 'Hi {{name}}, welcome to {{project}}.',
            'created_by' => $this->person->id,
        ]);

        $result = $this->service->render($template, ['name' => 'Ada', 'project' => 'Princess']);

        $this->assertSame('Hi Ada, welcome to Princess.', $result);
    }

    public function test_render_throws_when_placeholder_missing_from_context(): void
    {
        $template = PromptTemplate::factory()->create([
            'body'       => 'Hi {{name}}, welcome to {{project}}.',
            'created_by' => $this->person->id,
        ]);

        $this->expectException(MissingPlaceholderException::class);
        $this->expectExceptionMessage('project');

        $this->service->render($template, ['name' => 'Ada']);
    }
}
