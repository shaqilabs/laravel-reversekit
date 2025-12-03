<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test listing users.
     */
    public function test_can_list_users(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email'],
                ],
            ]);
    }

    /**
     * Test creating a user.
     */
    public function test_can_create_user(): void
    {
        $user = User::factory()->create();

        $data = [
            'name' => 'Test name',
            'email' => 'test@example.com',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/users', $data);

        $response->assertCreated()
            ->assertJsonFragment($data);
    }

    /**
     * Test showing a user.
     */
    public function test_can_show_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/users/{$targetUser->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
            ]);
    }

    /**
     * Test updating a user.
     */
    public function test_can_update_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $data = [
            'name' => 'Updated name',
            'email' => 'updated@example.com',
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$targetUser->id}", $data);

        $response->assertOk()
            ->assertJsonFragment($data);
    }

    /**
     * Test deleting a user.
     */
    public function test_can_delete_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }
}
