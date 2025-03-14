<?php

namespace App\Http\Controllers;

use App\Mail\ForgotMail;
use App\Mail\VerifyMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mime\Encoder\Base64Encoder;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "username" => "required|min:3|max:20|unique:users,username",
            "email" => "required|email|unique:users,email",
            "password" => "required",
            "confirmPassword" => "required|same:password",
        ]);
        $input = [
            "username" => $request->username,
            "email" => $request->email,
            "password" => $request->password,
        ];

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            DB::beginTransaction();
            $user = User::create($input);
            $email = $user->email;
            $plaintext = $email . " ~ " . $user->remember_token;
            $cipher = "aes-128-gcm";
            $key = "digital.in";
            if ($user != null) {
                if (in_array($cipher, openssl_get_cipher_methods())) {
                    $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
                    $iv = openssl_random_pseudo_bytes($ivlen);
                    $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
                    $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
                    $ciphertext = str_replace(array('+', '/'), array('-', '_'), base64_encode($iv . $hmac . $ciphertext_raw));

                    $data = [
                        'username' => $user->username,
                        'verify_url' => env('APP_FRONT_URL') . "/verify-email/" . $ciphertext,
                    ];

                    try {
                        Mail::to($email)->send(new VerifyMail($data));
                        DB::commit();
                        return response()->json([
                            "status" => "success",
                            "data" => "Verify email has been sent"
                        ], 201);
                    } catch (\Exception $e) {
                        DB::rollback();
                        return response()->json([
                            "status" => "error",
                            "data" => "Can't send verify email"
                        ], 400);
                    }
                }
            }
        }
    }

    public function verifyEmail($enc)
    {
        $cipher = "aes-128-gcm";
        $key = "digital.in";


        if (in_array($cipher, openssl_get_cipher_methods())) {
            $c = base64_decode(str_replace(array('-', '_'), array('+', '/'), $enc));
            $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len = 32);
            $ciphertext_raw = substr($c, $ivlen + $sha2len);
            $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);

            $email = explode(' ~ ', $original_plaintext)[0];

            $user = User::where("email", $email)->first();

            if ($user != null) {
                $lastToken = explode(' ~ ', $original_plaintext)[1];
                if ($user->remember_token == $lastToken && !$lastToken) {
                    $user->remember_token = base64_encode("verified");
                    $user->save();
                    return response()->json([
                        "status" => "success",
                        "data" => "Email has been verified"
                    ], 200);
                }
                return response()->json([
                    "status" => "error",
                    "data" => "Resource Not Found"
                ], 404);
            }

            return response()->json([
                "status" => "error",
                "data" => "Resource Not Found"
            ], 404);
        }
    }

    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => "required",
            "password" => "required",
        ]);

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {
            $user = User::where("email", $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                if (!$user->remember_token) {
                    $resp = [
                        "status" => "fail",
                        "message" => "Login failed, user not verified"
                    ];
                    return response()->json($resp, 400);
                }
                $token = $user->createToken("auth-token")->plainTextToken;
                $user->remember_token = $token;
                $user->save();
                $resp = [
                    "status" => "success",
                    "token" => $token,
                ];
                return response()->json($resp, 200);
            } else {
                $resp = [
                    "status" => "fail",
                    "message" => "Login failed, invalid data provided"
                ];
                return response()->json($resp, 400);
            }
        }
    }

    public function signout(Request $request)
    {
        $user = auth("sanctum")->user()->tokens()->delete();
        $resp = [
            "status" => "success",
            "message" => "Successfully logged out"
        ];
        return response()->json($resp, 200);
    }

    public function forgot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => "required|email"
        ]);
        if ($validator->fails()) {
            $resp = [
                "status" => "invalid",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        }
        $email = $request->email;
        $user = User::where("email", $email)->first();




        if ($user != null) {

            $plaintext = $email . " ~ " . $user->remember_token;
            // intval(explode('-', $latestTransaction->transaction_id)[1])
            $cipher = "aes-128-gcm";
            $key = "digital.in";
            if (!isset($user->remember_token)) {
                return response()->json([
                    "status" => "fail",
                    "data" => "User not verified"
                ], 400);
            };

            if (in_array($cipher, openssl_get_cipher_methods())) {
                $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
                $iv = openssl_random_pseudo_bytes($ivlen);
                $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
                $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
                $ciphertext = str_replace(array('+', '/'), array('-', '_'), base64_encode($iv . $hmac . $ciphertext_raw));

                $data = [
                    'username' => $user->username,
                    'reset_url' => env('APP_FRONT_URL') . "/reset-password/" . $ciphertext,
                ];

                try {
                    Mail::to($email)->send(new ForgotMail($data));
                    return response()->json([
                        "status" => "success",
                        "data" => "Reset password email has been sent"
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        "status" => "error",
                        "data" => "Can't send reset password email"
                    ], 500);
                }
            }
        }
        return response()->json([
            "status" => "error",
            "data" => "User with that email not found"
        ], 404);
    }

    public function verifyForgot($enc)
    {

        $cipher = "aes-128-gcm";
        $key = "digital.in";


        if (in_array($cipher, openssl_get_cipher_methods())) {
            $c = base64_decode(str_replace(array('-', '_'), array('+', '/'), $enc));
            $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len = 32);
            $ciphertext_raw = substr($c, $ivlen + $sha2len);
            $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);

            $email = explode(' ~ ', $original_plaintext)[0];
            // $lastToken = explode(' ~ ', $original_plaintext)[1];

            $user = User::where("email", $email)->first();

            if ($user != null) {
                $lastToken = explode(' ~ ', $original_plaintext)[1];
                if ($user->remember_token == $lastToken) {
                    return response()->json([
                        "status" => "success",
                        "data" => $email
                    ], 200);
                }
                return response()->json([
                    "status" => "error",
                    "data" => "Resource Not Found"
                ], 404);
            }

            return response()->json([
                "status" => "error",
                "data" => "Resource Not Found"
            ], 404);
        }
    }

    public function resetPassword(Request $request, $enc)
    {
        $validator = Validator::make($request->all(), [
            "password" => "required",
            "confirmPassword" => "required|same:password",
        ]);

        $input = [
            "password" => $request->password,
        ];

        if ($validator->fails()) {
            $resp = [
                "status" => "fail",
                "message" => $validator->errors(),
            ];
            return response()->json($resp, 422);
        } else {

            $cipher = "aes-128-gcm";
            $key = "digital.in";


            if (in_array($cipher, openssl_get_cipher_methods())) {
                $c = base64_decode(str_replace(array('-', '_'), array('+', '/'), $enc));
                $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
                $iv = substr($c, 0, $ivlen);
                $hmac = substr($c, $ivlen, $sha2len = 32);
                $ciphertext_raw = substr($c, $ivlen + $sha2len);
                $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
                $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);

                $email = explode(' ~ ', $original_plaintext)[0];
                // $lastToken = explode(' ~ ', $original_plaintext)[1];

                $user = User::where("email", $email)->first();

                if ($user != null) {
                    $lastToken = explode(' ~ ', $original_plaintext)[1];
                    if ($user->remember_token == $lastToken) {
                        $user->password = $request->password;
                        $user->save();
                        $resp = [
                            "status" => "success",
                            "message" => "User password has been resetted"
                        ];
                        return response()->json($resp, 200);
                    }
                    return response()->json([
                        "status" => "error",
                        "data" => "Resource Not Found"
                    ], 404);
                }

                return response()->json([
                    "status" => "error",
                    "data" => "Resource Not Found"
                ], 404);
            }
        }
    }
}
