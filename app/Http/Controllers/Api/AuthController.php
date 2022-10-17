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
    public function createUser(Request $request, $application)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(), 
            [
                'fullname' => 'required',
                'phone_number' => 'required|unique:users,phone_number',
                'password' => ['required', 'numeric', 'min:6', 'confirmed'],
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::create([
                'fullname' => $request->fullname,
                'email' => $request->email ?? "user@mail.com",
                'application' => $application,
                'phone_number' => $request->phone_number,
                'company_id' => $request->company_id ?? "",
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
    public function loginUser(Request $request, $application)
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

            $user = User::where('phone_number', $request->phone_number)
                          ->where('application', $application)
                          ->first();

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

     public function refresh() {
        return response()->json([
            'status' => true,
            'token' => $user->createToken("API TOKEN")->plainTextToken,
        ], 200);
    }

    public function updateUser(Request $request, $application)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(), 
            [
                'fullname' => 'required',
                'address' => 'required',
                'company_id' => 'required',
                'position_id' => 'required',
                'phone_number' => 'required|unique:users,phone_number',
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::find($request->user_id);
            if(!$user){
                return response()->json([
                    'status' => false,
                    'message' => 'user tidak ditemukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user->fullname = $request->fullname;
            $user->email = $request->email ?? "user@mail.com";
            $user->application = $application;
            $user->position_id = $request->position_id ?? "";
            $user->phone_number = $request->phone_number;
            $user->company_id = $request->company_id ?? "";
            $user->address = $request->address ?? "";
            $user->update();

            return response()->json([
                'status' => true,
                'message' => 'Update Successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

     public function updateProfile(Request $request, $application)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(), 
            [
                'user_id' => 'required',
                'status' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::find($request->user_id);

            if(!$user){
                return response()->json([
                    'status' => false,
                    'message' => 'user tidak ditemukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }
            $user->status = $request->status ?? "Pending";
            $user->update();

            return response()->json([
                'status' => true,
                'message' => 'Update Successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

    public function updatePassword(Request $request, $application)
    {
        try {
            //Validated
            $validateUser = Validator::make($request->all(), 
            [
                'user_id' => 'required',
                'password' => ['required', 'numeric', 'min:6', 'confirmed'],
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::find($request->user_id);

            if(!$user){
                return response()->json([
                    'status' => false,
                    'message' => 'user tidak ditemukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }
            $user->password = Hash::make($request->password);
            $user->update();

            return response()->json([
                'status' => true,
                'message' => 'Update Successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

}
