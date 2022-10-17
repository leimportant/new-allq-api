<?php
namespace App\Repository;

use App\Model\User;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

interface ComboRepositoryInterface
{
   public function position(array $request, $application): Model;
}