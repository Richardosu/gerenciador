<?php

namespace Tests\Feature;

use App\Actions\Projects\CompleteProject;
use App\Actions\Tasks\TransitionTask;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_workflow_sets_and_clears_completion_timestamp(): void
    {
        $this->freezeTime();
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $manager->id,
            'created_by' => $manager->id,
            'title' => 'Implementar fluxo',
            'priority' => Priority::High,
            'status' => TaskStatus::Todo,
        ]);

        $this->actingAs($manager);
        $transition = app(TransitionTask::class);
        $transition($task, TaskStatus::InProgress, $manager);
        $transition($task, TaskStatus::InReview, $manager);
        $completedTask = $transition($task, TaskStatus::Completed, $manager);

        $this->assertSame(TaskStatus::Completed, $completedTask->status);
        $this->assertSame(now()->toDateTimeString(), $completedTask->completed_at->toDateTimeString());
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::Completed->value]);

        $reopenedTask = $transition($completedTask, TaskStatus::Todo, $manager);

        $this->assertSame(TaskStatus::Todo, $reopenedTask->status);
        $this->assertNull($reopenedTask->completed_at);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_at' => null]);
    }

    public function test_task_cannot_be_assigned_to_a_non_member_of_its_project(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $outsider = User::factory()->create();
        $project = $this->createProject($manager);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O responsável pela tarefa deve ser membro do respectivo projeto.');

        Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $outsider->id,
            'created_by' => $manager->id,
            'title' => 'Atribuição inválida',
            'priority' => Priority::Medium,
            'status' => TaskStatus::Todo,
        ]);
    }

    public function test_task_due_date_must_fall_within_project_period(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A data limite deve respeitar o período do projeto.');

        Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $manager->id,
            'created_by' => $manager->id,
            'title' => 'Prazo inválido',
            'priority' => Priority::Medium,
            'status' => TaskStatus::Todo,
            'due_date' => today()->addDays(40),
        ]);
    }

    public function test_task_cannot_be_created_inside_a_closed_project(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $project->update(['status' => ProjectStatus::Completed]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Projetos concluídos ou cancelados não podem receber novas tarefas.');

        Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $manager->id,
            'created_by' => $manager->id,
            'title' => 'Nova tarefa em projeto encerrado',
            'priority' => Priority::Medium,
            'status' => TaskStatus::Todo,
        ]);
    }

    public function test_project_cannot_be_completed_while_open_tasks_remain(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $this->createTask($project, $manager, $manager);

        try {
            app(CompleteProject::class)($project);
            $this->fail('A project with open tasks should not be completed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
                $exception->errors()['status'][0],
            );
        }

        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
    }

    public function test_invalid_task_transition_is_rejected(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager, $manager);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Esta transição de status não está disponível.');

        app(TransitionTask::class)($task, TaskStatus::Completed, $manager);
    }

    public function test_assignee_receives_a_database_notification_when_task_is_assigned(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach($member->id);

        $this->actingAs($manager);
        Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'created_by' => $manager->id,
            'title' => 'Tarefa atribuída',
            'priority' => Priority::Medium,
            'status' => TaskStatus::Todo,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $member->id,
        ]);
    }

    public function test_project_manager_receives_a_database_notification_when_task_is_completed(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach($member->id);
        $task = $this->createTask($project, $manager, $member);
        $task->update(['status' => TaskStatus::InReview]);

        $this->actingAs($member);
        app(TransitionTask::class)($task, TaskStatus::Completed, $member);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $manager->id,
        ]);
    }

    public function test_project_progress_is_calculated_from_completed_tasks(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);

        foreach ([TaskStatus::Completed, TaskStatus::InProgress] as $position => $status) {
            Task::query()->create([
                'project_id' => $project->id,
                'assignee_id' => $manager->id,
                'created_by' => $manager->id,
                'title' => "Tarefa {$position}",
                'priority' => Priority::Medium,
                'status' => $status,
            ]);
        }

        $this->assertSame(50, $project->fresh()->progress);
    }

    public function test_member_can_only_update_tasks_assigned_to_them(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $otherMember = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach([$member->id, $otherMember->id]);
        $assignedTask = $this->createTask($project, $manager, $member);
        $otherTask = $this->createTask($project, $manager, $otherMember);

        $this->assertTrue($member->can('update', $assignedTask));
        $this->assertFalse($member->can('update', $otherTask));
    }

    public function test_member_can_view_participating_project_and_task_pages(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach($member->id);
        $task = $this->createTask($project, $manager, $manager);

        $this->actingAs($member)
            ->get('/admin/projects/'.$project->id)
            ->assertOk();

        $this->get('/admin/tasks/'.$task->id)
            ->assertOk();
    }

    public function test_manager_cannot_open_another_managers_project(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $otherManager = $this->createUserWithRole(RoleName::Manager);
        $otherProject = $this->createProject($otherManager);

        $this->actingAs($manager)
            ->get('/admin/projects/'.$otherProject->id.'/edit')
            ->assertNotFound();
    }

    public function test_dashboard_and_custom_task_page_render_for_authenticated_user(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);

        $this->actingAs($manager)
            ->get('/admin')
            ->assertOk();

        $this->get('/admin/my-tasks')
            ->assertOk();

        $this->get('/admin/tasks')
            ->assertOk();
    }

    private function createUserWithRole(RoleName $roleName): User
    {
        Role::query()->firstOrCreate([
            'name' => $roleName->value,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($roleName->value);

        return $user;
    }

    private function createProject(User $manager): Project
    {
        return Project::query()->create([
            'name' => 'Projeto de teste',
            'manager_id' => $manager->id,
            'start_date' => today(),
            'end_date' => today()->addDays(30),
            'status' => ProjectStatus::InProgress,
            'priority' => Priority::Medium,
        ]);
    }

    private function createTask(Project $project, User $manager, User $assignee): Task
    {
        return Task::query()->create([
            'project_id' => $project->id,
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
            'title' => 'Tarefa de teste',
            'priority' => Priority::Medium,
            'status' => TaskStatus::Todo,
        ]);
    }
}
