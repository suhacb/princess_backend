<?php

namespace App\Console\Commands;

use App\Contracts\AuthGatewayClientContract;
use App\Contracts\GraphClientContract;
use App\Contracts\OllamaClientContract;
use App\Contracts\QdrantClientContract;
use App\Contracts\ZincSearchClientContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckCommand extends Command
{
    protected $signature = 'princess:health';

    protected $description = 'Check connectivity to all princess services';

    public function handle(): int
    {
        $this->line('Checking princess services...');
        $this->newLine();

        $checks = [
            'Database'     => fn () => DB::connection()->getPdo() !== null,
            'Redis'        => fn () => (bool) Redis::ping(),
            'Auth Backend' => fn () => app(AuthGatewayClientContract::class)->ping(),
            'ZincSearch'   => fn () => app(ZincSearchClientContract::class)->ping(),
            'Qdrant'       => fn () => app(QdrantClientContract::class)->ping(),
            'Ollama'       => fn () => app(OllamaClientContract::class)->ping(),
            'M365 Graph'   => fn () => app(GraphClientContract::class)->ping(),
        ];

        $failures = 0;

        foreach ($checks as $name => $check) {
            try {
                $ok = $check();
            } catch (\Throwable) {
                $ok = false;
            }

            $status = $ok
                ? '<fg=green>OK</>'
                : '<fg=red>FAIL</>';

            $this->line(sprintf('  %-14s %s', $name, $status));

            if (! $ok) {
                $failures++;
            }
        }

        $this->newLine();

        if ($failures === 0) {
            $this->line('<fg=green>All services OK.</>');
            return self::SUCCESS;
        }

        $this->line("<fg=red>{$failures} service(s) unreachable.</>");
        return self::FAILURE;
    }
}
