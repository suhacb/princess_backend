<?php

namespace App\Services\Llm;

use App\Exceptions\PromptTemplate\MissingPlaceholderException;
use App\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;

class PromptTemplateService
{
    public function createVersion(string $name, string $body, int $createdBy): PromptTemplate
    {
        return DB::transaction(function () use ($name, $body, $createdBy) {
            // A row-level lock only protects rows that already exist; it does nothing for the
            // "first version of a brand-new name" case and doesn't stop a second transaction from
            // recomputing the same next version once it unblocks. An advisory lock keyed by name
            // serializes all createVersion() calls for that name, including brand-new names.
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$name]);

            $nextVersion = (PromptTemplate::where('name', $name)->max('version') ?? 0) + 1;

            PromptTemplate::where('name', $name)->where('active', true)->update(['active' => false]);

            return PromptTemplate::create([
                'name'       => $name,
                'version'    => $nextVersion,
                'body'       => $body,
                'created_by' => $createdBy,
                'active'     => true,
            ]);
        });
    }

    public function resolve(string $name, ?int $version = null): PromptTemplate
    {
        if ($version !== null) {
            return PromptTemplate::where('name', $name)->where('version', $version)->firstOrFail();
        }

        return PromptTemplate::where('name', $name)->active()->firstOrFail();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(PromptTemplate $template, array $context): string
    {
        $missing = array_diff($template->placeholders(), array_keys($context));

        if ($missing !== []) {
            throw new MissingPlaceholderException($missing);
        }

        $body = $template->body;

        foreach ($context as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        return $body;
    }
}
