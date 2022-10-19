<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class ComboController extends Controller
{

    public function combo(Request $request, $application)
    {
        $user_id = Auth::id();
        $type = $request->type;
        switch ($type) {
        
            case 'position':
                $data = $this->getPosition($request, $application);
                break;
            case 'user':
                $data = $this->getUser($request, $application);
                break;
            case 'month':
                $data = $this->getMonth($request, $application);
                break;
            case 'year':
                $data = $this->getYear($request, $application);
                break;
            default:
                $data = [];
                break;
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    private function getMonth($request, $application) {
        $sql =  DB::table('months');

        if ($request->q) {
            $sql->where('id', 'LIKE', '%' . $request->q . '%')
                ->orwhere('name','LIKE', '%' . $request->q . '%');
        }

        $data = $sql->get();

        return $data;
    }

    private function getYear($request, $application) {
        $sql =  DB::table('years')
                        ->where('status', $request->status);

        if ($request->q) {
            $sql->where('year','LIKE', '%' . $request->q . '%');
        }

        $data = $sql->get();

        return $data;
    }

    private function getPosition($request, $application) {
        $user_id = Auth::id();
        $emp = User::find($user_id);
        $position_id = $emp->position_id ?? "";
        $previlegde = "1";
        if ($position_id === 'ADM') {
            $previlegde = "1,2";
        }

        if ($position_id === 'OWN') {
            $previlegde = "1,2,3";
        }

        $sql =  Position::where('application', $application)
                          ->where('company_id', $request->company_id)
                          ->whereIn('previlegde', explode(",",$previlegde));

        if ($request->q) {
            $sql->where('id','LIKE', '%' . $request->q . '%')
                ->orwhere('name', 'LIKE', '%' . $request->q . '%');
        }

        $data = $sql->paginate();

        return $data;
    }

    private function getUser($request, $application) {
        $sql =  User::where('application', $application)
                            ->where('company_id', $request->company_id)
                            ->whereIn('status', explode(",",$request->status));

        if ($request->q) {
            $sql->where('id', 'LIKE', '%' . $request->q . '%')
                ->orwhere('fullname','LIKE', '%' . $request->q . '%')
                ->orwhere('phone_number', 'LIKE', '%' . $request->q . '%');
        }

        $data = $sql->paginate();

        return $data;
    }
}
