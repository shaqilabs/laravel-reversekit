<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test listing posts.
     */
    public function test_can_list_posts(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'body', 'published', 'user_id'],
                ],
            ]);
    }

    /**
     * Test creating a post.
     */
    public function test_can_create_post(): void
    {
        $user = User::factory()->create();

        $data = [
            'title' => 'Test title',
            'body' => 'Test body content',
            'published' => true,
            'user_id' => $user->id,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/posts', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'title' => 'Test title',
                'published' => true,
            ]);
    }

    /**
     * Test showing a post.
     */
    public function test_can_show_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'title', 'body', 'published', 'user_id'],
            ]);
    }

    /**
     * Test updating a post.
     */
    public function test_can_update_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $data = [
            'title' => 'Updated title',
            'body' => 'Updated body content',
            'published' => false,
            'user_id' => $user->id,
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/posts/{$post->id}", $data);

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'Updated title',
                'published' => false,
            ]);
    }

    /**
     * Test deleting a post.
     */
    public function test_can_delete_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
