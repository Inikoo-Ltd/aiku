<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Meta cannot read a fragmented MP4 — the kind screen recorders and streaming muxers
 * produce, where `moov` holds only track declarations and the samples live in repeating
 * `moof`/`mdat` fragments. It accepts the upload, then fails the send by webhook with
 * "No video stream found in given video file", which points nowhere near the cause.
 *
 * Rewriting the container fixes it. This is a remux, not a re-encode: the streams are
 * copied untouched, so there is no quality loss and it costs about a second.
 */
class NormaliseWhatsappVideo
{
    use AsAction;

    protected const TIMEOUT_SECONDS = 120;

    /**
     * Returns a path to a progressive MP4, which is the original when it already is one.
     * A failure here is never fatal: the original is used and Meta decides.
     */
    public function handle(string $path): string
    {
        if (!$this->isFragmented($path)) {
            return $path;
        }

        $target = tempnam(sys_get_temp_dir(), 'wa-video-').'.mp4';

        $process = new Process([
            config('services.ffmpeg.path', 'ffmpeg'),
            '-y',
            '-i', $path,
            '-c', 'copy',
            '-movflags', '+faststart',
            $target,
        ]);

        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException|RuntimeException $e) {
            @unlink($target);

            Log::warning('Fragmented WhatsApp video could not be remuxed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return $path;
        }

        if (!is_file($target) || filesize($target) === 0) {
            @unlink($target);

            return $path;
        }

        return $target;
    }

    /**
     * A `moof` box among the top-level boxes is what makes an MP4 fragmented. Reading the
     * box headers is enough — there is no need to decode anything.
     */
    public function isFragmented(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return false;
        }

        try {
            $size = filesize($path) ?: 0;
            $offset = 0;

            // A progressive file reaches its media data within a handful of boxes; the cap
            // stops a malformed file from being walked indefinitely.
            for ($i = 0; $i < 20 && $offset < $size; $i++) {
                if (fseek($handle, $offset) !== 0) {
                    return false;
                }

                $header = fread($handle, 8);

                if ($header === false || strlen($header) < 8) {
                    return false;
                }

                $boxSize = unpack('N', substr($header, 0, 4))[1];
                $boxType = substr($header, 4, 4);

                if ($boxType === 'moof') {
                    return true;
                }

                if ($boxSize === 1) {
                    $largeSize = fread($handle, 8);

                    if ($largeSize === false || strlen($largeSize) < 8) {
                        return false;
                    }

                    $boxSize = unpack('J', $largeSize)[1];
                }

                if ($boxSize < 8) {
                    return false;
                }

                $offset += $boxSize;
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
