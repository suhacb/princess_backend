<?php

namespace App\Http\Controllers;

use App\Contracts\GarageAdminClientContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class E2eController extends Controller
{
    public function reset(Request $request, GarageAdminClientContract $garage): JsonResponse
    {
        $this->resetStorage($garage);

        if ($request->boolean('full')) {
            Artisan::call('migrate:fresh', [
                '--database' => 'e2e',
                '--seed'     => true,
                '--seeder'   => 'E2eSeeder',
                '--force'    => true,
            ]);
        } else {
            $conn = DB::connection('e2e');
            $conn->statement('SET session_replication_role = replica');
            $tables = $conn->select(
                "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename != 'migrations'"
            );
            foreach ($tables as $table) {
                $conn->statement("TRUNCATE TABLE \"{$table->tablename}\" RESTART IDENTITY CASCADE");
            }
            $conn->statement('SET session_replication_role = DEFAULT');

            Artisan::call('db:seed', [
                '--class'    => 'E2eSeeder',
                '--database' => 'e2e',
                '--force'    => true,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function resetStorage(GarageAdminClientContract $garage): void
    {
        $prefix = config('princess.garage.bucket_prefix', 'princess-e2e-project');

        if (! str_starts_with($prefix, 'princess-e2e')) {
            // Safety guard: only wipe buckets in an e2e environment.
            return;
        }

        try {
            $buckets = $garage->listBucketsWithPrefix($prefix);
        } catch (\Throwable) {
            // Garage unreachable — skip storage reset silently.
            return;
        }

        foreach ($buckets as $bucket) {
            $alias = $bucket['globalAliases'][0] ?? $bucket['id'];

            try {
                $disk = Storage::build([
                    'driver'                  => 's3',
                    'key'                     => config('princess.garage.access_key_id'),
                    'secret'                  => config('princess.garage.secret_access_key'),
                    'region'                  => config('princess.garage.region'),
                    'bucket'                  => $alias,
                    'endpoint'                => config('princess.garage.s3_endpoint'),
                    'use_path_style_endpoint' => true,
                    'throw'                   => false,
                ]);

                // Delete all objects so the bucket can be dropped.
                foreach ($disk->allFiles() as $file) {
                    $disk->delete($file);
                }

                $garage->deleteBucket($bucket['id']);
            } catch (\Throwable) {
                // Best-effort: skip buckets that can't be cleaned up.
            }
        }
    }
}
