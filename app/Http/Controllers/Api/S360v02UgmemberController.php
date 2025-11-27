<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\S360v02Ugmember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class S360v02UgmemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['s360v02_ugmembers'])->remember('s360v02_ugmembers.index', 300, function () {
            return S360v02Ugmember::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $member = S360v02Ugmember::create($request->all());
        CacheTagger::tags(['s360v02_ugmembers'])->flush();
        return response()->json(['status' => 201, 'data' => $member], 201);
    }

    public function show(S360v02Ugmember $s360v02Ugmember): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $s360v02Ugmember]);
    }

    public function update(Request $request, S360v02Ugmember $s360v02Ugmember): JsonResponse
    {
        $s360v02Ugmember->update($request->all());
        CacheTagger::tags(['s360v02_ugmembers'])->flush();
        return response()->json(['status' => 200, 'data' => $s360v02Ugmember]);
    }

    public function destroy(S360v02Ugmember $s360v02Ugmember): JsonResponse
    {
        $s360v02Ugmember->delete();
        CacheTagger::tags(['s360v02_ugmembers'])->flush();
        return response()->json(['status' => 200]);
    }
}



