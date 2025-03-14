<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mockery\Undefined;
use Symfony\Component\ErrorHandler\Error\UndefinedMethodError;

class AdminController extends Controller
{
    // CRUD User
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10); // default 10 data per halaman

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
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
        try {
            $user = User::findOrFail($id);
            return response()->json([
                "status" => "success",
                "data" => $user,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "User not found."
            ], 404);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $rule = [
                "username" => "required",
                "email" => "required",
                "authority" => "required",
                "balance" => "required|integer",

            ];
            $rule["email"] = $request->email == $user->email ? "required" : "required|unique:users,email";
            $validator = Validator::make($request->all(), $rule);

            $input = [
                "username" => $request->username,
                "email" => $request->email,
                "authority" => $request->authority,
                "balance" => $request->balance,
            ];
            if (isset($request->password)) {
                $input["password"] = $request->password;
            }
            if ($validator->fails()) {
                $resp = [
                    "status" => "fail",
                    "message" => $validator->errors(),
                ];
                return response()->json($resp, 422);
            } else {



                $user->update($input);
                return response()->json([
                    "status" => "success",
                    "message" => "Data has been successfully updated"
                ], 200);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "User not found."
            ], 404);
        }
    }
    public function destroy($id)
    {
        try {
            $product = User::findOrFail($id);
            $product->delete();
            return response()->json([
                "status" => "success",
                "message" => "Data has been successfully deleted"
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => "fail",
                "message" => "User not found."
            ], 404);
        }
    }
    public function statistic(Request $request)
    {
        $type = $request->input('type', 'monthly');
        $date = $request->date;

        $query = Transaction::with(["product"]);
        switch ($type) {
            case 'daily':
                $query->whereDate('created_at', $date);
                break;

            case 'weekly':
                $year = explode('-W', $date)[0];
                $week = explode('-W', $date)[1];
                // list($year, $week) = explode('-W', $date);
                $startDate = Carbon::now()->setISODate($year, $week)->startOfWeek();
                $endDate = Carbon::now()->setISODate($year, $week)->endOfWeek();
                $query->whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'monthly':
                $startDate = $date ? Carbon::parse($date)->startOfMonth() : Carbon::now()->startOfMonth();
                $endDate = $date ? Carbon::parse($date)->endOfMonth() : Carbon::now()->endOfMonth();
                $query->whereBetween('created_at', [$startDate, $endDate]);
                break;
        }


        $transactions = $query->latest()->get();
        $profit = 0;
        $mostPurchase = [];

        foreach ($transactions as $transaction) {
            $profit += ($transaction->price - $transaction->supply_price);
            $productName = $transaction->product->product;
            if (isset($mostPurchase[$productName])) {
                $mostPurchase[$productName]++;
            } else {
                $mostPurchase[$productName] = 1;
            }
        }


        arsort($mostPurchase);
        $topThreePurchases = array_slice($mostPurchase, 0, 3, true);


        $userQuery = User::query();

        if (isset($startDate) && isset($endDate)) {
            $userQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else if ($date) {
            $userQuery->whereDate('created_at', $date);
        }

        $count_user = $userQuery->count();
        $resp = [
            "profit" => $profit,
            "count_transaction" => $transactions->count(),
            "count_user" => $count_user,
            "most_purchase" => $topThreePurchases,
            "period" => [
                "type" => $type,
                "start_date" => isset($startDate) ? $startDate->format('Y-m-d') : $date,
                "end_date" => isset($endDate) ? $endDate->format('Y-m-d') : $date
            ]
        ];

        return response()->json($resp);
    }
}
