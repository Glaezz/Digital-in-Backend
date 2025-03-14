<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = auth("sanctum")->user();
        return response()->json([
            "status" => "success",
            "data" => $user,
        ]);
    }
    // public function show($id)
    // {
    //     try {
    //         $user = User::findOrFail($id);
    //         return response()->json([
    //             "status" => "success",
    //             "data" => $user,
    //         ], 200);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             "status" => "fail",
    //             "message" => "User not found."
    //         ], 404);
    //     }
    // }
    public function update(Request $request)
    {
        $user = auth("sanctum")->user();
        $rule = [
            "username" => "required|min:3|max:20|unique:users,username",
            "email" => "required|unique:users,email",
        ];
        $rule["email"] = $request->email == $user->email ? "required" : "required|unique:users,email";
        $rule["username"] = $request->username == $user->username ? "required" : "required|min:3|max:20|unique:users,username";
        $validator = Validator::make($request->all(), $rule);
        // $authority = isset($request->authority) ? $request->authority : null;
        $input = [
            "username" => $request->username,
            "email" => $request->email,
        ];
        if (isset($request->password)) {
            $input["password"] = $request->password;
        }
        if ($validator->fails()) {
            $resp = [
                "status" => "invalid",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            try {
                $user = User::findOrFail($user->id);
                $user->update($input);
                return response()->json([
                    "status" => "success",
                    "message" => "Data has been successfully updated"
                ], 200);
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    "status" => "fail",
                    "message" => "user not found"
                ], 404);
            }
        }
    }
    public function destroy() {}
}
