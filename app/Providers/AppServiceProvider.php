<?php

namespace App\Providers;

use App\Models\Change;
use App\Models\DailyLogEntry;
use App\Models\Issue;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QualityRegisterEntry;
use App\Models\Risk;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Policies\ChangePolicy;
use App\Policies\DailyLogEntryPolicy;
use App\Policies\IssuePolicy;
use App\Policies\LessonPolicy;
use App\Policies\ProjectMemberPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\QualityRegisterEntryPolicy;
use App\Policies\RiskPolicy;
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
        Gate::policy(ProjectMember::class, ProjectMemberPolicy::class);
        Gate::policy(Stage::class, StagePolicy::class);
        Gate::policy(StageBoundary::class, StageBoundaryPolicy::class);
        Gate::policy(DailyLogEntry::class, DailyLogEntryPolicy::class);
        Gate::policy(Issue::class, IssuePolicy::class);
        Gate::policy(Risk::class, RiskPolicy::class);
        Gate::policy(Change::class, ChangePolicy::class);
        Gate::policy(QualityRegisterEntry::class, QualityRegisterEntryPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
        });
    }
}
