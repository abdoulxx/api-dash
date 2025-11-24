<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\S360v02Ugright;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class S360v02UgrightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['s360v02_ugrights'])->remember('s360v02_ugrights.index', 300, function () {
            return S360v02Ugright::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $right = S360v02Ugright::create($request->all());
        CacheTagger::tags(['s360v02_ugrights'])->flush();
        return response()->json(['status' => 201, 'data' => $right], 201);
    }

    public function show(S360v02Ugright $s360v02Ugright): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $s360v02Ugright]);
    }

    public function update(Request $request, S360v02Ugright $s360v02Ugright): JsonResponse
    {
        $s360v02Ugright->update($request->all());
        CacheTagger::tags(['s360v02_ugrights'])->flush();
        return response()->json(['status' => 200, 'data' => $s360v02Ugright]);
    }

    public function destroy(S360v02Ugright $s360v02Ugright): JsonResponse
    {
        $s360v02Ugright->delete();
        CacheTagger::tags(['s360v02_ugrights'])->flush();
        return response()->json(['status' => 200]);
    }
}


