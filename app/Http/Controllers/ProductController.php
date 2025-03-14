<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{

    public function fetchProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "url" => "required",
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => $validator->errors(),
            ], 422);
        } else {
            $apiUrl = $request->url;
            $response = Http::withOptions([
                'verify' => false
            ])->get($apiUrl);
            if ($response->successful()) {
                $products = $response->json();

                $result = (new Utility)->productJsonManipulation($products);
                if ($result == true) {
                    return response()->json([
                        "status" => "success",
                        "message" => "Products fetched and saved successfully"
                    ], 200);
                } else {
                    return response()->json([
                        "status" => "error",
                        "message" => "Failed to fetch products from API"
                    ], 500);
                }
            } else {
                return response()->json([
                    "status" => "error",
                    "message" => "Failed to fetch products from API"
                ], 500);
            }
        }
    }




    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10); // default 10 data per halaman

        $query = Product::with(["category"])->orderBy("product_code", "asc");

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('detail', 'LIKE', "%{$search}%")
                    ->orWhere('supply_price', 'LIKE', "%{$search}%")
                    ->orWhere('price', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            "data" => [
                'data' => $products,
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ]
            ]
        ]);
    }


    public function show($id)
    {

        $product = Product::where("product_code", $id)->first();
        if (!$product) {
            return response()->json([
                "status" => "fail",
                "message" => "Product not found."
            ], 404);
        }
        return response()->json([
            "status" => "success",
            "data" => $product
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_code' => "required",
            'detail' => "required",
            'product' => "required",
            'category' => "required",
            'price' => "required|integer",
            'supply_price' => "required|integer",
            'status' => "required",
        ]);
        $input = [
            'product_code' => $request->product_code,
            'detail' => $request->detail,
            'product' => $request->product,
            'category' => $request->category,
            'price' => $request->price,
            'supply_price' => $request->supply_price,
            'status' => $request->status,
        ];
        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            Product::create($input);
            $resp = [
                "status" => "success",
                "message" => "Add product success"
            ];
            return response()->json($resp, 201);
        }
    }

    /**
     * Display the specified resource.
     */
    public function productShow(Request $request)
    {
        $key = $request->key;
        $value = $request->value;

        if ($key === null) {
            return response()->json([
                "status" => "success",
                "data" => Product::all(),
            ], 200);
        }

        switch ($key) {
            case 'category':
                if ($value === null) {
                    $categories = Category::select('name')->pluck('name');
                    return response()->json([
                        "status" => "success",
                        "data" => $categories,
                    ], 200);
                } else {
                    switch (strtolower($value)) {
                        case 'credit':
                            $value = 1;
                            break;
                        case 'e wallet':
                            $value = 2;
                            break;

                        default:
                            $value = 1;
                            break;
                    }
                    $products = Product::where('category_id', $value)
                        ->select('product')
                        ->distinct()
                        ->get()
                        ->map(function ($item) {
                            return [
                                'title' => $item->product,
                                'image' => 'asset.url.com/' . $item->product,
                            ];
                        });

                    return response()->json([
                        "status" => "success",
                        "data" => $products,
                    ], 200);
                }
                break;

            case "product":
                if ($value === null) {
                    $product = Product::select('product')
                        ->distinct()
                        ->get()
                        ->map(function ($item) {
                            return [
                                'title' => $item->product,
                                'image' => 'https://glaezz.github.io/cdn/glaezz-in/v2/' . $item->product . ".jpg",
                            ];
                        });
                    return response()->json([
                        "status" => "success",
                        "data" => $product,
                    ], 200);
                    break;
                } else {
                    $subProducts = Product::where('product', $value)->orderBy('price')->get();
                    if ($subProducts->first() == null) {
                        return response()->json([
                            "status" => "fail",
                            "message" => "Product not found",
                        ], 404);
                        break;
                    }
                    return response()->json([
                        "status" => "success",
                        "data" => $subProducts,
                    ], 200);
                    break;
                }

            default:
                return response()->json([
                    "status" => "error",
                    "message" => "Invalid key provided",
                ], 422);
                break;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'price' => "required|integer",
            'status' => "required",
        ]);
        $input = [
            'price' => $request->price,
            'status' => $request->status,
        ];

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {

            $product = Product::where("product_code", $id)->first();
            if (!$product) {
                return response()->json([
                    "status" => "fail",
                    "message" => "Product not found."
                ], 404);
            }
            $product->update($input);
            return response()->json([
                "status" => "success",
                "message" => "Product has been successfully updated"
            ], 200);
        }
    }

    public function updateCategory($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profit_type' => "required",
            'status' => "required",
        ]);
        $input = [
            'price' => $request->price,
            'status' => $request->status,
        ];

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            $category = Category::find($id)->first();
            if (!$category) {
                return response()->json([
                    "status" => "fail",
                    "message" => "Category not found."
                ], 404);
            }
            $category->update($input);
            $products = Product::where("category", $id);


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
                $processedProduct = processProduct($product, $category);
                saveProduct($processedProduct);
            };
            return response()->json([
                "status" => "success",
                "message" => "Product has been successfully updated"
            ], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Product::where("product_code", $id)->first();
        if ($product) {
            $product->delete();
            return response()->json([
                "status" => "success",
                "message" => "Product has been successfully deleted"
            ], 200);
        } else {
            return response()->json([
                "status" => "fail",
                "message" => "Product not found."
            ], 404);
        }
    }

    public function product(Request $request)
    {
        $query = Product::where("category", $request->category);
        if ($request->filled('product')) {
            $query->where("product", $request->product);
        }
        $content = $query->get();
        return response()->json([
            "totalElements" => $content->count(),
            "content" => $content,
        ], 200);
    }
}
