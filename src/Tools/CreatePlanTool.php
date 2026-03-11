<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Agent\PlanningEngine;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreatePlanTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Create a multi-step execution plan for a complex task. The plan will be presented to the user for approval before any steps are executed. Use this when a task requires multiple coordinated actions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->string()->description('The conversation ID to attach the plan to')->required(),
            'description' => $schema->string()->description('A clear description of what this plan will accomplish')->required(),
            'steps' => $schema->string()->description('JSON array of step objects, each with a "description" key explaining what the step does (e.g. [{"description":"Fetch all users"},{"description":"Export to CSV"}])')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $conversationId = (string) $request['conversation_id'];
        $description = (string) $request['description'];
        $stepsJson = (string) $request['steps'];

        $conversation = CopilotConversation::find($conversationId);

        if (! $conversation) {
            return 'Conversation not found. Cannot create plan without an active conversation.';
        }

        $steps = json_decode($stepsJson, true);

        if (! is_array($steps) || empty($steps)) {
            return 'Invalid steps format. Please provide a JSON array of step objects with "description" keys.';
        }

        $this->audit(AuditAction::ActionExecuted, null, null, [
            'tool' => 'create_plan',
            'description' => $description,
            'steps_count' => count($steps),
        ]);

        /** @var PlanningEngine $planningEngine */
        $planningEngine = app(PlanningEngine::class);

        $plan = $planningEngine->propose(
            conversation: $conversation,
            content: $description,
            steps: $steps,
        );

        $stepsList = collect($steps)
            ->map(fn (array $step, int $i) => ($i + 1).'. '.($step['description'] ?? 'Step '.($i + 1)))
            ->implode("\n");

        return "Plan created and proposed for user approval.\n\nPlan: {$description}\n\nSteps:\n{$stepsList}\n\nWaiting for user to approve or reject the plan before execution.";
    }
}
