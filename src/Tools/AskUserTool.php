<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskUserTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Ask the user a question and wait for their response before proceeding. Use this when you need clarification, confirmation, or input from the user before taking an action. The user MUST respond before any further action is taken.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->description('The question to ask the user. Be clear and specific about what you need to know.')->required(),
            'options' => $schema->string()->description('Optional comma-separated list of suggested options for the user to choose from (e.g. "Option A, Option B, Option C")'),
            'context' => $schema->string()->description('Optional context explaining why you are asking this question'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $question = (string) $request['question'];
        $options = $request['options'] !== null ? (string) $request['options'] : null;
        $context = $request['context'] !== null ? (string) $request['context'] : null;

        $this->audit(AuditAction::ActionExecuted, null, null, [
            'tool' => 'ask_user',
            'question' => $question,
        ]);

        $response = [
            'type' => 'ask_user',
            'question' => $question,
        ];

        if ($options) {
            $response['options'] = array_map('trim', explode(',', $options));
        }

        if ($context) {
            $response['context'] = $context;
        }

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
