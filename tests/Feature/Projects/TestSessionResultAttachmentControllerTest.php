<?php

namespace Tests\Feature\Projects;

use App\Contracts\DocumentStorageDriver;
use App\Enums\ProjectRole;
use App\Enums\TeamType;
use App\Enums\TestResultStatus;
use App\Enums\TestScenarioStatus;
use App\Models\Person;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestScenario;
use App\Models\TestSession;
use App\Models\TestSessionResult;
use App\Models\TestSessionResultAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TestSessionResultAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;
    private Project $project;
    private TestSession $session;
    private TestScenario $scenario;
    private TestCaseModel $testCase;
    private TestSessionResult $result;

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

        $this->session = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'tester_id'  => $this->person->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ]);

        $this->scenario = TestScenario::factory()->testable()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->person->id,
            'status'     => TestScenarioStatus::Ready->value,
        ]);

        $this->testCase = TestCaseModel::factory()->create([
            'test_scenario_id' => $this->scenario->id,
            'project_id'       => $this->project->id,
            'created_by'       => $this->person->id,
            'steps'            => ['Step one', 'Step two'],
        ]);

        $this->result = TestSessionResult::create([
            'test_session_id'  => $this->session->id,
            'test_scenario_id' => $this->scenario->id,
            'test_case_id'     => $this->testCase->id,
            'result'           => TestResultStatus::NotRun->value,
        ]);

        $this->mock(DocumentStorageDriver::class);
    }

    private function uploadUrl(): string
    {
        return "/api/projects/{$this->project->id}/test-sessions/{$this->session->id}"
            . "/results/{$this->scenario->id}/test-cases/{$this->testCase->id}/attachments";
    }

    private function deleteUrl(TestSessionResultAttachment $attachment): string
    {
        return "/api/projects/{$this->project->id}/test-sessions/{$this->session->id}/attachments/{$attachment->id}";
    }

    private function fakeImage(string $name = 'screenshot.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name);
    }

    private function makeAttachment(array $attributes = []): TestSessionResultAttachment
    {
        return TestSessionResultAttachment::factory()->create(array_merge([
            'test_session_result_id' => $this->result->id,
            'created_by'             => $this->person->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_uploads_attachment_to_test_case_result(): void
    {
        $this->mock(DocumentStorageDriver::class)->shouldReceive('put')->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeImage()])
            ->assertCreated()
            ->assertJsonPath('data.step_index', null)
            ->assertJsonPath('data.file_name', 'screenshot.png');

        $this->assertDatabaseHas('test_session_result_attachments', [
            'test_session_result_id' => $this->result->id,
            'step_index'             => null,
            'file_name'              => 'screenshot.png',
            'created_by'             => $this->person->id,
        ]);
    }

    public function test_store_uploads_attachment_to_specific_step(): void
    {
        $this->mock(DocumentStorageDriver::class)->shouldReceive('put')->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeImage(), 'step_index' => 1])
            ->assertCreated()
            ->assertJsonPath('data.step_index', 1);

        $this->assertDatabaseHas('test_session_result_attachments', [
            'test_session_result_id' => $this->result->id,
            'step_index'             => 1,
        ]);
    }

    public function test_store_stores_key_with_uuid_path(): void
    {
        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('put')
            ->withArgs(function (Project $project, string $key) {
                return $project->is($this->project)
                    && preg_match('#^test-session-results/\d+/attachments/[0-9a-f-]{36}\.png$#', $key) === 1;
            })
            ->once();

        $this->post($this->uploadUrl(), ['file' => $this->fakeImage()])->assertCreated();
    }

    public function test_store_rejects_step_index_out_of_range(): void
    {
        $this->post($this->uploadUrl(), ['file' => $this->fakeImage(), 'step_index' => 5])
            ->assertUnprocessable();
    }

    public function test_store_rejects_when_test_case_not_in_session(): void
    {
        $otherCase = TestCaseModel::factory()->create([
            'test_scenario_id' => $this->scenario->id,
            'project_id'       => $this->project->id,
            'created_by'       => $this->person->id,
        ]);

        $url = "/api/projects/{$this->project->id}/test-sessions/{$this->session->id}"
            . "/results/{$this->scenario->id}/test-cases/{$otherCase->id}/attachments";

        $this->post($url, ['file' => $this->fakeImage()])->assertUnprocessable();
    }

    public function test_store_rejects_invalid_mime_type(): void
    {
        $exe = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $this->post($this->uploadUrl(), ['file' => $exe])->assertUnprocessable();
    }

    public function test_store_forbidden_for_non_tester_non_manager(): void
    {
        $otherPerson = Person::factory()->create();
        $otherUser   = User::factory()->create(['person_id' => $otherPerson->id]);
        $this->project->members()->create(['person_id' => $otherPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($otherUser)
            ->post($this->uploadUrl(), ['file' => $this->fakeImage()])
            ->assertForbidden();
    }

    public function test_store_forbidden_for_non_member(): void
    {
        $stranger = User::factory()->create(['person_id' => Person::factory()->create()->id]);

        $this->actingAs($stranger)
            ->post($this->uploadUrl(), ['file' => $this->fakeImage()])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_attachment_and_s3_object(): void
    {
        $attachment = $this->makeAttachment(['s3_key' => 'test-session-results/1/attachments/uuid.png']);

        $this->mock(DocumentStorageDriver::class)
            ->shouldReceive('delete')
            ->withArgs(fn (Project $project, string $key) => $project->is($this->project) && $key === $attachment->s3_key)
            ->once();

        $this->deleteJson($this->deleteUrl($attachment))->assertNoContent();

        $this->assertDatabaseMissing('test_session_result_attachments', ['id' => $attachment->id]);
    }

    public function test_destroy_forbidden_for_read_only_role(): void
    {
        $attachment = $this->makeAttachment();

        $observerPerson = Person::factory()->create();
        $observer       = User::factory()->create(['person_id' => $observerPerson->id]);
        $this->project->members()->create(['person_id' => $observerPerson->id, 'role' => ProjectRole::Observer->value]);

        $this->actingAs($observer)
            ->deleteJson($this->deleteUrl($attachment))
            ->assertForbidden();
    }

    public function test_destroy_returns_404_for_attachment_from_another_session(): void
    {
        $otherSession = TestSession::factory()->create([
            'project_id' => $this->project->id,
            'tester_id'  => $this->person->id,
            'team_type'  => TeamType::Supplier->value,
            'created_by' => $this->person->id,
        ]);
        $attachment = $this->makeAttachment();

        $this->deleteJson("/api/projects/{$this->project->id}/test-sessions/{$otherSession->id}/attachments/{$attachment->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // result resource includes attachments
    // -------------------------------------------------------------------------

    public function test_result_includes_attachments_grouped_by_step(): void
    {
        $this->makeAttachment(['step_index' => null, 'file_name' => 'case-level.png']);
        $this->makeAttachment(['step_index' => 0, 'file_name' => 'step-0.png']);

        $this->putJson(
            "/api/projects/{$this->project->id}/test-sessions/{$this->session->id}"
                . "/results/{$this->scenario->id}/test-cases/{$this->testCase->id}",
            ['result' => 'pass']
        )->assertOk()
            ->assertJsonPath('data.attachments.case.0.file_name', 'case-level.png')
            ->assertJsonPath('data.attachments.0.0.file_name', 'step-0.png');
    }
}
