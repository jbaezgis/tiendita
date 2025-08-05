<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import {file : The CSV file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing products from: {$filePath}");

        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            $this->error("Could not open file: {$filePath}");
            return 1;
        }

        // Skip header row
        fgetcsv($handle);

        $imported = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) < 3) {
                    $this->warn("Skipping row with insufficient data: " . implode(',', $data));
                    $errors++;
                    continue;
                }

                $code = trim(str_replace('﻿', '', $data[0])); // Remove BOM if present
                $description = trim($data[1]);
                $price = floatval($data[2]);

                if (empty($code) || empty($description) || $price < 0) {
                    $this->warn("Skipping row with invalid data: {$code}, {$description}, {$price}");
                    $errors++;
                    continue;
                }

                // Check if product already exists
                if (Product::where('code', $code)->exists()) {
                    $this->warn("Product with code {$code} already exists, skipping...");
                    $errors++;
                    continue;
                }

                Product::create([
                    'code' => $code,
                    'description' => $description,
                    'price' => $price,
                ]);

                $imported++;
            }

            DB::commit();
            
            $this->info("Import completed successfully!");
            $this->info("Products imported: {$imported}");
            if ($errors > 0) {
                $this->warn("Errors/Skipped: {$errors}");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        } finally {
            fclose($handle);
        }

        return 0;
    }
}
