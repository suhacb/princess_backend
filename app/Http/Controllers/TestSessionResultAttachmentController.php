<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentStorageDriver;
use App\Http\Requests\TestSession\UploadTestSessionResultAttachmentRequest;
use App\Http\Resources\TestSessionResultAttachmentResource;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestScenario;
use App\Models\TestSession;
use App\Models\TestSessionResultAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * @tags Test Session Result Attachments
 */
class TestSessionResultAttachmentController extends Controller
{
    /**
     * Upload a file attachment (e.g. a screenshot) to a test case's session result,
     * or to a single step within it when step_index is given.
     *
     * @response 201 {"data": {"id": 1, "step_index": null, "file_name": "screenshot.png"}}
     * @response 422 {"message": "This test case is not part of the session."}
     */
    public function store(
        UploadTestSessionResultAttachmentRequest $request,
        Project $project,
        TestSession $testSession,
        TestScenario $testScenario,
        TestCase $testCase,
        DocumentStorageDriver $storage,
    ): TestSessionResultAttachmentResource {
        $this->authorize('updateResult', [TestSession::class, $project, $testSession]);

        $result = $testSession->results()
            ->where('test_scenario_id', $testScenario->id)
            ->where('test_case_id', $testCase->id)
            ->first();

        abort_if(! $result, 422, 'This test case is not part of the session.');

        $stepIndex = $request->input('step_index');

        if ($stepIndex !== null) {
            $maxStepIndex = count($testCase->steps) - 1;
            abort_if($stepIndex > $maxStepIndex, 422, 'step_index is out of range for this test case.');
        }

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $key  = "test-session-results/{$result->id}/attachments/{$uuid}.{$file->extension()}";

        $storage->put($project, $key, fopen($file->getRealPath(), 'r'));

        $attachment = TestSessionResultAttachment::create([
            'test_session_result_id' => $result->id,
            'step_index'             => $stepIndex,
            's3_key'                 => $key,
            'file_name'              => $file->getClientOriginalName(),
            'file_size_bytes'        => $file->getSize(),
            'mime_type'              => $file->getMimeType(),
            'created_by'             => auth()->user()->person_id,
        ]);

        return new TestSessionResultAttachmentResource($attachment->load('createdBy'));
    }

    /**
     * Delete an attachment and its underlying file.
     *
     * @response 204 {}
     * @response 404 {"message": "Not Found"}
     */
    public function destroy(
        Project $project,
        TestSession $testSession,
        TestSessionResultAttachment $attachment,
        DocumentStorageDriver $storage,
    ): Response {
        $this->authorize('updateResult', [TestSession::class, $project, $testSession]);

        abort_if($attachment->testSessionResult->test_session_id !== $testSession->id, 404);

        $storage->delete($project, $attachment->s3_key);
        $attachment->delete();

        return response()->noContent();
    }
}
