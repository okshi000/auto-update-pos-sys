<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditService = app(AuditService::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_log_an_action(): void
    {
        $this->actingAs($this->user);

        $log = $this->auditService->log(
            action: 'test_action',
            auditable: $this->user,
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name']
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals(User::class, $log->auditable_type);
        $this->assertEquals($this->user->id, $log->auditable_id);
        $this->assertEquals(['name' => 'Old Name'], $log->old_values);
        $this->assertEquals(['name' => 'New Name'], $log->new_values);
    }

    /** @test */
    public function it_can_log_create_action(): void
    {
        $this->actingAs($this->user);

        $newUser = User::create([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $log = $this->auditService->logCreate($newUser);

        $this->assertEquals('created', $log->action);
        $this->assertNull($log->old_values);
        $this->assertNotNull($log->new_values);
        $this->assertEquals('New User', $log->new_values['name']);
    }

    /** @test */
    public function it_can_log_update_action(): void
    {
        $this->actingAs($this->user);

        $oldValues = $this->user->toArray();
        $this->user->name = 'Updated Name';
        $this->user->save();

        $log = $this->auditService->logUpdate($this->user, $oldValues);

        $this->assertEquals('updated', $log->action);
        $this->assertNotNull($log->old_values);
        $this->assertNotNull($log->new_values);
    }

    /** @test */
    public function it_can_log_delete_action(): void
    {
        $this->actingAs($this->user);

        $deletedUser = User::create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $log = $this->auditService->logDelete($deletedUser);

        $this->assertEquals('deleted', $log->action);
        $this->assertNotNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    /** @test */
    public function it_can_log_login_action(): void
    {
        $log = $this->auditService->logLogin($this->user);

        $this->assertEquals('login', $log->action);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals(User::class, $log->auditable_type);
        $this->assertEquals($this->user->id, $log->auditable_id);
    }

    /** @test */
    public function it_can_log_logout_action(): void
    {
        $log = $this->auditService->logLogout($this->user);

        $this->assertEquals('logout', $log->action);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    /** @test */
    public function it_can_log_failed_login(): void
    {
        $log = $this->auditService->logFailedLogin('unknown@example.com');

        $this->assertEquals('failed_login', $log->action);
        $this->assertNull($log->user_id);
        $this->assertEquals(['email' => 'unknown@example.com'], $log->new_values);
    }

    /** @test */
    public function audit_log_user_scope_works(): void
    {
        $this->actingAs($this->user);

        $this->auditService->log('action1', $this->user);
        $this->auditService->log('action2', $this->user);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($otherUser);
        $this->auditService->log('action3', $otherUser);

        $userLogs = AuditLog::byUser($this->user->id)->get();
        $this->assertCount(2, $userLogs);

        $otherLogs = AuditLog::byUser($otherUser->id)->get();
        $this->assertCount(1, $otherLogs);
    }

    /** @test */
    public function audit_log_action_scope_works(): void
    {
        $this->actingAs($this->user);

        $this->auditService->log('login', $this->user);
        $this->auditService->log('login', $this->user);
        $this->auditService->log('logout', $this->user);

        $loginLogs = AuditLog::byAction('login')->get();
        $this->assertCount(2, $loginLogs);

        $logoutLogs = AuditLog::byAction('logout')->get();
        $this->assertCount(1, $logoutLogs);
    }

    /** @test */
    public function audit_log_date_range_scope_works(): void
    {
        $this->actingAs($this->user);

        // Create log from yesterday
        $oldLog = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'old_action',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        // Create log from today
        $this->auditService->log('new_action', $this->user);

        $recentLogs = AuditLog::betweenDates(now()->subDay(), now()->addDay())->get();
        $this->assertCount(1, $recentLogs);
        $this->assertEquals('new_action', $recentLogs->first()->action);
    }

    /** @test */
    public function audit_log_changes_accessor_works(): void
    {
        $this->actingAs($this->user);

        $log = $this->auditService->log(
            action: 'update',
            auditable: $this->user,
            oldValues: ['name' => 'Old', 'email' => 'old@test.com'],
            newValues: ['name' => 'New', 'email' => 'new@test.com']
        );

        $changes = $log->changes;

        $this->assertArrayHasKey('old', $changes);
        $this->assertArrayHasKey('new', $changes);
        $this->assertEquals('Old', $changes['old']['name']);
        $this->assertEquals('New', $changes['new']['name']);
    }
}
