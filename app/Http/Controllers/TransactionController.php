<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{

    public function createTransaction(Request $request)
    {
        function fetchOKSaldo()
        {
            $user = config("okeconnect.merchantId");
            $password = config("okeconnect.password");
            $pin = config("okeconnect.pin");
            $endpoint = config("okeconnect.endpoint");
            $response = Http::get($endpoint . "/balance", [
                "memberID" => $user,
                "pin" => $pin,
                "password" => $password,
            ]);
            $saldoString = preg_replace('/^Saldo\s+/', '', $response);
            $saldoString = preg_replace('/[^0-9]/', '', $saldoString);

            return (int) $saldoString;
        }
        function fetchTransaction($transaction)
        {
            $user = config("okeconnect.merchantId");
            $password = config("okeconnect.password");
            $pin = config("okeconnect.pin");
            $endpoint = config("okeconnect.endpoint");
            $response = Http::get($endpoint, [
                "memberID" => $user,
                "product" => $transaction->product_code,
                "dest" => $transaction->destination,
                "refID" => $transaction->transaction_id,
                "pin" => $pin,
                "password" => $password,
            ]);
            if ($response->ok()) {
                $responseText = $response->body();
                if (preg_match('/GAGAL\.\s*(.*?)\.\s*/', $responseText, $matches)) {
                    // Jika status GAGAL 
                    if (preg_match('/Nomor\./', $responseText)) {
                        throw new \Exception("Wrong or incorrect destination number");
                    }
                    throw new \Exception($responseText);
                    // throw new \Exception($responseText);
                } elseif (preg_match('/akan diproses\./', $responseText)) {
                    // Jika status PROSES
                    if (preg_match('/T#(\d+)/', $responseText, $trxMatches)) {
                        $trxId = $trxMatches[1];
                        $transaction->ok_id = $trxId;
                        $transaction->save();
                    }
                }
            }
        }

        $validator = Validator::make($request->all(), [
            "product_code" => "required",
            "destination" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "fail",
                'message' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = auth("sanctum")->user();
            $user = User::find($user->id);
            $latestTransaction = Transaction::latest()->first();

            // Tentukan increment number
            $incrementNumber = $latestTransaction
                ? intval(explode('-', $latestTransaction->transaction_id)[1]) + 1 
                : 1;

            // Buat ID transaksi baru
            $transactionId = sprintf('TRX-%d-%s', $incrementNumber, $request->product_code);
            $product = Product::where("product_code", $request->product_code)->first();
            $input = [
                "transaction_id" => $transactionId,
                "user_id" => $user->id,
                "product_code" => $request->product_code,
                "destination" => $request->destination,
                "price" => $product->price,
                "supply_price" => $product->supply_price,
                "time" => now()->format("Y-m-d H:i"),
            ];

            $transaction = Transaction::create($input);
            if (fetchOKSaldo() < $product->supply_price) {
                throw new \Exception("Can't process transaction, please contact support");
            }
            if ($user->balance < $product->price) {
                throw new \Exception('Insufficient balance');
            }

            fetchTransaction($transaction);

            $user->balance -= $product->price;
            $user->save();

            DB::commit();
            

            return response()->json([
                'status' => 'success',
                'data' => $transactionId
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $message = $e->getMessage();

            if (strpos($message, "Insufficient balance") !== false) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Insufficient balance',
                ], 422);
            }

            if (preg_match('/for .*https?:\/\/.+/', $message)) {
                $message = preg_replace('/()https:\/\/h2h.+/', '', $message);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 500);
        }
    }

    public function okeconnectCallback(Request $request)
    {
        function processCallbackMessage($request)
        {
            $refid = $request->refid;
            $message = $request->message;

            $data = [
                'transaction_id' => $refid,
                'ok_id' => null,
                'status' => null,
                'sn_id' => null,
            ];


            // Extract OK_ID
            if (preg_match('/T#([^ ]+)/', $message, $trxMatches)) {
                $data['ok_id'] = $trxMatches[1];
            }
            // if (preg_match('/T%(\d+)/', $message, $matches)) {
            //     $data['ok_id'] = $matches[1];
            // }

            // Extract Status
            if (preg_match('/(SUKSES|GAGAL)/', $message, $matches)) {
                switch ($matches[1]) {
                    case 'SUKSES':
                        $data['status'] = "success";
                        break;

                    default:
                        $data['status'] = "refund";
                        break;
                }
            }

            // Extract SN_ID
            if (preg_match('/SN: ([^\s]+)/', $message, $matches)) {
                $data['sn_id'] = $matches[1];
            }

            return $data;
        }

        try {
            $extractedData = processCallbackMessage($request);
            foreach ($extractedData as $key => $value) {
                if ($value == null) {
                    throw new \exception("Failed to process callback");
                }
            }
            // Update transaksi di database
            $transaction = Transaction::where('transaction_id', $extractedData['transaction_id'])->first();
            if ($transaction->ok_id == $extractedData['ok_id']) {
                $transaction->update([
                    'status' => $extractedData['status'],
                    'sn_id' => $extractedData['sn_id'],
                ]);

                return response()->json([
                    "status" => "success",
                    'message' => 'Callback processed successfully'
                ]);
            } else {
                return response()->json([
                    "status" => "fail",
                    "message" => "Invalid signature, resource access denied"
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function userIndex(Request $request)
    {
        $search = $request->search;
        $perPage = $request->input('per_page', 10);
        $user = auth("sanctum")->user();

        $query = Transaction::where("user_id", $user->id)->with(['product']);

        // Jika ada parameter search
        if ($search != "") {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'LIKE', "%{$search}%")
                    ->orWhere('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('destination', 'LIKE', "%{$search}%")
                    ->orWhere('sn_id', 'LIKE', "%{$search}%");
            });
        }

        // Ambil data dengan pagination
        $trxs = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            'data' => [
                "data" => $trxs,
                'pagination' => [
                    'total' => $trxs->total(),
                    'per_page' => $trxs->perPage(),
                    'current_page' => $trxs->currentPage(),
                    'last_page' => $trxs->lastPage(),
                ]
            ],
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function userShow($id)
    {
        $trx = Transaction::where("transaction_id", $id)->with(['user', 'product'])->first();

        if (!$trx) {
            return response()->json([
                "status" => "fail",
                "message" => "Transaction not found"
            ], 404);
        }

        $user = auth("sanctum")->user();
        if ($trx->user_id == $user->id) {
            if ($trx) {
                $resp = [
                    "status" => "success",
                    "data" => $trx,
                ];
                return response()->json($resp, 200);
            } else {
                return response()->json([
                    "status" => "fail",
                    "message" => "Transaction not found"
                ], 404);
            }
        } else {
            return response()->json([
                "status" => "fail",
                "message" => "You don't have permission to access this resource"
            ], 403);
        }
    }



    public function index(Request $request)
    {
        $search = $request->search;
        $perPage = $request->input('per_page', 10);

        $query = Transaction::with(['product']);

        // Jika ada parameter search
        if ($search != "") {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'LIKE', "%{$search}%")
                    ->orWhere('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('destination', 'LIKE', "%{$search}%")
                    ->orWhere('sn_id', 'LIKE', "%{$search}%");
            });
        }

        // Ambil data dengan pagination
        $trxs = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            'data' => [
                "data" => $trxs,
                'pagination' => [
                    'total' => $trxs->total(),
                    'per_page' => $trxs->perPage(),
                    'current_page' => $trxs->currentPage(),
                    'last_page' => $trxs->lastPage(),
                ]
            ],
        ], 200);
    }

    public function show($id)
    {
        $trx = Transaction::where("transaction_id", $id)->with(['user', 'product'])->first();

        if ($trx) {
            $resp = [
                "status" => "success",
                "data" => $trx,
            ];
            return response()->json($resp, 200);
        } else {
            return response()->json([
                "status" => "fail",
                "message" => "Transaction not found"
            ], 404);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        //
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        //
    }
}
