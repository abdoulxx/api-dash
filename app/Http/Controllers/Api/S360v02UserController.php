<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\S360v02User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class S360v02UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['s360v02_users'])->remember('s360v02_users.index', 300, function () {
            return S360v02User::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = S360v02User::create($request->all());
        CacheTagger::tags(['s360v02_users'])->flush();
        return response()->json(['status' => 201, 'data' => $user], 201);
    }

    public function show(S360v02User $s360v02User): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $s360v02User]);
    }

    public function update(Request $request, S360v02User $s360v02User): JsonResponse
    {
        $s360v02User->update($request->all());
        CacheTagger::tags(['s360v02_users'])->flush();
        return response()->json(['status' => 200, 'data' => $s360v02User]);
    }

    public function destroy(S360v02User $s360v02User): JsonResponse
    {
        $s360v02User->delete();
        CacheTagger::tags(['s360v02_users'])->flush();
        return response()->json(['status' => 200]);
    }

    public function groups(S360v02User $s360v02User): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function rights(S360v02User $s360v02User): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => []]);
    }
}



