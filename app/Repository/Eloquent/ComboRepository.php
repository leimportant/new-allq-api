<?php   

namespace App\Repository\Eloquent;

use App\Model\User;
use App\Model\Position;
use App\Repository\ComboRepositoryInterface;
use Illuminate\Support\Collection;  

class ComboRepository extends BaseRepository implements ComboRepositoryInterface
{     
    /**      
     * @var Model      
     */     
     protected $model;       

    /**      
     * BaseRepository constructor.      
     *      
     * @param Model $model      
     */     
    public function __construct(Model $model)     
    {         
        $this->model = $model;
    }

    /**
    * @param $id
    * @return Model
    */
    public function position($request, $application)
    {
        $sql =  Position::where('application', $application);

        if ($request) {
            $sql->where('id', $request)
                ->orwhere('name', $request);
        }

        $data = $sql->paginate();

        return $data;
    }

}