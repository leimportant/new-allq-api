<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function createUser(Request $request)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(), 
            [
                'fullname' => 'required',
                'phone_number' => 'required|unique:users,phone_number',
                'password' => ['required', 'numeric', 'min:6', 'confirmed'],
                'company_id' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::create([
                'fullname' => $request->name,
                'email' => $request->email ?? "user@mail.com",
                'phone_number' => $request->phone_number,
                'company_id' => $request->company_id,
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User Created Successfully',
                'token_type'   => 'bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'access_menu' => [],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }


     /**
     * Login The User
     * @param Request $request
     * @return User
     */
    public function loginUser(Request $request)
    {
        try {
            $validateUser = Validator::make($request->all(), 
            [
                'phone_number' => 'required',
                'password' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Nomor Handphone harus di isi, atau nomor Handphone salah',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            if(!Auth::attempt($request->only(['phone_number', 'password']))){
                return response()->json([
                    'status' => false,
                    'message' => 'Nomor Handphone & Password tidak di temukan.',
                ], 200);
            }

            $user = User::where('phone_number', $request->phone_number)->first();

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token_type'   => 'bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
                'access_menu' => [],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

     // method for user logout and delete token
     public function logout()
     {
         auth()->user()->tokens()->delete();
 
         return [
             'message' => 'You have successfully logged out'
         ];
     }
}
