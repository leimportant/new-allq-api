<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\AccessMenu;
use App\Models\AccessRoles;
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
                    'message' => 'validasi error',
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

            $access_menu = AccessMenu::where('application', $application)
                                       ->where('default', 'Y')
                                       ->whereNull('deleted_at')
                                       ->get();
            $user_activity = "Silahkan lengkapi data profile anda, supaya dapat di verifikasi oleh admin";
            return response()->json([
                'status' => true,
                'message' => 'Buat akun berhasil',
                'token_type'  => 'bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
                'user_activity' => $user_activity,
                'access_menu' => $access_menu,
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

            $access_menu = $user == "Active" ? $this->access_menu($application) : [];
            $user_activity = $user !== "Active" ? "Status Akun anda *" . $user->status . "*, Hubungi admin untuk verifikasi" : "";
            return response()->json([
                'status' => true,
                'message' => 'Login Berhasil',
                'token_type'   => 'bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
                'user_activity' => $user_activity,
                'access_menu' => $access_menu,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

    private function access_menu($application) {
        $user_id = Auth::id();
        $user = User::find($user_id);
        $position_id = $user->status == "Active" ? $user->position_id : "";
        $role = AccessRoles::selectRaw('menu_id')
                            ->where('position_id', $position_id)
                            ->get()
                            ->toArray();

        $data = AccessMenu::where('application', $application)
                                ->whereIn('id', $role)
                                ->whereNull('deleted_at')
                                ->get();

        return $data;
    }

     // method for user logout and delete token
     public function logout()
     {
         auth()->user()->tokens()->delete();
         return [
             'message' => 'Berhasil logged out'
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
                'phone_number' => 'required',
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

            $check_phone = User::where('phone_number', $request->phone_number)
                                 ->whereNotIn('id', [$request->user_id])
                                 ->first();
            if($check_phone){
                return response()->json([
                    'status' => false,
                    'message' => 'Tidak dapat update data, Nomor Handphone sudah dipakai. silahkan cek kembali',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user->fullname = $request->fullname;
            $user->email = $request->email ?? "user@mail.com";
            $user->application = $application;
            $user->position_id = $request->position_id ?? "";
            $user->phone_number = $request->phone_number;
            $user->company_id = $request->company_id ?? "";
            $user->ktp_number = $request->ktp_number ?? "";
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
                    'message' => 'User tidak ditemukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }
            $user->status = $request->status ?? "Pending";
            $user->update();

            return response()->json([
                'status' => true,
                'message' => 'Update Berhasil',
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
                    'message' => 'validasi error',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $user = User::find($request->user_id);

            if(!$user){
                return response()->json([
                    'status' => false,
                    'message' => 'User tidak ditemukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }
            $user->password = Hash::make($request->password);
            $user->update();

            return response()->json([
                'status' => true,
                'message' => 'Update Berhasil',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

}
