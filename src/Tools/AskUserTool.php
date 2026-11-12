<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets the model ask the user a clarifying question and wait for the answer.
 *
 * Without a structured way to ask, a model that needs input either asks in
 * plain text and keeps going, or — when it was trained in an agent harness
 * that has a native question tool — calls a tool that doesn't exist, which
 * aborts the turn. This tool always pauses the run through laravel/ai's
 * human-approval mechanism, so generation stops deterministically. The chat
 * shows the question with its options; the user's answer resumes the same
 * turn as an edited approval decision and is returned as the tool result.
 */
class AskUserTool extends BaseTool implements Approvable
{
    use InteractsWithApprovals;

    public const NAME = 'ask_user';

    public const MAX_OPTIONS = 6;

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Ask the user a clarifying question and wait for their answer. Use this whenever you need '
            .'information, a choice or a confirmation from the user before you can continue, instead of '
            .'asking in plain text. Ask exactly one short, specific question per call and do not call other '
            .'tools in the same step. Offer 2 to '.self::MAX_OPTIONS.' short answer options when the likely '
            .'answers are known; the user can always answer in their own words. The conversation pauses until '
            .'the user answers, and their answer is returned as the result of this tool.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('The question to ask the user, in the language of the conversation.')
                ->required(),
            'options' => $schema->array()
                ->items($schema->string())
                ->description('Optional short answers the user can pick with one click (2 to '.self::MAX_OPTIONS.').'),
        ];
    }

    /**
     * Always pause: the question itself is the "reason" shown to the user.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required(static::questionFrom($request->toArray()));
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->toArray();
        $question = static::questionFrom($arguments);
        $answer = trim((string) ($arguments['answer'] ?? ''));

        if (isset($this->panelId, $this->user)) {
            $this->audit(AuditAction::ActionExecuted, null, null, [
                'tool' => self::NAME,
                'question' => $question,
                'answered' => $answer !== '',
            ]);
        }

        if ($answer === '') {
            return 'The user did not answer the question. Do not assume an answer: continue without it, or ask again only if you cannot proceed.';
        }

        return "The user answered: {$answer}";
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function questionFrom(array $arguments): string
    {
        return trim((string) ($arguments['question'] ?? ''));
    }

    /**
     * The answer options the model offered, cleaned up for display.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, string>
     */
    public static function optionsFrom(array $arguments): array
    {
        $options = $arguments['options'] ?? [];

        // Some models send the options as one comma separated string.
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : explode(',', $options);
        }

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->filter(fn ($option) => is_scalar($option))
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn (string $option) => $option !== '')
            ->unique()
            ->take(self::MAX_OPTIONS)
            ->values()
            ->all();
    }
}
