<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Projects
            'projects:read',
            'projects:create',
            'projects:update',
            'projects:delete',

            // Stages
            'stages:read',
            'stages:manage',
            'stages:transition',

            // Stage boundaries
            'boundaries:read',
            'boundaries:manage',
            'boundaries:submit',
            'boundaries:approve-reject',

            // Daily log
            'daily-log:read',
            'daily-log:manage',

            // Issue log
            'issue-log:read',
            'issue-log:create',
            'issue-log:manage',
            'issue-log:escalate',

            // Risk log
            'risk-log:read',
            'risk-log:create',
            'risk-log:manage',

            // Change log
            'change-log:read',
            'change-log:create',
            'change-log:manage',
            'change-log:approve-minor',
            'change-log:approve-major',

            // Quality register
            'quality-register:read',
            'quality-register:manage',

            // Lessons log
            'lessons-log:read',
            'lessons-log:create',
            'lessons-log:manage',

            // Plans & tasks
            'plans:read',
            'plans:manage',
            'tasks:read',
            'tasks:manage',
            'tasks:update-own',

            // Reports
            'reports:read',
            'reports:generate',

            // People & team
            'people:read',
            'people:manage',
            'project-team:manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $matrix = [
            'executive' => [
                'projects:read',
                'stages:read',
                'boundaries:read', 'boundaries:approve-reject',
                'issue-log:read',
                'risk-log:read',
                'change-log:read', 'change-log:approve-major',
                'quality-register:read',
                'lessons-log:read',
                'plans:read',
                'tasks:read',
                'reports:read',
                'people:read',
            ],
            'senior_user' => [
                'projects:read',
                'stages:read',
                'boundaries:read', 'boundaries:approve-reject',
                'issue-log:read',
                'risk-log:read',
                'change-log:read', 'change-log:approve-major',
                'quality-register:read', 'quality-register:manage',
                'lessons-log:read',
                'plans:read',
                'tasks:read',
                'reports:read',
                'people:read',
            ],
            'senior_supplier' => [
                'projects:read',
                'stages:read',
                'boundaries:read', 'boundaries:approve-reject',
                'issue-log:read',
                'risk-log:read',
                'change-log:read', 'change-log:approve-major',
                'quality-register:read',
                'lessons-log:read',
                'plans:read',
                'tasks:read',
                'reports:read',
                'people:read',
            ],
            'project_manager' => [
                'projects:read', 'projects:create', 'projects:update', 'projects:delete',
                'stages:read', 'stages:manage', 'stages:transition',
                'boundaries:read', 'boundaries:manage', 'boundaries:submit',
                'daily-log:read', 'daily-log:manage',
                'issue-log:read', 'issue-log:create', 'issue-log:manage', 'issue-log:escalate',
                'risk-log:read', 'risk-log:create', 'risk-log:manage',
                'change-log:read', 'change-log:create', 'change-log:manage',
                'quality-register:read', 'quality-register:manage',
                'lessons-log:read', 'lessons-log:create', 'lessons-log:manage',
                'plans:read', 'plans:manage',
                'tasks:read', 'tasks:manage',
                'reports:read', 'reports:generate',
                'people:read', 'people:manage',
                'project-team:manage',
            ],
            'project_assurance' => [
                'projects:read',
                'stages:read',
                'boundaries:read',
                'daily-log:read',
                'issue-log:read', 'issue-log:create',
                'risk-log:read', 'risk-log:create',
                'change-log:read',
                'quality-register:read', 'quality-register:manage',
                'lessons-log:read', 'lessons-log:create',
                'plans:read',
                'tasks:read',
                'reports:read',
                'people:read',
            ],
            'project_support' => [
                'projects:read', 'projects:update',
                'stages:read', 'stages:manage',
                'boundaries:read', 'boundaries:manage',
                'daily-log:read', 'daily-log:manage',
                'issue-log:read', 'issue-log:create', 'issue-log:manage',
                'risk-log:read', 'risk-log:create', 'risk-log:manage',
                'change-log:read', 'change-log:create', 'change-log:manage',
                'quality-register:read', 'quality-register:manage',
                'lessons-log:read', 'lessons-log:create', 'lessons-log:manage',
                'plans:read', 'plans:manage',
                'tasks:read', 'tasks:manage',
                'reports:read', 'reports:generate',
                'people:read', 'people:manage',
                'project-team:manage',
            ],
            'change_authority' => [
                'projects:read',
                'stages:read',
                'boundaries:read',
                'issue-log:read',
                'risk-log:read',
                'change-log:read', 'change-log:manage', 'change-log:approve-minor',
                'quality-register:read',
                'lessons-log:read',
                'plans:read',
                'tasks:read',
                'reports:read',
                'people:read',
            ],
            'team_manager' => [
                'projects:read',
                'stages:read',
                'boundaries:read',
                'issue-log:read', 'issue-log:create',
                'risk-log:read', 'risk-log:create',
                'change-log:read', 'change-log:create',
                'quality-register:read',
                'lessons-log:read', 'lessons-log:create',
                'plans:read',
                'tasks:read', 'tasks:manage',
                'reports:read',
                'people:read',
            ],
            'team_member' => [
                'projects:read',
                'stages:read',
                'issue-log:read', 'issue-log:create',
                'change-log:read', 'change-log:create',
                'lessons-log:read', 'lessons-log:create',
                'plans:read',
                'tasks:read', 'tasks:update-own',
            ],
            'observer' => [
                'projects:read',
                'stages:read',
                'boundaries:read',
                'issue-log:read',
                'risk-log:read',
                'change-log:read',
                'quality-register:read',
                'lessons-log:read',
                'plans:read',
                'reports:read',
            ],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
