<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 13:05:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Console\Commands;

use App\Actions\Comms\MailshotTimeSeries\ProcessMailshotTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Console\Command;

class ProcessMailshotTimeSeries extends Command
{
    protected $signature = 'mailshot:process-time-series 
                            {mailshotSlug : The slug of the mailshot to process}
                            {--frequency=daily : Time series frequency (daily, weekly, monthly, quarterly, yearly)}
                            {--from= : Start date (Y-m-d format, defaults to mailshot created_at)}
                            {--to= : End date (Y-m-d format, defaults to now)}';

    protected $description = 'Process time series records for a specific mailshot';

    public function handle(): int
    {
        $mailshotSlug = $this->argument('mailshotSlug');
        $frequency = $this->option('frequency');
        $from = $this->option('from');
        $to = $this->option('to');

        // Validate mailshot exists
        $mailshot = Mailshot::where('slug', $mailshotSlug)->first();
        if (!$mailshot) {
            $this->error("Mailshot with slug {$mailshotSlug} not found.");
            return Command::FAILURE;
        }

        // Validate frequency
        if (!in_array($frequency, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])) {
            $this->error("Invalid frequency: {$frequency}. Valid options: daily, weekly, monthly, quarterly, yearly");
            return Command::FAILURE;
        }

        // Set default dates if not provided
        if (!$from) {
            $from = $mailshot->created_at->format('Y-m-d');
        }
        if (!$to) {
            $to = now()->format('Y-m-d');
        }

        // Validate date format
        if (!$this->isValidDate($from) || !$this->isValidDate($to)) {
            $this->error("Invalid date format. Use Y-m-d format (e.g., 2026-05-12)");
            return Command::FAILURE;
        }

        $frequencyEnum = TimeSeriesFrequencyEnum::from($frequency);

        $this->info("Processing time series for mailshot {$mailshot->slug}");
        $this->info("Frequency: {$frequency}");
        $this->info("Period: {$from} to {$to}");

        try {
            ProcessMailshotTimeSeriesRecords::run(
                mailshotId: $mailshot->id,
                frequency: $frequencyEnum,
                from: $from,
                to: $to
            );

            $this->info("✓ Time series processing completed successfully!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error processing time series: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
