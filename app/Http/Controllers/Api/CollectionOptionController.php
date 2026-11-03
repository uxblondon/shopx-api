<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionPoint;
use App\Models\CollectionRate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CollectionOptionController extends Controller
{
    public function options(Request $request)
    {
        $request->validate(['collection_point_id' => 'required|integer|min:1']);
        if (! CollectionPoint::where('id', $request->integer('collection_point_id'))->where('active', 1)->exists()) {
            throw ValidationException::withMessages(['collection_point_id' => 'This collection point is not available.']);
        }
        $rates = CollectionRate::where('collection_point_id', $request->integer('collection_point_id'))
            ->where('available', 1)->orderBy('cost')->get();
        return response()->json(['status' => 'success', 'data' => $rates]);
    }
}
