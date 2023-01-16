<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Orders;
use App\Models\Convection;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class ConvectionController extends Controller
{
    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->q;
        $route = $request->route;
        $month = substr($request->period,5,4);
        $year = substr($request->period,0,4);
        
        $sql = Convection::where('application', $application)
                        ->where('route', $route)
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->where(function($query) use($user_id) {
                            $query->where('user_id', $user_id)
                                  ->orwhere('created_by', $user_id); 
                       });;

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%')
                    ->orwhere('orders_id', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }
    public function store(Request $request, $application) {
        try {
            DB::beginTransaction();

            $user_name = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'orders_id' => 'required',
                'good_receive_id' => 'required',
                'user_id' => 'required',
                'route' => 'required',
                'qty' => 'required',
                'uom' => 'required',
                'remark' => 'required',
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $job = DB::table('create_orders_assignjob')
                                        ->where('orders_id', $request->orders_id)
                                        ->where('route', $request->route)
                                        ->first();
            
            $price_job = $job->price ?? 0;
            $qty_job = $job->qty ?? 1;
            $qty = $request->qty ?? 0;
            $price = floatval($qty * $price_job);
            $total_price = floatval($price * $qty_job);

            $exist = Convection::find($request->id);

            if ($exist) {
                $transaction_id = $request->id;
                $data = Convection::find($transaction_id);
                $data->orders_id = $request->orders_id ?? "";
                $data->application = $application;
                $data->company_id = $request->company_id ?? "";
                $data->good_receive_id = $request->good_receive_id ?? "";
                $data->user_id = $request->user_id ?? "";
                $data->route = $request->route ?? "";
                $data->qty = $request->qty ?? "";
                $data->price = $price_job;
                $data->total_price = $total_price;
                $data->qty = $request->qty ?? "";
                $data->uom = $request->uom ?? "pcs";
                $data->remark = $request->remark ?? "";
                $data->updated_by = $user_name;
                $data->updated_at = $now;
                $data->update();
            } else {
                $transaction_id = $this->generateNumber(6, $application);
                $data = new Convection;
                $data->id = $transaction_id;
                $data->orders_id = $request->orders_id ?? "";
                $data->application = $application;
                $data->company_id = $request->company_id ?? "";
                $data->good_receive_id = $request->good_receive_id ?? "";
                $data->user_id = $request->user_id ?? "";
                $data->route = $request->route ?? "";
                $data->qty = $request->qty ?? "";
                $data->uom = $request->uom ?? "pcs";
                $data->price = $price_job;
                $data->total_price = $total_price;
                $data->remark = $request->remark ?? "";
                $data->created_by = $user_name;
                $data->updated_by = $user_name;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();
            }
        
        

         DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id,
            ], 200);

        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
    
    public function generateNumber($number, $application) {
        $year = $number . $application.  date('ym');
        $_f = '001';
        $sql =  Convection::select(DB::raw('MAX(id) AS id'))
                        ->where('id', 'LIKE', '%' . $year . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $_maxno = $sql->id;
        if (empty($_maxno)) {
            $no = $year . $_f;
        } else {
            $_sbstr = substr($_maxno, -3);
            $_sbstr++;
            $_new = sprintf("%03s", $_sbstr);
            $no = $year . $_new;
        }

      return $no;
    }

    public function reject(Request $request, $application) {
        try {
            DB::beginTransaction();

            $user_name = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'orders_id' => 'required',
                'good_receive_id' => 'required',
                'user_id' => 'required',
                'route' => 'required',
                'qty' => 'required',
                'uom' => 'required',
                'remark' => 'required',
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $job = DB::table('create_orders_assignjob')
                                        ->where('orders_id', $request->orders_id)
                                        ->where('route', $request->route)
                                        ->first();
            
            $price_job = $job->price ?? 0;
            $qty_job = $job->qty ?? 1;
            $qty = $request->qty ?? 0;
            $price = floatval($qty * $price_job);
            $total_price = floatval($price * $qty_job);

            $exist = Convection::find($request->id);

            if ($exist) {
                $transaction_id = $request->id;
                $data = Convection::find($transaction_id);
                $data->orders_id = $request->orders_id ?? "";
                $data->application = $application;
                $data->company_id = $request->company_id ?? "";
                $data->good_receive_id = $request->good_receive_id ?? "";
                $data->user_id = $request->user_id ?? "";
                $data->route = $request->route ?? "";
                $data->qty = $request->qty ?? "";
                $data->price = $price_job;
                $data->is_reject = 1;
                $data->total_price = $total_price;
                $data->qty = $request->qty ?? "";
                $data->uom = $request->uom ?? "pcs";
                $data->remark = $request->remark ?? "";
                $data->updated_by = $user_name;
                $data->updated_at = $now;
                $data->update();
            } else {
                $transaction_id = $this->generateNumber(7, $application);
                $data = new Convection;
                $data->id = $transaction_id;
                $data->orders_id = $request->orders_id ?? "";
                $data->application = $application;
                $data->company_id = $request->company_id ?? "";
                $data->good_receive_id = $request->good_receive_id ?? "";
                $data->user_id = $request->user_id ?? "";
                $data->route = $request->route ?? "";
                $data->qty = $request->qty ?? "";
                $data->is_reject = 1;
                $data->uom = $request->uom ?? "pcs";
                $data->price = $price_job;
                $data->total_price = $total_price;
                $data->remark = $request->remark ?? "";
                $data->created_by = $user_name;
                $data->updated_by = $user_name;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();
            }
        
        

         DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id,
            ], 200);

        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
