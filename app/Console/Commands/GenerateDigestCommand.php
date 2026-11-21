<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pkm:digest {--days=7 : Number of days the overview covers}')]
#[Description('Generate the AI overview of recent notes, open actions and related notes')]
class GenerateDigestCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        GenerateDigest::dispatchSync($days);

        $this->info("Digest generated for the last {$days} days.");

        return self::SUCCESS;
    }
}
