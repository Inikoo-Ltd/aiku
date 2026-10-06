<?php

namespace App\Actions\Maintenance\Comms;

use App\Actions\Comms\Email\GetEmailSocialIcons;
use App\Models\Comms\Email;
use App\Models\Comms\EmailTemplate;
use App\Models\Helpers\Snapshot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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

    public string $commandDescription = 'Replace Beefree social icon urls in the live and unpublished email snapshots and in email templates with our own seeded icons';

    public function asCommand(Command $command): int
    {
        if (GetEmailSocialIcons::run(group()) === []) {
            $command->error('No social icons stored yet, run group:seed_email_social_icons first');

            return 1;
        }

        $replaced = 0;
        $missing  = [];
        $repair   = function (Snapshot|EmailTemplate $model) use (&$replaced, &$missing) {
            $result   = $this->handle($model);
            $replaced += $result['replaced'];
            $missing  = array_merge($missing, $result['missing']);
        };

        $command->info('Email snapshots in use');
        $progressBar = $command->getOutput()->createProgressBar(Email::count());
        Email::select(['id', 'live_snapshot_id', 'unpublished_snapshot_id'])->chunkById(500, function ($emails) use ($repair, $progressBar) {
            $this->snapshotsWithBeefreeIcons($emails->pluck('live_snapshot_id')->merge($emails->pluck('unpublished_snapshot_id'))->filter()->unique()->all())
                ->each($repair);
            $progressBar->advance($emails->count());
        });
        $progressBar->finish();
        $command->newLine();

        $command->info('Email templates');
        EmailTemplate::whereRaw('layout::text LIKE ?', ['%'.self::BEEFREE_ICON_PATH.'%'])->lazyById()->each($repair);

        $command->info("Done: $replaced icon urls replaced");
        foreach (array_unique($missing) as $url) {
            $command->warn("Not in our icon set, left as is: $url");
        }

        return 0;
    }

    /**
     * @param  array<int, int>  $snapshotIds
     *
     * @return Collection<int, Snapshot>
     */
    private function snapshotsWithBeefreeIcons(array $snapshotIds): Collection
    {
        return Snapshot::whereIn('id', $snapshotIds)
            ->whereRaw('layout::text LIKE ?', ['%'.self::BEEFREE_ICON_PATH.'%'])
            ->get();
    }
}
