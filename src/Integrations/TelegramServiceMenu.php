<?php
declare(strict_types=1);

namespace RouteBox\Integrations;

/** Builds the service-selection keyboard without provider-specific Telegram code. */
final class TelegramServiceMenu
{
    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function keyboard(string $lang = 'fa'): array
    {
        $rows = [];
        foreach ($this->catalog->categories(true) as $category) {
            $label = $lang === 'fa' ? (string)$category['name_fa'] : (string)$category['name_en'];
            $rows[] = [[
                'text' => trim((string)$category['icon'].' '.$label),
                'callback_data' => 'svc:category:'.(int)$category['id'],
            ]];
        }
        return $rows;
    }

    public function planKeyboard(int $categoryId, string $lang = 'fa'): array
    {
        $rows = [];
        foreach ($this->catalog->plans($categoryId, true) as $plan) {
            $label = $lang === 'fa' ? (string)$plan['display_name_fa'] : (string)$plan['display_name_en'];
            $rows[] = [[
                'text' => '📦 '.$label,
                'callback_data' => 'svc:plan:'.(int)$plan['id'],
            ]];
        }
        return $rows;
    }
}
