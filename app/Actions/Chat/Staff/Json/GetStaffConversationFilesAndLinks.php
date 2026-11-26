<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-15h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Chat\Staff\Json;

use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The "Files & links" tab of a V·D or Verbration: pictures, GIFs and web links shared in it, newest or oldest first,
 * never past the moment the person left. Staff messages carry no other files, so media and links are the only kinds.
 * Pages are counted in messages and continued from the last message's id.
 */
class GetStaffConversationFilesAndLinks
{
    use AsAction;

    public const int PER_PAGE = 40;

    public const array KINDS = ['all', 'media', 'links'];

    public const string GIF_PATTERN = '#^https://\S+\.(gif|webp)$#i';

    public const string URL_PATTERN = '#https?://[^\s<>"\'`]+#i';

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffConversation')->canBeAccessedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'kind'   => ['sometimes', 'string', 'in:'.implode(',', self::KINDS)],
            'q'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort'   => ['sometimes', 'string', 'in:newest,oldest'],
            'cursor' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: int|null}
     */
    public function handle(StaffConversation $staffConversation, User $viewer, string $kind = 'all', ?string $search = null, string $sort = 'newest', ?int $cursor = null): array
    {
        $search     = trim((string) $search);
        $newest     = $sort !== 'oldest';
        $wantsMedia = $kind !== 'links';
        $wantsLinks = $kind !== 'media';
        $leftAt     = $staffConversation->leftAtFor($viewer);

        $messages = StaffMessage::where('staff_conversation_id', $staffConversation->id)
            ->whereNull('staff_call_id')
            ->when($leftAt, fn (Builder $query) => $query->where('created_at', '<=', $leftAt))
            ->where(function (Builder $query) use ($wantsMedia, $wantsLinks) {
                if ($wantsMedia) {
                    $query->orWhereNotNull('media_id')->orWhere('body', '~*', '^https://\S+\.(gif|webp)$');
                }
                if ($wantsLinks) {
                    $query->orWhere('body', '~*', 'https?://');
                }
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('body', 'ilike', $like)
                    ->orWhereHas('attachment', fn (Builder $media) => $media->where('name', 'ilike', $like)));
            })
            ->when($cursor, fn (Builder $query) => $query->where('id', $newest ? '<' : '>', $cursor))
            ->with(['user', 'attachment'])
            ->orderBy('id', $newest ? 'desc' : 'asc')
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore  = $messages->count() > self::PER_PAGE;
        $messages = $messages->take(self::PER_PAGE)->values();
        $needle   = mb_strtolower($search);
        $matches  = fn (string $text) => $needle === '' || str_contains(mb_strtolower($text), $needle);

        $items = [];
        foreach ($messages as $message) {
            $body   = (string) $message->body;
            $isGif  = preg_match(self::GIF_PATTERN, $body) === 1;
            $shared = [
                'message_id' => $message->id,
                'sent_at'    => $message->created_at?->toIso8601String(),
                'shared_by'  => $message->user?->chatName(),
            ];

            if ($wantsMedia && $message->media_id && $message->attachment) {
                $name = $message->attachment->name ?: __('Image');
                if ($matches($name) || $matches($body)) {
                    $items[] = $shared + [
                        'key'   => 'image-'.$message->id,
                        'kind'  => 'media',
                        'media' => [
                            'name'      => $name,
                            'thumbnail' => $message->imageSources(120, 120, 'attachment'),
                            'image'     => $message->imageSources(0, 0, 'attachment'),
                            'gif_url'   => null,
                        ],
                    ];
                }
            }

            if ($wantsMedia && $isGif && $matches($body)) {
                $items[] = $shared + [
                    'key'   => 'gif-'.$message->id,
                    'kind'  => 'media',
                    'media' => ['name' => __('GIF'), 'thumbnail' => null, 'image' => null, 'gif_url' => $body],
                ];
            }

            if ($wantsLinks && !$isGif) {
                foreach ($this->links($body) as $index => $url) {
                    if ($matches($url)) {
                        $items[] = $shared + ['key' => 'link-'.$message->id.'-'.$index, 'kind' => 'link', 'link' => ['url' => $url, 'text' => $url]];
                    }
                }
            }
        }

        return [
            'items'       => $items,
            'next_cursor' => $hasMore ? $messages->last()?->id : null,
        ];
    }

    /**
     * The distinct web links of a message body, without the punctuation that ends a sentence after them.
     *
     * @return list<string>
     */
    private function links(string $body): array
    {
        preg_match_all(self::URL_PATTERN, $body, $found);

        return array_values(array_unique(array_map(fn (string $url) => rtrim($url, '.,;:!?)]*_~'), $found[0])));
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: int|null}
     */
    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        return $this->handle(
            $staffConversation,
            $request->user(),
            $request->validated('kind', 'all'),
            $request->validated('q'),
            $request->validated('sort', 'newest'),
            $request->validated('cursor') !== null ? (int) $request->validated('cursor') : null,
        );
    }
}
