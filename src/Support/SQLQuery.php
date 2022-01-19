<?php

namespace App\Support;

use Illuminate\Http\Request;

class SQLQuery 
{
    public static function standardFilter(Request $request, $query) 
    {
        
        $limit = config('api.limit');
        if (is_null($limit))
        {
            $limit = 25;
        }

        if (isset($request->limit))
        {
            $limit = $request->limit;
        }

        $order = 'desc';
        if (isset($request->order))
        {
            switch ($request->order)
            {
                case 'asc':
                case 'desc':
                    $order = $request->order;
                break;
            }
        }

        $order_by = 'id';
        if (isset($request->order_by))
        {
            switch ($request->order_by)
            {
                case 'created_at':
                case 'updated_at':
                case 'published_at':
                case 'id':
                    $order_by = $request->order_by;
                break;
            }
        }

        $offset = 0;
        if (isset($request->offset)) {
            $offset = $request->offset;
        }

        if (isset($request->limit) || isset($request->offset)) {
            return $query->orderBy($order_by, $order)->skip($offset)->take($limit)->get();
        } 
            
        return $query->orderBy($order_by, $order)->paginate($limit);
        
    }
}