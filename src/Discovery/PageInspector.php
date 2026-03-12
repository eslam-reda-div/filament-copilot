<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Discovery;

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use Filament\Facades\Filament;

class PageInspector
{
    /**
     * Discover all pages in the panel that implement CopilotPage.
     */
    public function discoverPages(?string $panelId = null): array
    {
        $panel = $panelId
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();

        if (! $panel) {
            return [];
        }

        $pages = [];

        foreach ($panel->getPages() as $pageClass) {
            if (! is_subclass_of($pageClass, CopilotPage::class)) {
                continue;
            }

            /** @var class-string<\Filament\Pages\Page&CopilotPage> $pageClass */
            $hasTools = false;
            try {
                $description = $pageClass::copilotPageDescription();
                $hasTools = ! empty($pageClass::copilotTools());
            } catch (\Throwable) {
                $description = null;
            }

            /** @var class-string<\Filament\Pages\Page> $pageClass */
            $pages[] = [
                'page' => $pageClass,
                'label' => $pageClass::getNavigationLabel(),
                'slug' => $pageClass::getSlug(),
                'copilot_description' => $description,
                'has_tools' => $hasTools,
            ];
        }

        return $pages;
    }

    /**
     * Build AI-friendly page descriptions for the system prompt.
     */
    public function buildPageContext(?string $panelId = null): string
    {
        $pages = $this->discoverPages($panelId);

        if (empty($pages)) {
            return '';
        }

        $lines = ['## Available Pages'];

        foreach ($pages as $page) {
            $line = '- ' . $page['label'] . ' (' . $page['page'] . ')';

            if (! empty($page['copilot_description'])) {
                $line .= ': ' . $page['copilot_description'];
            }

            if ($page['has_tools']) {
                $line .= ' [has copilot tools]';
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
