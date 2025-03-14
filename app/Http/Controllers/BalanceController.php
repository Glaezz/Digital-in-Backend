<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10); // default 10 data per halaman

        $query = BalanceTransaction::with(["paymentMethod", "user"]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('midtrans_id', 'LIKE', "%{$search}%")
                    ->orWhere('user_id', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%")
                    ->orWhereHas('paymentMethod', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('username', 'LIKE', "%{$search}%");
                    });
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            "data" => [
                'data' => $users,
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ]
            ]
        ]);
    }
    public function show($id)
    {
        function calculateFee($amount, $payMethod)
        {
            return $payMethod->fee_type === 'percentage'
                ? $amount * ($payMethod->fee_value / 100)
                : $payMethod->fee_value;
        }

        try {
            $balanceTransaction = BalanceTransaction::findOrFail($id);
            $payMethod = PaymentMethod::find($balanceTransaction->payment_method_id);
            $fee = (int)calculateFee($balanceTransaction->amount, $payMethod);
            $item_details = [
                (object)[
                    "name" => "Amount",
                    "price" => $balanceTransaction->amount,
                ],
                (object)[
                    "name" => "Amount",
                    "price" => (int)$fee,
                ]
            ];
            $transaction_data = [
                "transaction_id" => $balanceTransaction->id,
                "midtrans_id" => $balanceTransaction->midtrans_id,
                "status" => $balanceTransaction->status,
                "total" => ($balanceTransaction->amount + $fee),
                "expiry_time" => $balanceTransaction->expiry_time,
                "transaction_time" => $balanceTransaction->transaction_time,
                "payment_details" => [
                    "name" => $payMethod->name,
                    "type" => $payMethod->payment_type,
                    "data" => $balanceTransaction->payment_details
                ],
                "item_details" => [
                    (object)[
                        "name" => "Amount",
                        "price" => $balanceTransaction->amount,
                    ],
                    (object)[
                        "name" => "Fee",
                        "price" => $fee,
                    ]
                ]
            ];
            return response()->json([
                "status" => "success",
                "data" => $transaction_data,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "The requested resource is not found"
            ], 404);
        }
    }

    public function userBalance()
    {
        $user = auth("sanctum")->user();
        if ($user != null) {
            return response()->json([
                "status" => "success",
                "data" => $user->balance
            ], 200);
        }
    }

    public function userBalanceHistory()
    {
        $user = auth('sanctum')->user();

        $balanceTransaction = BalanceTransaction::where("user_id", $user->id)->with(["paymentMethod"])->latest()->take(5)->get();
        return response()->json([
            "status" => "success",
            "data" => array(
                "balance" => $user->balance,
                "transaction" => $balanceTransaction,
            )
        ], 200);
    }

    public function userCharge(Request $request)
    {

        function calculateFee($amount, $payMethod)
        {
            return $payMethod->fee_type === 'percentage'
                ? $amount * ($payMethod->fee_value / 100)
                : $payMethod->fee_value;
        }
        function handlePaymentParams($payMethod, $params)
        {
            switch ($payMethod->payment_type) {
                case 'e_wallet':
                    $params[strtolower($payMethod->name)] = (object)[];
                    $params['payment_type'] = strtolower($payMethod->name);
                    break;
                case 'virtual_account':
                    $params['bank_transfer'] = (object)["bank" => strtolower($payMethod->name)];
                    $params['payment_type'] = "bank_transfer";
                    break;
                case 'over_counter':
                    $params['cstore'] = (object)["store" => strtolower($payMethod->name)];
                    $params['payment_type'] = "cstore";
                    break;
                default:
                    throw new \Exception('Invalid payment method');
            }
            return $params;
        }
        function handleTransactionResponse($payMethod, $transaction, $response)
        {
            $transaction->midtrans_id = $response->transaction_id;
            $transaction->expiry_time = $response->expiry_time ?? null;
            $transaction->transaction_time = $response->transaction_time ?? null;

            switch ($payMethod->payment_type) {
                case 'e_wallet':
                    $transaction->payment_details = $response->actions[0]->url ?? null;
                    break;
                case 'virtual_account':
                    $transaction->payment_details = $response->va_numbers[0]->va_number ?? null;
                    break;
                case 'over_counter':
                    $transaction->payment_details = $response->payment_code ?? null;
                    break;
            }

            $transaction->save();
        }

        $user = auth('sanctum')->user();

        $validator = Validator::make($request->all(), [
            "amount" => "required|integer",
            "payment_method" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "fail",
                "message" => $validator->errors()
            ], 422);
        }

        if ($request->amount < 10000) {
            return response()->json([
                "status" => "fail",
                "message" => "Minimum deposit amount is Rp10.000"
            ], 422);
        }

        DB::beginTransaction();

        try {
            $payMethod = PaymentMethod::where("name", $request->payment_method)->first();
            $input = [
                "user_id" => $user->id,
                "amount" => $request->amount,
                "payment_method_id" => $payMethod->id
            ];

            $balanceTransaction = BalanceTransaction::create($input);

            if (!$payMethod) {
                throw new \Exception('Payment method not found');
            }

            \Midtrans\Config::$serverKey = config('midtrans.serverKey');
            \Midtrans\Config::$isProduction = config('midtrans.isProduction', false);
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $fee = calculateFee($request->amount, $payMethod);

            $params = array(
                'transaction_details' => array(
                    'order_id' => $balanceTransaction->id,
                    'gross_amount' => $request->amount + (int)$fee,
                ),
                'customer_details' => array(
                    'first_name' => $user->username,
                    'email' => $user->email,
                ),
                'item_details' => array(
                    array(
                        'id' => "amount",
                        'name' => "Amount",
                        'price' => $request->amount,
                        'quantity' => 1
                    ),
                    array(
                        'id' => "fee",
                        'name' => "Fee",
                        'price' => (int)$fee,
                        'quantity' => 1
                    ),
                ),
            );


            // Handle specific payment types
            $params = handlePaymentParams($payMethod, $params);

            $response = \Midtrans\CoreApi::charge($params);

            if ((int)$response->status_code == 201) {
                handleTransactionResponse($payMethod, $balanceTransaction, $response);
                DB::commit();

                return response()->json([
                    "status" => "success",
                    "message" => $response->status_message,
                    "data" => $balanceTransaction->id
                ], 201);
            } else {
                DB::rollback();
                return response()->json([
                    "status" => "fail",
                    "message" => $response->status_message
                ], (int)$response->status_code);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    }

    public function midtransCallback(Request $request)
    {
        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;

        $reqSignature = $request->signature_key;
        $signature = hash("sha512", $orderId . $statusCode . $grossAmount . config("midtrans.serverKey"));

        if ($reqSignature != $signature) {
            return response()->json([
                "status" => "fail",
                "message" => "Invalid signature, resource access denied"
            ], 401);
        }

        $balanceTransaction = BalanceTransaction::where("midtrans_id", $request->transaction_id)->first();
        $user = User::find($balanceTransaction->user_id);
        if (!$balanceTransaction) {
            return response()->json([
                "status" => "fail",
                "message" => "Invalid transaction id"
            ], 400);
        }

        switch ($request->transaction_status) {
            case 'settlement':
                $balanceTransaction->status = "success";
                $user->balance += $balanceTransaction->amount;
                $user->save();
                break;

            case 'expire':
                $balanceTransaction->status = "expire";
                break;

            case 'cancel':
                $balanceTransaction->status = "cancel";
                break;

            default:

                break;
        }

        $balanceTransaction->save();
        return response()->json([
            "status" => "success",
            "message" => "Success updating data from callback",
        ], 200);
    }

    public function userIndex()
    {
        $user = auth('sanctum')->user();

        $balanceTransaction = BalanceTransaction::where("user_id", $user->id)->with(["paymentMethod"])->latest()->take(5)->get();
        return response()->json([
            "status" => "success",
            "data" => $balanceTransaction
        ], 200);
    }

    public function userShow($id)
    {
        $user = auth('sanctum')->user();

        function calculateFee($amount, $payMethod)
        {
            return $payMethod->fee_type === 'percentage'
                ? $amount * ($payMethod->fee_value / 100)
                : $payMethod->fee_value;
        }



        try {
            $balanceTransaction = BalanceTransaction::findOrFail($id);
            if ($balanceTransaction->user_id == $user->id) {
                $payMethod = PaymentMethod::find($balanceTransaction->payment_method_id);
                $fee = (int)calculateFee($balanceTransaction->amount, $payMethod);
                $item_details = [
                    (object)[
                        "name" => "Amount",
                        "price" => $balanceTransaction->amount,
                    ],
                    (object)[
                        "name" => "Amount",
                        "price" => (int)$fee,
                    ]
                ];
                $transaction_data = [
                    "transaction_id" => $balanceTransaction->id,
                    "midtrans_id" => $balanceTransaction->midtrans_id,
                    "status" => $balanceTransaction->status,
                    "total" => ($balanceTransaction->amount + $fee),
                    "expiry_time" => $balanceTransaction->expiry_time,
                    "transaction_time" => $balanceTransaction->transaction_time,
                    "payment_details" => [
                        "name" => $payMethod->name,
                        "type" => $payMethod->payment_type,
                        "data" => $balanceTransaction->payment_details
                    ],
                    "item_details" => [
                        (object)[
                            "name" => "Amount",
                            "price" => $balanceTransaction->amount,
                        ],
                        (object)[
                            "name" => "Fee",
                            "price" => $fee,
                        ]
                    ]
                ];
                return response()->json([
                    "status" => "success",
                    "data" => $transaction_data,
                ], 200);
            } else {
                return response()->json([
                    "status" => "fail",
                    "message" => "You don't have permission to access this resource",
                ], 403);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "The requested resource is not found"
            ], 404);
        }
    }

    public function userCancel($id)
    {
        $user = auth('sanctum')->user();



        try {
            $balanceTransaction = BalanceTransaction::findOrFail($id);
            if ($balanceTransaction->user_id == $user->id) {
                \Midtrans\Config::$serverKey = config('midtrans.serverKey');
                \Midtrans\Config::$isProduction = config('midtrans.isProduction', false);
                $response = \Midtrans\CoreApi::cancel($id);
                if ((int)$response->status_code == 200) {
                    $balanceTransaction->status = $response->transaction_status;
                    $balanceTransaction->save();
                    return response()->json([
                        "status" => "success",
                        "message" => $response->status_message,
                    ], 200);
                } else {
                    return response()->json([
                        "status" => "fail",
                        "message" => $response->status_message,
                    ], (int)$response->status_code);
                }
            } else {
                return response()->json([
                    "status" => "fail",
                    "message" => "You don't have permission to access this resource",
                ], 403);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "The requested resource is not found"
            ], 404);
        }
    }
}
