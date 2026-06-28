<?php

namespace App\Console\Commands\Garage;

use App\Contracts\GarageAdminClientContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BootstrapGarageCommand extends Command
{
    protected $signature = 'garage:bootstrap
                            {--zone=garage : Zone name to assign to the cluster node}
                            {--capacity=1073741824 : Storage capacity in bytes (default: 1 GB)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Bootstrap the Garage S3 cluster: apply layout, create access key, create templates bucket';

    public function handle(GarageAdminClientContract $garage): int
    {
        $this->line('');
        $this->line('  <fg=blue>Garage S3 Bootstrap</>');
        $this->line('  ' . str_repeat('─', 44));
        $this->line('');

        // 1. Connectivity check
        $this->line('  Checking Garage Admin API connectivity...');

        if (! $garage->ping()) {
            $this->line('  <fg=red>✗ Cannot reach Garage Admin API at ' . config('princess.garage.admin_url') . '</>');
            $this->line('  Make sure the garage container is running.');
            return self::FAILURE;
        }

        $this->line('  <fg=green>✓ Garage Admin API reachable</>');
        $this->line('');

        // 2. Layout
        $this->line('  Applying cluster layout...');

        try {
            $nodeId   = $garage->getNodeId();
            $zone     = $this->option('zone');
            $capacity = (int) $this->option('capacity');

            if ($garage->nodeHasRole($nodeId)) {
                $this->line("  <fg=yellow>↷ Node already has a role — skipping layout</>  (node: {$nodeId})");
            } else {
                $garage->applyLayout($nodeId, $zone, $capacity);
                $version = $garage->getLayoutVersion();
                $this->line("  <fg=green>✓ Layout applied</>  (node: {$nodeId}, zone: {$zone}, version: {$version})");
            }
        } catch (RuntimeException $e) {
            $this->line("  <fg=red>✗ Layout failed: {$e->getMessage()}</>");
            return self::FAILURE;
        }

        $this->line('');

        // 3. Access key
        $keyName = 'princess-backend';
        $this->line("  Creating access key '{$keyName}'...");

        try {
            $existingKey = $garage->findKey($keyName);

            if ($existingKey) {
                $this->line("  <fg=yellow>↷ Key '{$keyName}' already exists — secret cannot be recovered</>");
                $this->line("    Key ID: <fg=cyan>{$existingKey['accessKeyId']}</>");
                $this->line("    Add GARAGE_ACCESS_KEY_ID to .env — create a new key if the secret is lost.");
                $keyId = $existingKey['accessKeyId'];
            } else {
                if (! $this->option('force') && ! $this->confirm("  Create new access key '{$keyName}'?", true)) {
                    $this->line('  Aborted.');
                    return self::FAILURE;
                }

                $key = $garage->createKey($keyName);
                $keyId = $key['accessKeyId'];

                $this->line('  <fg=green>✓ Access key created — copy these values to .env NOW, the secret is shown only once:</>');
                $this->line('');
                $this->line("    GARAGE_ACCESS_KEY_ID=<fg=cyan>{$key['accessKeyId']}</>");
                $this->line("    GARAGE_SECRET_ACCESS_KEY=<fg=cyan>{$key['secretAccessKey']}</>");
                $this->line('');
            }
        } catch (RuntimeException $e) {
            $this->line("  <fg=red>✗ Key creation failed: {$e->getMessage()}</>");
            return self::FAILURE;
        }

        // 4. Templates bucket
        $templatesBucket = config('princess.garage.templates_bucket', 'princess-templates');
        $this->line("  Creating templates bucket '{$templatesBucket}'...");

        try {
            $bucketId = $garage->findBucket($templatesBucket);

            if ($bucketId) {
                $this->line("  <fg=yellow>↷ Bucket '{$templatesBucket}' already exists</>  (id: {$bucketId})");
            } else {
                $bucketId = $garage->createBucket($templatesBucket);
                $this->line("  <fg=green>✓ Bucket '{$templatesBucket}' created</>  (id: {$bucketId})");
            }

            $garage->allowKeyOnBucket($bucketId, $keyId);
            $this->line("  <fg=green>✓ Key granted read/write/owner on '{$templatesBucket}'</>");
        } catch (RuntimeException $e) {
            $this->line("  <fg=red>✗ Bucket setup failed: {$e->getMessage()}</>");
            return self::FAILURE;
        }

        $this->line('');

        // 5. S3 connectivity verification
        $this->line('  Verifying S3 connectivity...');

        try {
            $probe = '_bootstrap-probe.txt';
            Storage::disk('garage')->put($probe, 'ok');
            $contents = Storage::disk('garage')->get($probe);
            Storage::disk('garage')->delete($probe);

            if ($contents !== 'ok') {
                throw new RuntimeException('S3 read-back mismatch.');
            }

            $this->line('  <fg=green>✓ S3 read/write verified</>');
        } catch (\Throwable $e) {
            $this->line("  <fg=red>✗ S3 verification failed: {$e->getMessage()}</>");
            $this->line('  Check GARAGE_ACCESS_KEY_ID, GARAGE_SECRET_ACCESS_KEY, and AWS_ENDPOINT in .env.');
            return self::FAILURE;
        }

        $this->line('');
        $this->line('  <fg=green>Bootstrap complete.</>');
        $this->line('');

        return self::SUCCESS;
    }
}
