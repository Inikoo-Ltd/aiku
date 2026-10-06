<?php

namespace App\Actions\Maintenance\Comms;

use App\Actions\Comms\Email\GetEmailSocialIcons;
use App\Models\Comms\EmailTemplate;
use App\Models\Helpers\Snapshot;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEmailSocialIcons
{
    use AsAction;

    private const string BEEFREE_ICON_PATH = '/public/resources/social-networks-icon-sets/';

    private const string BEEFREE_ICON_PATTERN = '~^https?://[^/]+/public/resources/social-networks-icon-sets/([^/]+)/([^/@]+)@2x\.png$~';

    /**
     * @var array<string, string|null> beefree icon url => own icon url, null when the icon is not in our seeded set
     */
    private array $ownIconUrls = [];

    /**
     * @return array{replaced: int, missing: array<int, string>}
     */
    public function handle(Snapshot|EmailTemplate $model): array
    {
        $this->ownIconUrls = [];
        $socialIcons       = GetEmailSocialIcons::run($model->group);

        $layout       = $this->replaceIcons($model->layout ?? [], $socialIcons);
        $replacements = array_filter($this->ownIconUrls);

        if ($replacements) {
            $model->updateQuietly(array_filter([
                'layout'          => $layout,
                'checksum'        => $model instanceof Snapshot ? md5(json_encode($layout)) : null,
                'compiled_layout' => $model->compiled_layout === null
                    ? null
                    : str_replace(array_keys($replacements), array_values($replacements), $model->compiled_layout),
            ], fn ($value) => $value !== null));
        }

        return [
            'replaced' => count($replacements),
            'missing'  => array_keys(array_diff_key($this->ownIconUrls, $replacements)),
        ];
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<string, string>  $socialIcons
     *
     * @return array<mixed>
     */
    private function replaceIcons(array $node, array $socialIcons): array
    {
        $iconSrc = $node['image']['src'] ?? null;
        if (is_string($iconSrc) && preg_match(self::BEEFREE_ICON_PATTERN, $iconSrc, $match) && $this->ownIconUrl($iconSrc, $socialIcons)) {
            $node['iconSet'] = $match[1];
            $node['name']    ??= $match[2];
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->replaceIcons($value, $socialIcons);
            } elseif (is_string($value)) {
                $node[$key] = $this->ownIconUrl($value, $socialIcons) ?? $value;
            }
        }

        return $node;
    }

    /**
     * @param  array<string, string>  $socialIcons
     */
    private function ownIconUrl(string $url, array $socialIcons): ?string
    {
        if (!preg_match(self::BEEFREE_ICON_PATTERN, $url, $match)) {
            return null;
        }

        return $this->ownIconUrls[$url] ??= $socialIcons["$match[1]/$match[2]"] ?? null;
    }

    public string $commandSignature = 'repair:email-social-icons';

    public string $commandDescription = 'Replace Beefree social icon urls in email snapshots and email templates with our own seeded icons';

    public function asCommand(Command $command): int
    {
        if (GetEmailSocialIcons::run(group()) === []) {
            $command->error('No social icons stored yet, run group:seed_email_social_icons first');

            return 1;
        }

        $queries = [
            'email snapshots' => Snapshot::where('parent_type', 'Email'),
            'email templates' => EmailTemplate::query(),
        ];

        foreach ($queries as $label => $query) {
            $query->whereRaw('layout::text LIKE ?', ['%'.self::BEEFREE_ICON_PATH.'%']);

            $total    = $query->clone()->count();
            $replaced = 0;
            $missing  = [];

            foreach ($query->lazyById() as $model) {
                $result   = $this->handle($model);
                $replaced += $result['replaced'];
                $missing  = array_merge($missing, $result['missing']);
            }

            $command->info("$label: $total checked, $replaced icon urls replaced");
            foreach (array_unique($missing) as $url) {
                $command->warn("  not in our icon set, left as is: $url");
            }
        }

        return 0;
    }
}
