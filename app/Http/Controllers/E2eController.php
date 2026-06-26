<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class E2eController extends Controller
{
    public function reset(Request $request): JsonResponse
    {
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
}
