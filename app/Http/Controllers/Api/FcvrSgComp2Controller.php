<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcvrSgComp2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class FcvrSgComp2Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = CacheTagger::tags(['fcvr_sg_comp_2'])->remember('fcvr_sg_comp_2.index', 300, function () {
            return FcvrSgComp2::paginate(15);
        });

        return response()->json(['status' => 200, 'data' => $data->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $comp = FcvrSgComp2::create($request->all());
        CacheTagger::tags(['fcvr_sg_comp_2'])->flush();
        return response()->json(['status' => 201, 'data' => $comp], 201);
    }

    public function show(FcvrSgComp2 $fcvrSgComp2): JsonResponse
    {
        return response()->json(['status' => 200, 'data' => $fcvrSgComp2]);
    }

    public function update(Request $request, FcvrSgComp2 $fcvrSgComp2): JsonResponse
    {
        $fcvrSgComp2->update($request->all());
        CacheTagger::tags(['fcvr_sg_comp_2'])->flush();
        return response()->json(['status' => 200, 'data' => $fcvrSgComp2]);
    }

    public function destroy(FcvrSgComp2 $fcvrSgComp2): JsonResponse
    {
        $fcvrSgComp2->delete();
        CacheTagger::tags(['fcvr_sg_comp_2'])->flush();
        return response()->json(['status' => 200]);
    }
}


