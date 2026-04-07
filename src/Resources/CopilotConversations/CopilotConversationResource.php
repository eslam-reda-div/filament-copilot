<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Pages\ListCopilotConversations;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Pages\ViewCopilotConversation;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Schemas\CopilotConversationForm;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Schemas\CopilotConversationInfolist;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Tables\CopilotConversationsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CopilotConversationResource extends Resource
{
    protected static ?string $model = CopilotConversation::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | \UnitEnum | null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.conversations');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.conversation');
    }

    public static function canAccess(): bool
    {
        $guard = FilamentCopilotPlugin::get()->getManagementGuard();

        if ($guard) {
            try {
                return auth()->guard($guard)->check();
            } catch (\Throwable) {
                return false;
            }
        }

        return parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return CopilotConversationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CopilotConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CopilotConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotConversations::route('/'),
            'view' => ViewCopilotConversation::route('/{record}'),
        ];
    }
}
