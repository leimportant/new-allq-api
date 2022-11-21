<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Models\Activities;
use App\Models\Purchaseorder;
use App\Models\Purchaseorderitem;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Log;

class PurchaseorderController extends Controller
{
    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->q
        $month = substr($request->period,5,4);
        $year = substr($request->period,0,4);
        
        $sql =  Purchaseorder::with('items')
                        ->select([
                            DB::raw('purchase_order.*'),
                            DB::raw('statuses.name as status_name')
                        ])
                        ->leftJoin('statuses', 'statuses.id', '=', 'purchase_order.status')
                        ->whereNull('deleted_at')
                        ->where('application', $application)
                        ->where('statuses.menu', 'document')
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->where(function($query) use($user_id) {
                            $query->where('created_by', $user_id); 
                       });

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%')
                    ->where('purchase_order.id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('date', 'LIKE', '%' . $filter. '%')
                    ->orwhere('supplier_id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('total_amount', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql = Purchaseorder::with('items')
                        ->with('activities')
                        ->where('application', $application)
                        ->where('id', $filter);

        $data = $sql->get();
        $result= [];
        foreach($data as $row) {
            $result[] = $row;
        }

        return response()->json([
            'status' => true,
            'data' => $result
        ], 200);
    }

    public function store(Request $request, $application)
    {
        try {
            DB::beginTransaction();

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'date' => 'required',
                'total_amount' => ['required', 'numeric'],
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $exist = Purchaseorder::find($request->id);
            $status = $exist->status ?? 1;

            if ($request->total_amount <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Pembelian barang tidak boleh 0 (nol) atau kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }


            if (in_array($status, [2,3])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak boleh di edit kembali, data sudah di setujui',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $emp = User::find($user_id);
            $fullname = $emp->fullname ?? "";
            $company_id = $emp->company_id ?? "";

            if ($exist) {
                $status = $request->status ?? 1;
                $transaction_id = $request->id;
                $data = Purchaseorder::find($transaction_id);
                $data->date = $request->date;
                $data->company_id = $request->company_id;
                $data->supplier_id = $request->supplier_id ?? "";
                $data->application = $application;
                $data->total_amount = $request->total_amount;
                $data->remark = $request->remark;
                $data->status = $status;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = "edit pembelian barang sebesar " . $request->total_amount;

            } else {
                $transaction_id = $this->generateNumber(3, $application);
                $status = $request->status ?? 1;
                $data = new Purchaseorder;
                $data->id = $transaction_id;
                $data->date = $request->date;
                $data->company_id = $request->company_id ?? $company_id;
                $data->supplier_id = $request->supplier_id ?? "";
                $data->application = $application;
                $data->total_amount = $request->total_amount;
                $data->remark = $request->remark;
                $data->status = $status;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();

                $descriptions = "melakukan pembelian barang sebesar " . $request->total_amount;
            }

            if (count($request->items) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Item barang tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $delete = Purchaseorderitem::where('purchase_id', $transaction_id)->delete();
            foreach ($request->items as $row) {
                $material = $row['material'] ?? "";
                $material_name = $row['material_name'] ?? "";
                $qty = $row['qty'] ?? 0;
                $uom = $row['uom'] ?? "";
                $amount = $row['amount'] ?? "";

                if (!$material) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Data barang tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                if (!$qty || !$uom || !$amount) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Qty/Unit/Amount barang tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                $check = Purchaseorderitem::where('purchase_id', $transaction_id)
                                  ->where('material', $material)
                                  ->first();

                if ($check) {
                    $data = Purchaseorderitem::find($check->id);
                    $data->material = $material;
                    $data->purchase_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->amount = $amount;
                    $data->update();
                } else {
                    $data = new Purchaseorderitem;
                    $data->material = $material;
                    $data->purchase_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->amount = $amount;
                    $data->save();
                }

            }

            $Approval = "";

            if ($status == 2) {
                $message  = $descriptions; 
                $Approval = (new ApprovalController)->store($request, $transaction_id, $company_id, 'purchase-order', $application, $message);
            }

            $act = Activities::create([
                'application' => $application,
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'descriptions' => $descriptions,
            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id,
                "notification" => $Approval
            ], 200);
            
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function generateNumber($number, $application) {
        $year = $number . $application.  date('ym');
        $_f = '0001';
        $sql =  Purchaseorder::select(DB::raw('MAX(id) AS id'))
                        ->where('id', 'LIKE', '%' . $year . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $_maxno = $sql->id;
        if (empty($_maxno)) {
            $no = $year . $_f;
        } else {
            $_sbstr = substr($_maxno, -4);
            $_sbstr++;
            $_new = sprintf("%04s", $_sbstr);
            $no = $year . $_new;
        }

      return $no;
    }

}
