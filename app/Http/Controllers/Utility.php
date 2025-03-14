<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class Utility extends Controller
{

    public function productJsonManipulation($products)
{
    function processProduct($product)
    {
        $supplyPrice = (int) $product['harga'];
        $categoryName = $product['kategori'];
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'name' => $categoryName,
                'profit_type' => 'percentage',
                'profit_value' => 10,
            ]
        );

        return [
            'product_code' => $product['kode'],
            'detail' => cleanDetail($product['keterangan']),
            'product' => cleanProduct($product['produk']),
            'category_id' => $category->id,
            'price' => calculateSellingPrice($supplyPrice, $category),
            'supply_price' => $product['harga'],
            'status' => $product['status'],
        ];
    }

    function calculateSellingPrice($supplyPrice, $category)
    {
        $profit = $category->profit_type === 'percentage'
            ? $supplyPrice * ($category->profit_value / 100)
            : $category->profit_value;
        $price = $supplyPrice + $profit;

        return round($price / 5) * 5;
    }

    function cleanDetail($detail)
    {
        return preg_replace('/\b(Topup\s|H2H\s|Saldo\s)/', '', $detail);
    }

    function cleanProduct($product)
    {
        return preg_replace('/\b(Top Up Saldo\s|Topup Saldo\s|H2H\s)/', '', $product);
    }

    function saveProduct($product)
    {
        Product::updateOrCreate(
            ['product_code' => $product['product_code']],
            $product
        );
    }

    foreach ($products as $product) {
        $processedProduct = processProduct($product);
        saveProduct($processedProduct);
    };

    return true;
    }
}
