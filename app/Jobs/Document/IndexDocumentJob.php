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
 * Stub for the document search/AI indexing pipeline.
 *
 * Intended pipeline (not yet implemented):
 *   1. Read converted Markdown from S3 at $version->converted_md_key
 *   2. Index full text in ZincSearch (DOC-03)
 *   3. Generate embeddings via Ollama and store in Qdrant (DOC-04)
 *
 * Dispatched by ConvertDocumentJob once conversion is real.
 * Separated so ZincSearch indexing and Qdrant embedding can be toggled
 * independently from the conversion step.
 */
class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly DocumentVersion $version) {}

    public function handle(): void
    {
        Log::info("Document indexing not yet implemented for version {$this->version->id}");
    }
}
