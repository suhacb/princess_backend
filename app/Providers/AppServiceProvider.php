<?php

namespace App\Providers;

use App\Models\Change;
use App\Models\DailyLogEntry;
use App\Models\Issue;
use App\Models\Lesson;
use App\Models\AcceptanceCriterion;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductDependency;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectProductDescription;
use App\Models\QaDocument;
use App\Models\QualityRegisterEntry;
use App\Models\Requirement;
use App\Models\Risk;
use App\Models\Stage;
use App\Models\StageBoundary;
use App\Models\WorkPackage;
use App\Policies\AcceptanceCriterionPolicy;
use App\Policies\ChangePolicy;
use App\Policies\DailyLogEntryPolicy;
use App\Policies\IssuePolicy;
use App\Policies\LessonPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ProductFlowPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProjectMemberPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectProductDescriptionPolicy;
use App\Policies\QaDocumentPolicy;
use App\Policies\QualityRegisterEntryPolicy;
use App\Policies\RequirementPolicy;
use App\Policies\RiskPolicy;
use App\Policies\StageBoundaryPolicy;
use App\Policies\StagePolicy;
use App\Policies\WorkPackagePolicy;
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
        Gate::policy(ProjectProductDescription::class, ProjectProductDescriptionPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductDependency::class, ProductFlowPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(WorkPackage::class, WorkPackagePolicy::class);
        Gate::policy(Requirement::class, RequirementPolicy::class);
        Gate::policy(AcceptanceCriterion::class, AcceptanceCriterionPolicy::class);
        Gate::policy(QaDocument::class, QaDocumentPolicy::class);

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
        });
    }
}
