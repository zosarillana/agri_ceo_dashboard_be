<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductService
{
    public function getAll()
    {
        return Product::latest()->get();
    }

    public function create(array $data): Product
    {
        $data['slug'] = Str::slug($data['name']);

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}