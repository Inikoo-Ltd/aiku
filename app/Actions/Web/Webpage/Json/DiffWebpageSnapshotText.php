<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Json;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Paragraph by paragraph comparison of two texts. Paragraphs are matched first, and a removed
 * paragraph followed by an added one is compared word by word, so an edited sentence shows only
 * the words that changed instead of the whole paragraph twice.
 */
class DiffWebpageSnapshotText
{
    use AsAction;

    private const int MAX_COMPARISONS = 250_000;

    /**
     * @return array<int, array{type: string, segments: array<int, array{type: string, text: string}>}>
     */
    public function handle(string $before, string $after): array
    {
        $rows      = [];
        $removed   = [];
        $added     = [];

        foreach ($this->sequenceOperations($this->paragraphs($before), $this->paragraphs($after)) as [$type, $paragraph]) {
            if ($type === 'removed') {
                $removed[] = $paragraph;
            } elseif ($type === 'added') {
                $added[] = $paragraph;
            } else {
                $rows      = array_merge($rows, $this->pairChangedParagraphs($removed, $added));
                $removed   = [];
                $added     = [];
                $rows[]    = ['type' => 'equal', 'segments' => [['type' => 'equal', 'text' => $paragraph]]];
            }
        }

        return array_merge($rows, $this->pairChangedParagraphs($removed, $added));
    }

    /**
     * @return array<int, string>
     */
    private function paragraphs(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text)),
            fn (string $paragraph) => $paragraph !== ''
        ));
    }

    /**
     * @param array<int, string> $removed
     * @param array<int, string> $added
     *
     * @return array<int, array{type: string, segments: array<int, array{type: string, text: string}>}>
     */
    private function pairChangedParagraphs(array $removed, array $added): array
    {
        $rows  = [];
        $pairs = min(count($removed), count($added));

        for ($index = 0; $index < $pairs; $index++) {
            $rows[] = ['type' => 'changed', 'segments' => $this->wordSegments($removed[$index], $added[$index])];
        }

        foreach (array_slice($removed, $pairs) as $paragraph) {
            $rows[] = ['type' => 'removed', 'segments' => [['type' => 'removed', 'text' => $paragraph]]];
        }

        foreach (array_slice($added, $pairs) as $paragraph) {
            $rows[] = ['type' => 'added', 'segments' => [['type' => 'added', 'text' => $paragraph]]];
        }

        return $rows;
    }

    /**
     * @return array<int, array{type: string, text: string}>
     */
    private function wordSegments(string $before, string $after): array
    {
        $segments = [];

        foreach ($this->sequenceOperations($this->words($before), $this->words($after)) as [$type, $word]) {
            $last = array_key_last($segments);
            if ($last !== null && $segments[$last]['type'] === $type) {
                $segments[$last]['text'] .= $word;
            } else {
                $segments[] = ['type' => $type, 'text' => $word];
            }
        }

        return $segments;
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Longest common subsequence of two lists, returned as equal, removed and added operations in
     * reading order. Common starts and ends are cut off first; a middle too big to compare is
     * reported as fully replaced rather than spending the memory a full table would take.
     *
     * @param array<int, string> $before
     * @param array<int, string> $after
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function sequenceOperations(array $before, array $after): array
    {
        $head = [];
        while ($before && $after && $before[0] === $after[0]) {
            $head[] = ['equal', array_shift($before)];
            array_shift($after);
        }

        $tail = [];
        while ($before && $after && end($before) === end($after)) {
            array_unshift($tail, ['equal', array_pop($before)]);
            array_pop($after);
        }

        $beforeCount = count($before);
        $afterCount  = count($after);

        if ($beforeCount * $afterCount > self::MAX_COMPARISONS) {
            return array_merge(
                $head,
                array_map(fn (string $item) => ['removed', $item], $before),
                array_map(fn (string $item) => ['added', $item], $after),
                $tail
            );
        }

        $lengths = array_fill(0, $beforeCount + 1, array_fill(0, $afterCount + 1, 0));
        for ($i = $beforeCount - 1; $i >= 0; $i--) {
            for ($j = $afterCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $before[$i] === $after[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $middle = [];
        $i      = 0;
        $j      = 0;
        while ($i < $beforeCount && $j < $afterCount) {
            if ($before[$i] === $after[$j]) {
                $middle[] = ['equal', $before[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $middle[] = ['removed', $before[$i]];
                $i++;
            } else {
                $middle[] = ['added', $after[$j]];
                $j++;
            }
        }
        for (; $i < $beforeCount; $i++) {
            $middle[] = ['removed', $before[$i]];
        }
        for (; $j < $afterCount; $j++) {
            $middle[] = ['added', $after[$j]];
        }

        return array_merge($head, $middle, $tail);
    }
}
