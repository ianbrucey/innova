<?php

namespace App\Console\Commands;

use App\Services\FulfillmentRouterService;
use Illuminate\Console\Command;

class RunFulfillmentEngine extends Command
{
    protected $signature   = 'innova:route-orders
                                {--dry-run : Log routing decisions without writing to the database}';
    protected $description = 'Fetch open Shopify orders and route each line item to Amazon or Irvine';

    public function handle(FulfillmentRouterService $router): int
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no database writes will occur');
        }

        $this->info('Starting fulfillment routing engine...');

        try {
            $router->run();
            $this->info('Done.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Engine failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
