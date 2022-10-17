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
            default:
                $data = [];
                break;
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    private function getPosition($request, $application) {
        $sql =  Position::where('application', $application)
                          ->where('company_id', $request->company_id);

        if ($request->q) {
            $sql->where('id', $request->search)
                ->orwhere('name', $request->search);
        }

        $data = $sql->paginate();

        return $data;
    }

    private function getUser($request, $application) {
        $sql =  User::where('application', $application)
                            ->where('company_id', $request->company_id)
                            ->whereIn('status', explode(",",$request->status));

        if ($request->q) {
            $sql->where('id', $request->search)
                ->orwhere('fullname', $request->search)
                ->orwhere('phone_number', $request->search);
        }

        $data = $sql->paginate();

        return $data;
    }
}
