<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 15:04:29 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait WithVarnishBan
{
    /**
     * Execute a shell command using Symfony Process and throw on failure.
     */
    protected function executeCommand(string $command): Process
    {
        // Use shell execution so the full command string is interpreted correctly
        $process = Process::fromShellCommandline($command);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    /**
     * Runs a Varnish command and, if a Command is provided, prints the stdout/stderr
     * mirroring the previously duplicated logic. Swallows exceptions to avoid breaking the flow.
     */
    protected function runVarnishCommand(string $varnishCommand, Command $command = null): void
    {
        try {
            $process = $this->executeCommand($varnishCommand);

            if ($command) {
                $stdout = trim($process->getOutput());
                $stderr = trim($process->getErrorOutput());

                if ($stdout !== '') {
                    $command->info($stdout);
                } else {
                    $command->line('Command executed with no output.');
                }

                if ($stderr !== '') {
                    $command->warn('STDERR:');
                    $command->line($stderr);
                }
            }
        } catch (ProcessFailedException $e) {
            if ($command) {
                $process = $e->getProcess();
                $stdout = trim($process->getOutput());
                $stderr = trim($process->getErrorOutput());

                if ($stdout !== '') {
                    $command->line($stdout);
                }
                if ($stderr !== '') {
                    $command->error($stderr);
                } else {
                    $command->error($e->getMessage());
                }
            }
            // Swallow ban failures to avoid breaking the request flow; caching will still be cleared at app level
        } catch (\Throwable $e) {
            if ($command) {
                $command->error($e->getMessage());
            }
            // Swallow ban failures to avoid breaking the request flow; caching will still be cleared at app level
        }
    }
}
