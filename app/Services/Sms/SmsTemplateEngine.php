<?php

namespace App\Services\Sms;

class SmsTemplateEngine
{
    /**
     * Render template with data
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    public function render(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }
        return $template;
    }

    /**
     * Extract variables from template
     *
     * @param string $template
     * @return array
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        return array_map('trim', $matches[1] ?? []);
    }
}