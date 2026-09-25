<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventoSeguridadRequest;
use App\Http\Requests\UpdateEventoSeguridadRequest;
use App\Http\Resources\EventoSeguridadCollection;
use App\Http\Resources\EventoSeguridadResource;
use App\Models\EventoSeguridad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EventoSeguridadController extends Controller
{
    public function index(): EventoSeguridadCollection
    {
        return new EventoSeguridadCollection(EventoSeguridad::recientes()->paginate(15));
    }

    public function store(StoreEventoSeguridadRequest $request): JsonResponse
    {
        $evento = EventoSeguridad::create($request->validated());

        return (new EventoSeguridadResource($evento))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EventoSeguridad $evento): EventoSeguridadResource
    {
        return new EventoSeguridadResource($evento);
    }

    public function update(
        UpdateEventoSeguridadRequest $request,
        EventoSeguridad $evento,
    ): EventoSeguridadResource {
        $evento->update($request->validated());

        return new EventoSeguridadResource($evento->refresh());
    }

    public function destroy(EventoSeguridad $evento): Response
    {
        $evento->delete();

        return response()->noContent();
    }
}
