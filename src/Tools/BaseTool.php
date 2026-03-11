<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Events\CopilotToolExecuted;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Tools\Concerns\LogsAudit;
use EslamRedaDiv\FilamentCopilot\Tools\Concerns\ValidatesAuthorization;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Tool;

abstract class BaseTool implements Tool
{
    use LogsAudit;
    use ValidatesAuthorization;

    protected string $panelId;

    protected Model $user;

    protected ?Model $tenant = null;

    public function forPanel(string $panelId): static
    {
        $this->panelId = $panelId;

        return $this;
    }

    public function forUser(Model $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function forTenant(?Model $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Dispatch a CopilotToolExecuted event for this tool.
     */
    protected function dispatchToolExecuted(string $toolName, string $result, ?string $messageId = null, ?array $input = null): void
    {
        $toolCall = new CopilotToolCall([
            'message_id' => $messageId,
            'tool_name' => $toolName,
            'input' => $input ?? [],
            'output' => $result,
            'status' => 'completed',
        ]);

        event(new CopilotToolExecuted(
            toolCall: $toolCall,
            toolName: $toolName,
            result: $result,
        ));
    }

    /**
     * Get the table column names defined in a Filament resource.
     *
     * @return list<string>
     */
    protected function getTableColumnNames(string $resourceClass): array
    {
        try {
            /** @phpstan-ignore argument.type */
            $table = $resourceClass::table(Table::make(app(\Filament\Tables\Contracts\HasTable::class)));
            $columns = $table->getColumns();

            return array_keys($columns);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve eager-load relationships and withCount aggregates from table columns.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function resolveEagerLoads(string $resourceClass): array
    {
        $columnNames = $this->getTableColumnNames($resourceClass);
        $relations = [];
        $withCounts = [];

        foreach ($columnNames as $colName) {
            if (str_contains($colName, '.')) {
                $relations[] = explode('.', $colName, 2)[0];
            } elseif (str_ends_with($colName, '_count')) {
                $withCounts[] = substr($colName, 0, -6);
            }
        }

        return [array_values(array_unique($relations)), array_values(array_unique($withCounts))];
    }

    /**
     * Summarize a record using the resource's table column definitions.
     * Falls back to all model attributes if table columns cannot be resolved.
     */
    protected function summarizeRecord(Model $record, string $resourceClass): string
    {
        $columnNames = $this->getTableColumnNames($resourceClass);
        $attributes = $record->toArray();

        if (empty($columnNames)) {
            // Fallback: use all model attributes
            $summary = [];
            foreach ($attributes as $key => $value) {
                if (is_array($value) || is_null($value)) {
                    continue;
                }
                $display = is_string($value) ? mb_substr((string) $value, 0, 80) : $value;
                $summary[] = "{$key}: {$display}";
            }

            return implode(', ', $summary);
        }

        $summary = [];
        foreach ($columnNames as $colName) {
            // Handle relationship columns (e.g. "company.name")
            if (str_contains($colName, '.')) {
                $value = data_get($attributes, $colName)
                    ?? data_get($record, $colName);
            } else {
                $value = $attributes[$colName] ?? null;
            }

            // Handle count columns (e.g. "products_count")
            if (is_null($value) && str_ends_with($colName, '_count')) {
                $value = $attributes[$colName] ?? null;
            }

            if (is_array($value) || is_null($value)) {
                continue;
            }

            $display = is_string($value) ? mb_substr((string) $value, 0, 80) : $value;
            $summary[] = "{$colName}: {$display}";
        }

        return implode(', ', $summary);
    }
}
