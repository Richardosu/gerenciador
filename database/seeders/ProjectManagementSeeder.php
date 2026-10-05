<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Enums\SubtaskStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProjectManagementSeeder extends Seeder
{
    public function run(): void
    {
        $resourcePermissions = collect(['Project', 'Task', 'User', 'Role'])
            ->crossJoin([
                'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny',
                'ForceDelete', 'ForceDeleteAny', 'Restore', 'RestoreAny', 'Replicate', 'Reorder',
            ])
            ->map(fn (array $permission): string => "{$permission[1]}:{$permission[0]}");

        $standalonePermissions = collect([
            'View:MyTasks',
            'View:ProjectStatsWidget',
            'View:TasksByStatusChart',
            'View:UpcomingTasksWidget',
        ]);

        $resourcePermissions
            ->concat($standalonePermissions)
            ->each(fn (string $permission): mixed => Permission::findOrCreate($permission, 'web'));

        foreach (RoleName::cases() as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName->value,
                'guard_name' => 'web',
            ]);
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin Demonstração', 'password' => 'password', 'is_active' => true],
        );
        $manager = User::query()->updateOrCreate(
            ['email' => 'gestor@example.com'],
            ['name' => 'Gestor Demonstração', 'password' => 'password', 'is_active' => true],
        );
        $member = User::query()->updateOrCreate(
            ['email' => 'membro@example.com'],
            ['name' => 'Membro Demonstração', 'password' => 'password', 'is_active' => true],
        );

        $admin->syncRoles(RoleName::Admin->value);
        $manager->syncRoles(RoleName::Manager->value);
        $member->syncRoles(RoleName::Member->value);

        Role::findByName(RoleName::Admin->value)->syncPermissions(Permission::query()->get());
        Role::findByName(RoleName::Manager->value)->syncPermissions(
            Permission::query()
                ->where('name', 'not like', '%:User')
                ->where('name', 'not like', '%:Role')
                ->get(),
        );
        Role::findByName(RoleName::Member->value)->syncPermissions(
            Permission::query()
                ->whereIn('name', [
                    'ViewAny:Project', 'View:Project', 'ViewAny:Task', 'View:Task', 'View:MyTasks',
                    'View:ProjectStatsWidget', 'View:TasksByStatusChart', 'View:UpcomingTasksWidget',
                ])
                ->get(),
        );

        $project = Project::query()->updateOrCreate(
            ['name' => 'Projeto de demonstração'],
            [
                'description' => 'Projeto para demonstrar membros, tarefas, progresso e notificações.',
                'manager_id' => $manager->id,
                'start_date' => today()->subDays(10),
                'end_date' => today()->addDays(30),
                'status' => ProjectStatus::InProgress,
                'priority' => Priority::High,
            ],
        );

        $project->members()->syncWithoutDetaching([$admin->id, $member->id]);

        $taskDefinitions = [
            ['title' => 'Planejar entregas', 'status' => TaskStatus::Completed, 'due_date' => today()->addDays(2)],
            ['title' => 'Implementar fluxo principal', 'status' => TaskStatus::InProgress, 'due_date' => today()->addDays(4)],
            ['title' => 'Revisar documentação', 'status' => TaskStatus::Todo, 'due_date' => today()->addDays(7)],
            ['title' => 'Validar protótipo', 'status' => TaskStatus::InReview, 'due_date' => today()->addDays(1)],
            ['title' => 'Atualizar plano de testes', 'status' => TaskStatus::Todo, 'due_date' => today()->subDay()],
        ];

        foreach ($taskDefinitions as $position => $definition) {
            $task = Task::query()->updateOrCreate(
                ['project_id' => $project->id, 'title' => $definition['title']],
                [
                    'assignee_id' => $member->id,
                    'created_by' => $manager->id,
                    'description' => 'Tarefa de exemplo para o fluxo de gerenciamento.',
                    'priority' => $position === 4 ? Priority::Urgent : Priority::Medium,
                    'status' => $definition['status'],
                    'start_date' => today()->subDays(5),
                    'due_date' => $definition['due_date'],
                    'completed_at' => $definition['status'] === TaskStatus::Completed ? now() : null,
                    'estimated_hours' => 2.5,
                ],
            );

            if ($position === 1) {
                Subtask::query()->updateOrCreate(
                    ['task_id' => $task->id, 'title' => 'Preparar ambiente'],
                    ['status' => SubtaskStatus::Completed, 'position' => 1, 'completed_at' => now()],
                );

                Subtask::query()->updateOrCreate(
                    ['task_id' => $task->id, 'title' => 'Implementar tela'],
                    ['status' => SubtaskStatus::Pending, 'position' => 2, 'completed_at' => null],
                );
            }
        }
    }
}
