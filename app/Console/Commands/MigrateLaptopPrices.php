<?php

namespace App\Console\Commands;

use App\Models\Laptop;
use App\Models\LaptopPrice;
use Illuminate\Console\Command;

class MigrateLaptopPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laptop:migrate-prices {--dry-run : Show what would be migrated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing laptop prices to laptop_prices table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $laptops = Laptop::all();
        $bar = $this->output->createProgressBar($laptops->count());
        $bar->start();

        $migrated = 0;
        $skipped = 0;

        foreach ($laptops as $laptop) {
            // Cek apakah sudah ada price history untuk laptop ini
            $existingPriceHistory = LaptopPrice::where('laptop_id', $laptop->id)->exists();
            
            if (!$existingPriceHistory && $laptop->price > 0) {
                if (!$isDryRun) {
                    LaptopPrice::create([
                        'laptop_id' => $laptop->id,
                        'price' => $laptop->price,
                        'source' => 'migration'
                    ]);
                }
                $migrated++;
                $this->line("  Migrated: {$laptop->series} {$laptop->model} - Price: {$laptop->price}");
            } else {
                $skipped++;
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($isDryRun) {
            $this->info("DRY RUN RESULTS:");
            $this->info("Would migrate: {$migrated} laptops");
            $this->info("Would skip: {$skipped} laptops");
        } else {
            $this->info("Migration completed:");
            $this->info("Migrated: {$migrated} laptops");
            $this->info("Skipped: {$skipped} laptops");
        }

        return 0;
    }
}
