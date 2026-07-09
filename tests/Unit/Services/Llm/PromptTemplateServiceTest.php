<?php

namespace Tests\Unit\Services\Llm;

use App\Exceptions\PromptTemplate\MissingPlaceholderException;
use App\Models\Person;
use App\Models\PromptTemplate;
use App\Services\Llm\PromptTemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_resolve_throws_when_pinned_version_does_not_exist_for_known_name(): void
    {
        $this->service->createVersion('greeting', 'Hi {{name}}', $this->person->id);

        $this->expectException(ModelNotFoundException::class);

        $this->service->resolve('greeting', 99);
    }

    public function test_create_version_holds_a_lock_that_blocks_concurrent_creation_for_the_same_name(): void
    {
        // pg_advisory_xact_lock is a Postgres primitive: it can only be verified by proving a
        // second connection can't acquire the same lock while this transaction holds it, and can
        // once it commits. A single PHP process can't run two overlapping transactions on one
        // connection, so this opens a second real connection to the same database.
        config(['database.connections.pg_lock_test' => config('database.connections.pgsql')]);
        $second = DB::connection('pg_lock_test');

        // Note: this only asserts mutual exclusion while the lock is held — it can't also assert
        // release-on-commit, because RefreshDatabase wraps the whole test in an outer transaction
        // and Postgres only releases an xact-scoped advisory lock on a real top-level commit, not
        // on a nested transaction/savepoint completing.
        DB::transaction(function () use ($second) {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['greeting']);

            $tryLock = $second->selectOne('SELECT pg_try_advisory_xact_lock(hashtext(?)) AS locked', ['greeting']);

            $this->assertFalse(
                $tryLock->locked,
                'A concurrent transaction should not be able to acquire the createVersion() lock for the same name.'
            );
        });

        $second->disconnect();
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

    public function test_render_ignores_context_keys_that_are_not_placeholders(): void
    {
        $template = PromptTemplate::factory()->create([
            'body'       => 'Hi {{name}}.',
            'created_by' => $this->person->id,
        ]);

        $result = $this->service->render($template, ['name' => 'Ada', 'unused' => 'should not appear']);

        $this->assertSame('Hi Ada.', $result);
    }
}
