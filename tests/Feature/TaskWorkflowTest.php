<?php

namespace Tests\Feature;

use App\Actions\Projects\CompleteProject;
use App\Actions\Tasks\TransitionTask;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\SubtasksRelationManager;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ProjectManagementSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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
        $this->actingAs($manager);

        try {
            app(CompleteProject::class)($project, $manager);
            $this->fail('A project with open tasks should not be completed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'O projeto só pode ser concluído quando todas as tarefas estiverem concluídas ou canceladas.',
                $exception->errors()['status'][0],
            );
        }

        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
    }

    public function test_project_manager_can_complete_project_after_all_tasks_are_closed(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager, $manager);
        $task->update(['status' => TaskStatus::Completed]);

        $completedProject = app(CompleteProject::class)($project, $manager);

        $this->assertSame(ProjectStatus::Completed, $completedProject->status);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => ProjectStatus::Completed->value]);
    }

    public function test_manager_cannot_complete_another_managers_project(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $otherManager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($otherManager);
        $this->actingAs($manager);

        $this->expectException(AuthorizationException::class);

        app(CompleteProject::class)($project, $manager);
    }

    public function test_completing_last_open_task_does_not_implicitly_complete_project(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager, $manager);

        foreach ([TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Completed] as $status) {
            app(TransitionTask::class)($task, $status, $manager);
        }

        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertTrue($project->fresh()->canBeCompleted());
    }

    public function test_project_dates_cannot_exclude_existing_task_dates(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager, $manager);
        $task->update(['start_date' => today()->addDays(5), 'due_date' => today()->addDays(10)]);

        try {
            $project->update(['end_date' => today()->addDays(7)]);
            $this->fail('A project end date cannot exclude an existing task due date.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'As datas das tarefas precisam permanecer dentro do período do projeto.',
                $exception->errors()['end_date'][0],
            );
        }

        $this->assertSame(today()->addDays(30)->toDateString(), $project->fresh()->end_date->toDateString());
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

    public function test_member_can_only_change_status_of_tasks_assigned_to_them(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $otherMember = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach([$member->id, $otherMember->id]);
        $assignedTask = $this->createTask($project, $manager, $member);
        $otherTask = $this->createTask($project, $manager, $otherMember);

        $this->assertFalse($member->can('update', $assignedTask));
        $this->assertFalse($member->can('update', $otherTask));
        $this->assertTrue($member->can('changeStatus', $assignedTask));
        $this->assertFalse($member->can('changeStatus', $otherTask));
        $this->assertTrue($member->can('view', $assignedTask));
        $this->assertFalse($member->can('view', $otherTask));
        $this->assertSame([$assignedTask->id], $member->visibleTasksQuery()->pluck('id')->all());
    }

    public function test_member_can_view_participating_project_and_task_pages(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach($member->id);
        $ownTask = $this->createTask($project, $manager, $member);
        $otherTask = $this->createTask($project, $manager, $manager);

        $this->actingAs($member)
            ->get('/admin/projects/'.$project->id)
            ->assertOk();

        $this->get('/admin/tasks/'.$ownTask->id)->assertOk();
        $this->get('/admin/tasks/'.$ownTask->id.'/edit')->assertForbidden();
        $this->get('/admin/tasks/'.$otherTask->id)->assertNotFound();
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

    public function test_demo_seeder_creates_shield_permissions_before_assigning_roles(): void
    {
        $this->seed(ProjectManagementSeeder::class);

        $administrator = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $manager = User::query()->where('email', 'gestor@example.com')->firstOrFail();
        $member = User::query()->where('email', 'membro@example.com')->firstOrFail();

        $this->assertTrue($administrator->hasPermissionTo('Create:User'));
        $this->assertTrue($manager->hasPermissionTo('Create:Project'));
        $this->assertFalse($manager->hasPermissionTo('Create:User'));
        $this->assertTrue($member->hasPermissionTo('ViewAny:Project'));
        $this->assertFalse($member->hasPermissionTo('Create:Task'));
    }

    public function test_my_tasks_tabs_filter_assigned_tasks_by_requested_category(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->update(['start_date' => today()->subDays(10)]);
        $project->members()->attach($member->id);

        $todoTask = $this->createTask($project, $manager, $member);
        $todoTask->update(['title' => 'Minha tarefa a fazer']);
        $overdueTask = $this->createTask($project, $manager, $member);
        $overdueTask->update(['title' => 'Minha tarefa atrasada', 'due_date' => today()->subDay()]);
        $reviewTask = $this->createTask($project, $manager, $member);
        $reviewTask->update(['title' => 'Minha tarefa em revisão', 'status' => TaskStatus::InReview]);
        $completedTask = $this->createTask($project, $manager, $member);
        $completedTask->update(['title' => 'Minha tarefa concluída', 'status' => TaskStatus::Completed]);
        $backlogTask = $this->createTask($project, $manager, $member);
        $backlogTask->update(['title' => 'Minha tarefa no backlog', 'status' => TaskStatus::Backlog]);

        $this->actingAs($member)
            ->get('/admin/my-tasks?tab=overdue')
            ->assertOk()
            ->assertSee('Minha tarefa atrasada')
            ->assertDontSee('Minha tarefa em revisão');

        $this->get('/admin/my-tasks?tab=in_review')
            ->assertOk()
            ->assertSee('Minha tarefa em revisão')
            ->assertDontSee('Minha tarefa atrasada');

        $this->get('/admin/my-tasks?tab=todo')
            ->assertOk()
            ->assertSee('Minha tarefa a fazer')
            ->assertDontSee('Minha tarefa em revisão');

        $this->get('/admin/my-tasks?tab=completed')
            ->assertOk()
            ->assertSee('Minha tarefa concluída')
            ->assertDontSee('Minha tarefa no backlog');

        $this->get('/admin/my-tasks?tab=in_progress')
            ->assertOk()
            ->assertDontSee('Minha tarefa a fazer')
            ->assertDontSee('Minha tarefa concluída');

        $this->get('/admin/my-tasks?tab=all')
            ->assertOk()
            ->assertSee('Minha tarefa no backlog')
            ->assertSee('Minha tarefa concluída');
    }

    public function test_manager_can_view_project_members_and_tasks_relation_managers(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);

        $this->actingAs($manager);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        $project->members()->attach($member->id);
        $task = $this->createTask($project, $manager, $member);

        Livewire::test(MembersRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$member]);

        Livewire::test(TasksRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$task]);
    }

    public function test_task_relation_managers_create_comments_and_subtasks(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager, $manager);

        $this->actingAs($manager);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => EditTask::class,
        ])
            ->assertOk()
            ->assertSee('Adicionar comentário');

        $comment = $task->comments()->create([
            'user_id' => $manager->id,
            'body' => 'Comentário pelo Relation Manager.',
        ]);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $manager->id,
            'body' => 'Comentário pelo Relation Manager.',
        ]);

        Livewire::test(SubtasksRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => EditTask::class,
        ])
            ->assertOk()
            ->assertSee('Adicionar subtarefa');

        $subtask = $task->subtasks()->create([
            'title' => 'Subtarefa pelo Relation Manager',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('subtasks', [
            'task_id' => $task->id,
            'title' => 'Subtarefa pelo Relation Manager',
            'status' => 'pending',
        ]);

        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => EditTask::class,
        ])
            ->loadTable()
            ->assertCanSeeTableRecords([$comment]);

        Livewire::test(SubtasksRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => EditTask::class,
        ])
            ->loadTable()
            ->assertCanSeeTableRecords([$subtask]);
    }

    public function test_task_attachments_can_be_viewed_downloaded_and_removed_with_access_control(): void
    {
        Storage::fake('local');

        $manager = $this->createUserWithRole(RoleName::Manager);
        $member = $this->createUserWithRole(RoleName::Member);
        $otherMember = $this->createUserWithRole(RoleName::Member);
        $project = $this->createProject($manager);
        $project->members()->attach([$member->id, $otherMember->id]);
        $task = $this->createTask($project, $manager, $member);
        $attachment = $task
            ->addMedia(UploadedFile::fake()->createWithContent('specification.txt', 'Task specification'))
            ->toMediaCollection('attachments');

        $this->actingAs($member);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(AttachmentsRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => ViewTask::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$attachment])
            ->assertSee('Baixar');

        $download = $this->get(route('tasks.attachments.download', [$task, $attachment]));
        $download->assertOk();
        $this->assertSame(
            'attachment; filename="specification.txt"',
            $download->headers->get('content-disposition'),
        );

        $this->actingAs($otherMember)
            ->get(route('tasks.attachments.download', [$task, $attachment]))
            ->assertForbidden();

        $this->actingAs($manager);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(AttachmentsRelationManager::class, [
            'ownerRecord' => $task,
            'pageClass' => EditTask::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$attachment])
            ->assertSee('Remover');

        $attachment->delete();

        $this->assertDatabaseMissing('media', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->getPathRelativeToRoot());
    }

    public function test_manager_can_create_a_task_through_the_task_resource_form(): void
    {
        $manager = $this->createUserWithRole(RoleName::Manager);
        $project = $this->createProject($manager);
        $this->actingAs($manager);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Tarefa criada pelo Resource',
                'project_id' => $project->id,
                'assignee_id' => $manager->id,
                'priority' => Priority::High->value,
                'status' => TaskStatus::Todo->value,
                'start_date' => today()->toDateString(),
                'due_date' => today()->addDays(5)->toDateString(),
                'estimated_hours' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'title' => 'Tarefa criada pelo Resource',
        ]);
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
