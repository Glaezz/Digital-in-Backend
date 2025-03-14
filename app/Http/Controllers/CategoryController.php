<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10); // default 10 data per halaman

        $query = Category::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        $categories = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            "data" => [
                'data' => $categories,
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                ]
            ]
        ]);
    }

    public function show($id)
    {
        try {
            $category = Category::findOrFail($id);
            return response()->json([
                "status" => "success",
                "data" => $category,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "User not found."
            ], 404);
        }
    }

    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profit_type' => "required",
            'profit_value' => "required",
        ]);
        $input = [
            'profit_type' => $request->profit_type,
            'profit_value' => $request->profit_value,
        ];

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    "status" => "fail",
                    "message" => "Category not found."
                ], 404);
            }
            $category->update($input);
            $products = Product::where("category_id", $id)->get();


            function calculateSellingPrice($supplyPrice, $category)
            {
                $profit = $category->profit_type === 'percentage'
                    ? $supplyPrice * ($category->profit_value / 100)
                    : $category->profit_value;
                $price = $supplyPrice + $profit;

                // Pembulatan ke 5 rupiah terdekat
                return round($price / 5) * 5;
            };

            function processProduct($product, $category)
            {
                $product->price =  calculateSellingPrice($product->supply_price, $category);
                $product->save();
                return $product;
            };

            function saveProduct($product)
            {
                Product::updateOrCreate(
                    ['product_code' => $product['product_code']],
                    $product
                );
            }

            foreach ($products as $product) {
                processProduct($product, $category);
            };
            return response()->json([
                "status" => "success",
                "message" => "Data has been successfully updated"
            ], 200);
        }
    }
}
