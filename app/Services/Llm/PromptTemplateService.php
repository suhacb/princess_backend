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
            $existing = PromptTemplate::where('name', $name)->lockForUpdate()->get();

            $existing->where('active', true)->each(function (PromptTemplate $template) {
                $template->update(['active' => false]);
            });

            $nextVersion = ($existing->max('version') ?? 0) + 1;

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
