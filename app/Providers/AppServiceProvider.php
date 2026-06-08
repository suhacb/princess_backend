<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Policies\ProjectPolicy;
use App\Policies\StageBoundaryPolicy;
use App\Policies\StagePolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Stage::class, StagePolicy::class);
        Gate::policy(StageBoundary::class, StageBoundaryPolicy::class);

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
        });
    }
}
