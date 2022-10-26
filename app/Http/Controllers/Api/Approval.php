<?php

namespace App\Http\Controllers\Api;

use App\Models\Documents;
use App\Models\documentApproval;
use App\Models\configApproval;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class Approval extends Controller
{

    public function checkDocument($transaction_id) {
        $data = Documents::where('transaction_id', $transaction_id)
                            ->first();
        
        return $data;
    }

    public function checkLastApproval($transaction_id) {
        $data = documentApproval::where('transaction_id', $transaction_id)
                    ->where('status', 'N')
                    ->orderBy('level')
                    ->first();
        
        return $data;
    }

    public function store($request, $transaction_id, $company_id, $route, $application)
    {
        $userId = Auth::id();
        $now = Carbon::now()->timestamp;
        // Cek document apakah sudah masuk approval dan status nya
        $doc = $this->checkDocument($transaction_id);

        $status = $doc->status ?? "-";
        $next_apprl = $doc->next_approval ?? "-";

        if (!$doc) {
            $data = new Documents;
            $data->transaction_id = $transaction_id;
            $data->application = $application;
            $data->route = $route;
            $data->status = 0;
            $data->next_approval = '-';
            $data->created_by = $userId;
            $data->updated_by = $userId;
            $data->created_at = $now;
            $data->updated_at = $now;
            $data->save();
        }

        $configApproval = "";
        // status 1 draft dan 4 reject approval bisa di create lagi 

        if (in_array($status, [0,4]) || $status === "-") {
            $delete = documentApproval::where('transaction_id', $transaction_id)->delete();

            $configApproval = configApproval::where('company_id', $company_id)
                                ->where('route', $route)
                                ->where('application', $application)
                                ->get();

        } else {
            // kirim status true karena bisa di edit lagi dan lagi approval
            return response()->json([
                'status' => true,
                'message' => "Status pengajuan sedang di proses",
            ], 200);
        }

        if ($configApproval) {
            
            $new = new documentApproval;
            $new->transaction_id = $transaction_id;
            $new->user_id = $userId;
            $new->level = 1;
            $new->status = "Y";
            $new->created_at = date("Y-m-d H:i:s");
            $new->separator = "";
            $new->save();

            foreach($configApproval as $row) {
                $user_id = $row->user_id ?? "";
                $level = $row->level ?? 1;
                $separator = $row->separator ?? "";
                $exist = documentApproval::where('transaction_id', $transaction_id)
                                         ->where('user_id', $user_id)
                                         ->first();

                if ($exist) {
                    documentApproval::where('transaction_id', $transaction_id)
                                    ->where('user_id', $user_id)
                                    ->update([
                                        'level' => $exist->level,
                                        'status' => 'N',
                                        'created_at' => null
                                    ]);
                } else {

                    $data = new documentApproval;
                    $data->transaction_id = $transaction_id;
                    $data->user_id = $user_id;
                    $data->level = ++$level;
                    $data->separator = $separator;
                    $new->created_at = null;
                    $data->save();
                    
                }                
            }

            $last = $this->checkLastApproval($transaction_id);
            
            $next_approval = $last->user_id ?? $next_apprl; 
            $descriptions = "Pengajuan terkirim, menunggu approval " . $next_approval;
            if ($next_approval === "-") {
                $status = 4;
                $descriptions = "Pengajuan pending";
            } 


            Documents::where('transaction_id', $transaction_id)
                            ->update([
                                'next_approval' => $next_approval,
                                'status' =>  $next_approval !== '-' ? 1: $status,
                                'route' => $route
                            ]);

            $act = Activities::create([
                    'application' => $application,
                    'transaction_id' => $transaction_id,
                    'user_id' => $userId,
                    'descriptions' => $descriptions,
                ]);
        }
        
        return response()->json([
            'status' => true,
            'message' => "Notifikasi Pengajuan anda berhasil",
        ], 200);
    }
}
