<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SiteAudit;

class RobotsTxt
{
    /**
     * @param  array<int, array{allow: bool, pattern: string}>  $rules
     * @param  array<int, string>  $sitemaps
     */
    private function __construct(
        private readonly array $rules,
        private readonly array $sitemaps
    ) {
    }

    public static function allowAll(): self
    {
        return new self([], []);
    }

    public static function parse(string $content, string $userAgentToken): self
    {
        $groups         = [];
        $sitemaps       = [];
        $currentAgents  = [];
        $isReadingRules = false;

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));

            if (!str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                $sitemaps[] = $value;

                continue;
            }

            if ($field === 'user-agent') {
                if ($isReadingRules) {
                    $currentAgents  = [];
                    $isReadingRules = false;
                }

                $currentAgents[] = strtolower($value);
                $groups[strtolower($value)] ??= [];

                continue;
            }

            if (!in_array($field, ['allow', 'disallow'], true) || $currentAgents === []) {
                continue;
            }

            $isReadingRules = true;

            if ($value === '') {
                continue;
            }

            foreach ($currentAgents as $agent) {
                $groups[$agent][] = ['allow' => $field === 'allow', 'pattern' => $value];
            }
        }

        $token = strtolower($userAgentToken);
        $rules = null;

        foreach ($groups as $agent => $agentRules) {
            if ($agent !== '*' && str_contains($token, $agent)) {
                $rules = $agentRules;

                break;
            }
        }

        return new self($rules ?? $groups['*'] ?? [], $sitemaps);
    }

    public function isAllowed(string $path): bool
    {
        $matchedLength = -1;
        $isAllowed     = true;

        foreach ($this->rules as $rule) {
            if (!$this->matches($rule['pattern'], $path)) {
                continue;
            }

            $length = strlen($rule['pattern']);

            if ($length > $matchedLength || ($length === $matchedLength && $rule['allow'])) {
                $matchedLength = $length;
                $isAllowed     = $rule['allow'];
            }
        }

        return $isAllowed;
    }

    /**
     * @return array<int, string>
     */
    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    private function matches(string $pattern, string $path): bool
    {
        $isAnchored = str_ends_with($pattern, '$');
        $regex      = str_replace('\*', '.*', preg_quote(rtrim($pattern, '$'), '/'));

        return (bool) preg_match('/^'.$regex.($isAnchored ? '$' : '').'/', $path);
    }
}
