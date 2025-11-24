<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcvrSgComp1;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class FcvrSgComp1Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['fcvr_sg_comp_1'])->remember('fcvr_sg_comp_1.index', 300, function () {
            return FcvrSgComp1::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $comp = FcvrSgComp1::create($request->all());
        CacheTagger::tags(['fcvr_sg_comp_1'])->flush();
        return response()->json(['status' => 201, 'data' => $comp], 201);
    }

    public function show(FcvrSgComp1 $fcvrSgComp1): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $fcvrSgComp1]);
    }

    public function update(Request $request, FcvrSgComp1 $fcvrSgComp1): JsonResponse
    {
        $fcvrSgComp1->update($request->all());
        CacheTagger::tags(['fcvr_sg_comp_1'])->flush();
        return response()->json(['status' => 200, 'data' => $fcvrSgComp1]);
    }

    public function destroy(FcvrSgComp1 $fcvrSgComp1): JsonResponse
    {
        $fcvrSgComp1->delete();
        CacheTagger::tags(['fcvr_sg_comp_1'])->flush();
        return response()->json(['status' => 200]);
    }
}


