<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Commands;

use Illuminate\Console\Command;
use Filament\Support\Commands\Concerns\CanOpenUrlInBrowser;

class InstallCommand extends Command
{
    use CanOpenUrlInBrowser;

    protected $signature = 'filament-copilot:install';

    protected $description = 'Install the Filament Copilot package.';

    protected const GITHUB_URL = 'https://github.com/eslam-reda-div/filament-copilot';

    public function handle(): int
    {
        $this->newLine();
        $this->info('🚀 Welcome to Filament Copilot Installer');
        $this->line('This wizard will guide you through setting up the AI-powered copilot for your Filament panels.');
        $this->newLine(2);

        // Step 1
        $this->section('Step 1/8 — Publishing configuration');

        $this->line('⏳ Publishing config file...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'filament-copilot-config',
        ]);

        $this->info('✓ Config file published to config/filament-copilot.php');

        // Step 2
        $this->section('Step 2/8 — Publishing assets');

        $this->line('⏳ Publishing JS and CSS assets...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'filament-copilot-assets',
        ]);

        $this->info('✓ Assets published to public/vendor/filament-copilot/');

        // Step 3
        $this->section('Step 3/8 — Database setup');

        $this->line('⏳ Publishing migration files...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'filament-copilot-migrations',
        ]);

        $this->info('✓ Migration files published.');

        $this->newLine();

        $runMigrations = $this->confirm(
            'Would you like to run database migrations now?',
            true
        );

        if ($runMigrations) {
            $this->line('⏳ Running database migrations...');
            $this->callSilently('migrate');
            $this->info('✓ Migrations completed successfully.');
        } else {
            $this->warn('⚠ Remember to run "php artisan migrate" before using Filament Copilot.');
        }

        // Step 4
        $this->section('Step 4/8 — Laravel AI SDK Configuration');

        $this->line('Filament Copilot uses the official laravel/ai SDK.');
        $this->line('Let\'s configure your AI provider.');
        $this->newLine();

        $publishAiConfig = $this->confirm(
            'Would you like to publish the laravel/ai config file (config/ai.php)?',
            true
        );

        if ($publishAiConfig) {

            $this->line('⏳ Publishing laravel/ai config...');

            $this->callSilently('vendor:publish', [
                '--tag' => 'ai-config',
            ]);

            $this->info('✓ Laravel AI SDK config published to config/ai.php.');
        }

        // Step 5
        $this->section('Step 5/8 — Choose your AI provider');

        $providers = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'groq' => 'Groq',
            'xai' => 'xAI',
            'deepseek' => 'DeepSeek',
            'mistral' => 'Mistral',
            'ollama' => 'Ollama (local models)',
        ];

        $this->line('Supported AI Providers:');
        $this->newLine();

        $providerChoice = $this->choice(
            'Select your AI provider',
            array_values($providers),
            0
        );

        $provider = array_search($providerChoice, $providers);

        // Model defaults
        $defaultModels = [
            'openai' => 'gpt-4o',
            'anthropic' => 'claude-sonnet-4',
            'gemini' => 'gemini-2.0-flash',
            'groq' => 'llama-3.3-70b-versatile',
            'xai' => 'grok-3',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-large-latest',
            'ollama' => 'llama3',
        ];

        $modelSuggestions = [
            'openai' => ['gpt-4o', 'gpt-4o-mini', 'o3', 'o4-mini'],
            'anthropic' => ['claude-sonnet-4', 'claude-opus-4', 'claude-haiku-4'],
            'gemini' => ['gemini-2.0-flash', 'gemini-2.5-pro', 'gemini-2.5-flash'],
            'groq' => ['llama-3.3-70b-versatile', 'mixtral-8x7b'],
            'xai' => ['grok-3', 'grok-3-mini'],
            'deepseek' => ['deepseek-chat', 'deepseek-reasoner'],
            'mistral' => ['mistral-large-latest', 'codestral-latest'],
            'ollama' => ['llama3', 'mistral', 'codellama', 'phi3'],
        ];

        // Step 6
        $this->section('Step 6/8 — Choose your AI model');

        $this->line("Popular models for {$provider}:");
        $this->newLine();

        foreach ($modelSuggestions[$provider] as $modelName) {
            $this->line(" • {$modelName}");
        }

        $this->newLine();

        $modelChoice = $this->choice(
            'Select a model',
            array_merge($modelSuggestions[$provider], ['Custom model']),
            $defaultModels[$provider]
        );

        if ($modelChoice === 'Custom model') {
            $model = $this->ask(
                'Enter the model name',
                $defaultModels[$provider]
            );
        } else {
            $model = $modelChoice;
        }

        // Step 7
        $this->section('Step 7/8 — API key configuration');

        $envKeyMap = [
            'openai' => 'OPENAI_API_KEY',
            'anthropic' => 'ANTHROPIC_API_KEY',
            'gemini' => 'GEMINI_API_KEY',
            'groq' => 'GROQ_API_KEY',
            'xai' => 'XAI_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'mistral' => 'MISTRAL_API_KEY',
            'ollama' => null,
        ];

        $envKey = $envKeyMap[$provider] ?? null;

        if ($provider === 'ollama') {

            $this->info('✓ Ollama runs locally — no API key required.');
            $this->line('Make sure Ollama is running:');
            $this->line('ollama serve');

        } else {

            $this->line("You'll need an API key from {$provider}.");
            $this->line('You can leave it empty and configure it later.');
            $this->newLine();

            $apiKey = $this->ask("Enter your {$provider} API key");

            if (!empty($apiKey)) {

                $this->addEnvVariable((string) $envKey, $apiKey);
                $this->info("✓ {$envKey} added to your .env file.");

            } else {

                $this->warn("⚠ Don't forget to add {$envKey}=your-key to your .env file.");
            }

            $this->addEnvVariable('COPILOT_PROVIDER', $provider);
            $this->addEnvVariable('COPILOT_MODEL', $model);
        }

        // Update config
        $configPath = config_path('filament-copilot.php');

        if (file_exists($configPath)) {

            $config = file_get_contents($configPath);

            $config = preg_replace(
                "/('provider'\s*=>\s*env\('COPILOT_PROVIDER',\s*')([^']*)('\))/",
                "'provider' => env('COPILOT_PROVIDER', '{$provider}')",
                $config
            );

            file_put_contents($configPath, $config);
        }

        // Step 8
        $this->section('Step 8/8 — Setup complete');

        $this->table(
            ['Setting', 'Value'],
            [
                ['AI Provider', $provider],
                ['AI Model', $model],
                ['API Key', $envKey ? (!empty($apiKey ?? '') ? 'Configured' : 'Not set') : 'Not required'],
                ['Environment', '.env updated'],
                ['Config File', 'config/filament-copilot.php'],
                ['Migrations', $runMigrations ? 'Executed' : 'Pending'],
            ]
        );

        $this->newLine();

        $this->info('🎉 Filament Copilot installation completed successfully!');
        $this->line('Your AI-powered admin panel is now ready.');

        $this->newLine();

        $this->line('If you enjoy this package please consider supporting it ⭐');
        $this->line(self::GITHUB_URL);

        $this->newLine();

        $starRepo = $this->confirm(
            'Would you like to open the GitHub repository now?',
            true
        );

        if ($starRepo) {
            $this->openUrlInBrowser(self::GITHUB_URL);
            $this->info('Thank you for your support! ⭐');
        }

        return self::SUCCESS;
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->info($title);
        $this->line(str_repeat('─', 60));
        $this->newLine();
    }

    protected function addEnvVariable(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);
        $quotedValue = $this->quoteEnvValue($value);
        $line = "{$key}={$quotedValue}";

        if (preg_match("/^{$key}=/m", $envContent)) {
            $envContent = preg_replace_callback(
                "/^{$key}=.*/m",
                fn () => $line,
                $envContent,
                1
            );
        } else {
            $envContent .= PHP_EOL . $line;
        }

        file_put_contents($envPath, $envContent);
    }

    private function quoteEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\\\\$]/', $value)) {
            return '"' . addcslashes($value, '"\\$') . '"';
        }

        return $value;
    }
}