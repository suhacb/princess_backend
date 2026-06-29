<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use App\Models\DocumentVersion;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QaDocument;
use App\Models\User;
use App\Services\Document\ProjectStorageService;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    private const ROLE_USERS = [
        ['external_id' => 'e2e-executive',        'username' => 'e2e_executive',        'name' => 'E2E Executive',        'email' => 'e2e.executive@princess.test',        'role' => ProjectRole::Executive],
        ['external_id' => 'e2e-senior-user',       'username' => 'e2e_senior_user',       'name' => 'E2E Senior User',       'email' => 'e2e.senior_user@princess.test',       'role' => ProjectRole::SeniorUser],
        ['external_id' => 'e2e-senior-supplier',   'username' => 'e2e_senior_supplier',   'name' => 'E2E Senior Supplier',   'email' => 'e2e.senior_supplier@princess.test',   'role' => ProjectRole::SeniorSupplier],
        ['external_id' => 'e2e-project-manager',   'username' => 'e2e_project_manager',   'name' => 'E2E Project Manager',   'email' => 'e2e.project_manager@princess.test',   'role' => ProjectRole::ProjectManager],
        ['external_id' => 'e2e-project-assurance', 'username' => 'e2e_project_assurance', 'name' => 'E2E Project Assurance', 'email' => 'e2e.project_assurance@princess.test', 'role' => ProjectRole::ProjectAssurance],
        ['external_id' => 'e2e-project-support',   'username' => 'e2e_project_support',   'name' => 'E2E Project Support',   'email' => 'e2e.project_support@princess.test',   'role' => ProjectRole::ProjectSupport],
        ['external_id' => 'e2e-change-authority',  'username' => 'e2e_change_authority',  'name' => 'E2E Change Authority',  'email' => 'e2e.change_authority@princess.test',  'role' => ProjectRole::ChangeAuthority],
        ['external_id' => 'e2e-team-manager',      'username' => 'e2e_team_manager',      'name' => 'E2E Team Manager',      'email' => 'e2e.team_manager@princess.test',      'role' => ProjectRole::TeamManager],
        ['external_id' => 'e2e-team-member',       'username' => 'e2e_team_member',       'name' => 'E2E Team Member',       'email' => 'e2e.team_member@princess.test',       'role' => ProjectRole::TeamMember],
        ['external_id' => 'e2e-observer',          'username' => 'e2e_observer',          'name' => 'E2E Observer',          'email' => 'e2e.observer@princess.test',          'role' => ProjectRole::Observer],
    ];

    private const NON_MEMBER = [
        'external_id' => 'e2e-non-member',
        'username'    => 'e2e_non_member',
        'name'        => 'E2E Non Member',
        'email'       => 'e2e.non_member@princess.test',
    ];

    public function run(): void
    {
        // Create the PM person first — needed for project's created_by (NOT NULL FK).
        $pmData   = self::ROLE_USERS[3]; // ProjectManager
        $pmPerson = Person::create(['name' => $pmData['name'], 'email' => $pmData['email']]);

        $project = Project::create([
            'name'              => 'E2E Test Project',
            'reference'         => 'E2E-001',
            'status'            => 'pre_project',
            'document_provider' => 'garage',
            'created_by'        => $pmPerson->id,
        ]);

        foreach (self::ROLE_USERS as $data) {
            if ($data['external_id'] === $pmData['external_id']) {
                $person = $pmPerson;
            } else {
                $person = Person::create(['name' => $data['name'], 'email' => $data['email']]);
            }

            User::create([
                'external_id' => $data['external_id'],
                'username'    => $data['username'],
                'name'        => $data['name'],
                'email'       => $data['email'],
                'person_id'   => $person->id,
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'person_id'  => $person->id,
                'role'       => $data['role'],
            ]);
        }

        $nonMemberPerson = Person::create(['name' => self::NON_MEMBER['name'], 'email' => self::NON_MEMBER['email']]);

        User::create([
            'external_id' => self::NON_MEMBER['external_id'],
            'username'    => self::NON_MEMBER['username'],
            'name'        => self::NON_MEMBER['name'],
            'email'       => self::NON_MEMBER['email'],
            'person_id'   => $nonMemberPerson->id,
        ]);

        try {
            app(ProjectStorageService::class)->provision($project);
        } catch (\Throwable) {
            // Garage unreachable — skip bucket provisioning silently.
        }

        $document = QaDocument::create([
            'project_id' => $project->id,
            'type'       => QaDocumentType::ProjectInitiationDocument,
            'title'      => 'E2E Project Initiation Document',
            'version'    => 'v0.1',
            'status'     => QaDocumentStatus::Draft,
            'created_by' => $pmPerson->id,
        ]);

        $version = DocumentVersion::create([
            'document_id'     => $document->id,
            'version_number'  => 1,
            's3_key'          => "documents/{$document->id}/versions/1/project-initiation-document.docx",
            'file_name'       => 'project-initiation-document.docx',
            'file_size_bytes' => 0,
            'created_by'      => $pmPerson->id,
        ]);

        $document->update(['current_version_id' => $version->id]);
    }
}
