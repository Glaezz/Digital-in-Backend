<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::get()
            ->groupBy('payment_type');

        $response = [
            'virtual_account' => $paymentMethods->get('virtual_account') ?? [],
            'e_wallet' => $paymentMethods->get('e_wallet') ?? [],
            'over_counter' => $paymentMethods->get('over_counter') ?? [],
        ];

        return response()->json([
            "status" => "success",
            "data" => $response,
        ], 200);
    }

    public function show($id)
    {
        // dd(auth("sanctum")->user()->balance);
        $data = PaymentMethod::find($id);
        return response()->json([
            "status" => "success",
            "data" => $data,
        ], 200);
    }
}
