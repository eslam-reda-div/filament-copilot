<?php

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;
use EslamRedaDiv\FilamentCopilot\Discovery\PageInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\ResourceInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\WidgetInspector;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Pages\CopilotDashboardPage;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotAuditLogs\CopilotAuditLogResource;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\CopilotConversationResource;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\CopilotRateLimitResource;
use EslamRedaDiv\FilamentCopilot\Tools\GetToolsTool;
use EslamRedaDiv\FilamentCopilot\Tools\RunToolTool;
use Filament\Facades\Filament;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Laravel\Ai\Tools\Request as ToolRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecurityPanelStub
{
    public function __construct(
        protected string $id,
        protected array $resources = [],
        protected array $pages = [],
        protected array $widgets = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function getWidgets(): array
    {
        return $this->widgets;
    }
}

class SecurityGuardStub
{
    public function __construct(protected $user) {}

    public function user()
    {
        return $this->user;
    }
}

class SecurityFilamentManagerStub
{
    protected ?SecurityPanelStub $currentPanel = null;

    /** @var array<string, SecurityPanelStub> */
    protected array $panels = [];

    public function __construct(
        protected SecurityGuardStub $guard,
        SecurityPanelStub $panel,
        protected FilamentCopilotPlugin $plugin,
        protected $tenant = null,
    ) {
        $this->currentPanel = $panel;
        $this->panels[$panel->getId()] = $panel;
    }

    public function getPlugin(string $pluginId): FilamentCopilotPlugin
    {
        if ($pluginId !== $this->plugin->getId()) {
            throw new RuntimeException("Plugin '{$pluginId}' not found.");
        }

        return $this->plugin;
    }

    public function auth(): SecurityGuardStub
    {
        return $this->guard;
    }

    public function setCurrentPanel(string $panelId): void
    {
        if (! isset($this->panels[$panelId])) {
            throw new RuntimeException('Panel not found.');
        }

        $this->currentPanel = $this->panels[$panelId];
    }

    public function getCurrentPanel(): ?SecurityPanelStub
    {
        return $this->currentPanel;
    }

    public function getPanel(string $panelId): ?SecurityPanelStub
    {
        return $this->panels[$panelId] ?? null;
    }

    public function getTenant()
    {
        return $this->tenant;
    }
}

class SecurityAllowedResource implements CopilotResource
{
    public static function canAccess(): bool
    {
        return true;
    }

    public static function getModelLabel(): string
    {
        return 'Allowed Resource';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Allowed Resources';
    }

    public static function getSlug(): string
    {
        return 'allowed-resources';
    }

    public static function copilotResourceDescription(): ?string
    {
        return 'Allowed resource for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityDeniedResource implements CopilotResource
{
    public static function canAccess(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'Denied Resource';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Denied Resources';
    }

    public static function getSlug(): string
    {
        return 'denied-resources';
    }

    public static function copilotResourceDescription(): ?string
    {
        return 'Denied resource for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityAllowedPage implements CopilotPage
{
    public static function canAccess(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Allowed Page';
    }

    public static function getSlug(): string
    {
        return 'allowed-page';
    }

    public static function copilotPageDescription(): ?string
    {
        return 'Allowed page for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityDeniedPage implements CopilotPage
{
    public static function canAccess(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Denied Page';
    }

    public static function getSlug(): string
    {
        return 'denied-page';
    }

    public static function copilotPageDescription(): ?string
    {
        return 'Denied page for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityAllowedWidget implements CopilotWidget
{
    public static function canView(): bool
    {
        return true;
    }

    public static function copilotWidgetDescription(): ?string
    {
        return 'Allowed widget for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityDeniedWidget implements CopilotWidget
{
    public static function canView(): bool
    {
        return false;
    }

    public static function copilotWidgetDescription(): ?string
    {
        return 'Denied widget for tests';
    }

    public static function copilotTools(): array
    {
        return [new SecurityNoopTool];
    }
}

class SecurityNoopTool implements \Laravel\Ai\Contracts\Tool
{
    public function description(): string
    {
        return 'Noop test tool';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(ToolRequest $request): string
    {
        return 'ok';
    }
}

function swapFilamentForAuthorizationTest($user, SecurityPanelStub $panel, ?FilamentCopilotPlugin $plugin = null): void
{
    $plugin ??= new FilamentCopilotPlugin;

    $manager = new SecurityFilamentManagerStub(
        new SecurityGuardStub($user),
        $panel,
        $plugin,
        null,
    );

    app()->instance('filament', $manager);
    Filament::swap($manager);
}

it('enforces authorizeUsing callback in stream controller', function () {
    $user = createTestUser();

    $plugin = (new FilamentCopilotPlugin)
        ->authorizeUsing(fn ($authUser): bool => false);

    $panel = new SecurityPanelStub('admin');
    swapFilamentForAuthorizationTest($user, $panel, $plugin);

    $request = Request::create('/copilot/stream', 'POST', [
        'message' => 'hello',
        'panel_id' => 'admin',
    ]);

    try {
        app(StreamController::class)->stream($request);
        $this->fail('Expected HTTP 403 when authorizeUsing callback denies access.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('filters unauthorized components from discovery inspectors', function () {
    $plugin = (new FilamentCopilotPlugin)
        ->respectAuthorization(true);

    $panel = new SecurityPanelStub(
        id: 'admin',
        resources: [SecurityAllowedResource::class, SecurityDeniedResource::class],
        pages: [SecurityAllowedPage::class, SecurityDeniedPage::class],
        widgets: [SecurityAllowedWidget::class, SecurityDeniedWidget::class],
    );

    swapFilamentForAuthorizationTest(null, $panel, $plugin);

    $resources = app(ResourceInspector::class)->discoverResources();
    $pages = app(PageInspector::class)->discoverPages();
    $widgets = app(WidgetInspector::class)->discoverWidgets();

    expect(array_column($resources, 'resource'))
        ->toContain(SecurityAllowedResource::class)
        ->not->toContain(SecurityDeniedResource::class);

    expect(array_column($pages, 'page'))
        ->toContain(SecurityAllowedPage::class)
        ->not->toContain(SecurityDeniedPage::class);

    expect(array_column($widgets, 'widget'))
        ->toContain(SecurityAllowedWidget::class)
        ->not->toContain(SecurityDeniedWidget::class);
});

it('blocks unauthorized classes in get_tools and run_tool', function () {
    $user = createTestUser();

    $plugin = (new FilamentCopilotPlugin)
        ->respectAuthorization(true);

    swapFilamentForAuthorizationTest($user, new SecurityPanelStub('admin'), $plugin);

    $getTools = new GetToolsTool;
    $runTool = (new RunToolTool)
        ->forPanel('admin')
        ->forUser($user);

    $deniedGet = (string) $getTools->handle(new ToolRequest([
        'source_class' => SecurityDeniedResource::class,
    ]));

    $deniedRun = (string) $runTool->handle(new ToolRequest([
        'source_class' => SecurityDeniedResource::class,
        'tool_class' => SecurityNoopTool::class,
        'arguments' => '{}',
    ]));

    $allowedRun = (string) $runTool->handle(new ToolRequest([
        'source_class' => SecurityAllowedResource::class,
        'tool_class' => SecurityNoopTool::class,
        'arguments' => '{}',
    ]));

    expect($deniedGet)->toContain('Access denied')
        ->and($deniedRun)->toContain('Access denied')
        ->and($allowedRun)->toBe('ok');
});

it('enforces management guard on management resources and dashboard', function () {
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $user = createTestUser();

    $plugin = (new FilamentCopilotPlugin)
        ->managementGuard('admin');

    swapFilamentForAuthorizationTest($user, new SecurityPanelStub('admin'), $plugin);

    auth()->guard('web')->setUser($user);

    expect(CopilotConversationResource::canAccess())->toBeFalse()
        ->and(CopilotAuditLogResource::canAccess())->toBeFalse()
        ->and(CopilotRateLimitResource::canAccess())->toBeFalse()
        ->and(CopilotDashboardPage::canAccess())->toBeFalse();

    auth()->guard('web')->logout();
    auth()->guard('admin')->setUser($user);

    expect(CopilotConversationResource::canAccess())->toBeTrue()
        ->and(CopilotAuditLogResource::canAccess())->toBeTrue()
        ->and(CopilotRateLimitResource::canAccess())->toBeTrue()
        ->and(CopilotDashboardPage::canAccess())->toBeTrue();
});
