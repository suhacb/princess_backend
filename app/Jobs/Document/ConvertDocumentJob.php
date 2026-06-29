<?php

namespace App\Jobs\Document;

use App\Models\DocumentVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stub for the document conversion pipeline.
 *
 * Intended pipeline (not yet implemented):
 *   1. Fetch the .docx file from S3 at $version->s3_key
 *   2. Convert to Markdown via LibreOffice headless (or equivalent)
 *   3. Store the .md at $version->converted_md_key (same bucket, *.md suffix)
 *   4. Dispatch IndexDocumentJob to index the converted content
 *
 * Separated from IndexDocumentJob so the search/AI phase (DOC-03, DOC-04)
 * can plug in without touching this job.
 */
class ConvertDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly DocumentVersion $version) {}

    public function handle(): void
    {
        Log::info("Document conversion not yet implemented for version {$this->version->id}");
    }
}
