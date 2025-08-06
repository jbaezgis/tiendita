<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AddSampleImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:add-sample-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add sample images to products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Adding sample images to products...');

        // Sample image URLs (you can replace these with actual image URLs)
        $sampleImages = [
            'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=400&fit=crop',
        ];

        $products = Product::take(20)->get(); // Take first 20 products
        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $index => $product) {
            try {
                // Clear existing images
                $product->clearMediaCollection('images');
                
                // Get a sample image URL (cycle through the array)
                $imageUrl = $sampleImages[$index % count($sampleImages)];
                
                // Download and add the image
                $product->addMediaFromUrl($imageUrl)
                       ->toMediaCollection('images', 'public');
                
                $bar->advance();
            } catch (\Exception $e) {
                $this->warn("Failed to add image to product {$product->id}: " . $e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sample images added successfully!');
    }
} 