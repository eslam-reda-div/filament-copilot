<?php

use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Models\Conversation as AiConversation;
use Laravel\Ai\Models\ConversationMessage as AiConversationMessage;

beforeEach(function () {
    $this->loadMigrationsFrom(dirname((new ReflectionClass(AiServiceProvider::class))->getFileName(), 2).'/database/migrations');
});

/**
 * Store a laravel/ai conversation with a couple of messages for the given
 * participant. The message columns differ between laravel/ai lines (1.x
 * replaced tool_calls / tool_results / approval_state with steps and
 * status), so only the columns the installed schema has are filled.
 */
function storeAiConversationForDeleteAiConversationTest($participant): string
{
    $id = (string) Str::uuid7();

    AiConversation::query()->create([
        'id' => $id,
        'participant_type' => $participant->getMorphClass(),
        'participant_id' => $participant->getKey(),
        'title' => 'Site report',
    ]);

    $table = (new AiConversationMessage)->getTable();
    $jsonColumns = array_filter(
        ['attachments', 'tool_calls', 'tool_results', 'steps', 'usage', 'meta'],
        fn (string $column) => Schema::hasColumn($table, $column),
    );

    foreach (['user', 'assistant'] as $role) {
        AiConversationMessage::query()->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $id,
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'agent' => 'CopilotAgent',
            'role' => $role,
            'content' => "A {$role} message",
            ...array_fill_keys($jsonColumns, '[]'),
            ...(Schema::hasColumn($table, 'status') ? ['status' => 'completed'] : []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}

function copilotConversationForDeleteAiConversationTest($participant, ?string $aiConversationId): CopilotConversation
{
    $conversation = app(ConversationManager::class)->create($participant, 'admin');

    if ($aiConversationId !== null) {
        $conversation->update(['metadata' => ['ai_conversation_id' => $aiConversationId]]);
    }

    return $conversation;
}

function aiMessageCountForDeleteAiConversationTest(string $aiConversationId): int
{
    return AiConversationMessage::query()->where('conversation_id', $aiConversationId)->count();
}

it('deletes the laravel/ai conversation and its messages with the copilot conversation', function () {
    $user = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($user);
    $conversation = copilotConversationForDeleteAiConversationTest($user, $aiConversationId);

    expect(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(2);

    app(ConversationManager::class)->delete($conversation);

    expect(CopilotConversation::find($conversation->id))->toBeNull()
        ->and(AiConversation::find($aiConversationId))->toBeNull()
        ->and(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(0);
});

it('also cleans up when the conversation is deleted directly, as the management resource does', function () {
    $user = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($user);

    copilotConversationForDeleteAiConversationTest($user, $aiConversationId)->delete();

    expect(AiConversation::find($aiConversationId))->toBeNull()
        ->and(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(0);
});

it('leaves other laravel/ai conversations alone', function () {
    $user = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($user);
    $otherAiConversationId = storeAiConversationForDeleteAiConversationTest($user);

    app(ConversationManager::class)->delete(copilotConversationForDeleteAiConversationTest($user, $aiConversationId));

    expect(AiConversation::find($otherAiConversationId))->not->toBeNull()
        ->and(aiMessageCountForDeleteAiConversationTest($otherAiConversationId))->toBe(2);
});

it('does not delete a laravel/ai conversation that belongs to someone else', function () {
    $owner = createTestUser();
    $otherUser = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($owner);

    app(ConversationManager::class)->delete(copilotConversationForDeleteAiConversationTest($otherUser, $aiConversationId));

    expect(AiConversation::find($aiConversationId))->not->toBeNull()
        ->and(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(2);
});

it('deletes conversations that never reached the AI, or whose AI conversation is already gone', function () {
    $user = createTestUser();
    $neverSent = copilotConversationForDeleteAiConversationTest($user, null);
    $alreadyGone = copilotConversationForDeleteAiConversationTest($user, (string) Str::uuid7());

    app(ConversationManager::class)->delete($neverSent);
    app(ConversationManager::class)->delete($alreadyGone);

    expect(CopilotConversation::query()->count())->toBe(0);
});

it('still deletes the copilot conversation when laravel/ai\'s conversation tables do not exist', function () {
    // An app with its own ConversationStore never creates laravel/ai's tables.
    config()->set('ai.conversations.tables.conversations', 'missing_agent_conversations');
    config()->set('ai.conversations.tables.messages', 'missing_agent_conversation_messages');

    $conversation = copilotConversationForDeleteAiConversationTest(createTestUser(), (string) Str::uuid7());

    app(ConversationManager::class)->delete($conversation);

    expect(CopilotConversation::find($conversation->id))->toBeNull();
});

it('cancels the copilot delete and reports the error when the laravel/ai conversation cannot be deleted', function () {
    Exceptions::fake();

    $user = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($user);
    $conversation = copilotConversationForDeleteAiConversationTest($user, $aiConversationId);

    AiConversation::deleting(fn () => throw new RuntimeException('Delete failed.'));

    // false is what Filament's DeleteAction turns into its failure notification.
    expect($conversation->delete())->toBeFalse();

    expect(CopilotConversation::find($conversation->id))->not->toBeNull()
        ->and(AiConversation::find($aiConversationId))->not->toBeNull();

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Delete failed.');
});

it('keeps the transcript when the laravel/ai conversation delete fails after its messages were deleted', function () {
    Exceptions::fake();

    $user = createTestUser();
    $aiConversationId = storeAiConversationForDeleteAiConversationTest($user);
    $conversation = copilotConversationForDeleteAiConversationTest($user, $aiConversationId);

    // The messages are deleted first; the conversation delete then fails.
    AiConversation::deleting(function () use ($aiConversationId) {
        expect(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(0);

        throw new RuntimeException('Delete failed.');
    });

    app(ConversationManager::class)->delete($conversation);

    expect(CopilotConversation::find($conversation->id))->not->toBeNull()
        ->and(AiConversation::find($aiConversationId))->not->toBeNull()
        ->and(aiMessageCountForDeleteAiConversationTest($aiConversationId))->toBe(2);
});

it('keeps the copilot conversation when laravel/ai\'s connection is unavailable', function () {
    Exceptions::fake();

    $user = createTestUser();
    $conversation = copilotConversationForDeleteAiConversationTest($user, (string) Str::uuid7());

    config()->set('database.connections.unavailable_ai_connection', [
        'driver' => 'sqlite',
        'database' => sys_get_temp_dir().'/missing-directory-'.Str::random(8).'/ai.sqlite',
        'prefix' => '',
    ]);
    config()->set('ai.conversations.connection', 'unavailable_ai_connection');

    $deleted = $conversation->delete();

    // Restored before the migrations roll back, which use this connection too.
    config()->set('ai.conversations.connection', null);

    expect($deleted)->toBeFalse()
        ->and(CopilotConversation::find($conversation->id))->not->toBeNull();

    Exceptions::assertReportedCount(1);
});
