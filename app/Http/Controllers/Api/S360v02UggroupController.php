<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\S360v02Uggroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class S360v02UggroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['s360v02_uggroups'])->remember('s360v02_uggroups.index', 300, function () {
            return S360v02Uggroup::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = S360v02Uggroup::create($request->all());
        CacheTagger::tags(['s360v02_uggroups'])->flush();
        return response()->json(['status' => 201, 'data' => $group], 201);
    }

    public function show(S360v02Uggroup $s360v02Uggroup): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $s360v02Uggroup]);
    }

    public function update(Request $request, S360v02Uggroup $s360v02Uggroup): JsonResponse
    {
        $s360v02Uggroup->update($request->all());
        CacheTagger::tags(['s360v02_uggroups'])->flush();
        return response()->json(['status' => 200, 'data' => $s360v02Uggroup]);
    }

    public function destroy(S360v02Uggroup $s360v02Uggroup): JsonResponse
    {
        $s360v02Uggroup->delete();
        CacheTagger::tags(['s360v02_uggroups'])->flush();
        return response()->json(['status' => 200]);
    }

    public function members(S360v02Uggroup $s360v02Uggroup): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function rights(S360v02Uggroup $s360v02Uggroup): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => []]);
    }
}



