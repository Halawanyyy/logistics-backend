<?php

namespace App\Modules\Driver\Controllers;

use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Driver\Requests\CloseDriverManifestRequest;
use App\Modules\Driver\Requests\GenerateDriverManifestRequest;
use App\Modules\Driver\Requests\ListDriverManifestsRequest;
use App\Modules\Label\Enums\LabelFormat;
use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Driver\Resources\DriverManifestPrintResource;
use App\Modules\Driver\Resources\DriverManifestResource;
use App\Modules\Driver\Services\DriverManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DriverManifestController
{
    public function __construct(
        private readonly DriverManifestService $driverManifestService,
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
    ) {
    }

    public function index(ListDriverManifestsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $manifests = DriverManifest::query()
            ->with([
                'driver',
                'createdBy',
                'closedBy',
                'cashClosure.driver',
                'cashClosure.createdBy',
                'cashClosure.verifiedBy',
                'items.shipment.order',
                'items.shipment.merchant',
            ])
            ->when(isset($validated['driver_id']), fn ($query) => $query->where('driver_id', $validated['driver_id']))
            ->when(isset($validated['manifest_date']), fn ($query) => $query->whereDate('manifest_date', $validated['manifest_date']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('manifest_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('manifest_date', '<=', $validated['date_to']))
            ->latest('manifest_date')
            ->latest('id')
            ->get();

        return DriverManifestResource::collection($manifests);
    }

    public function generate(GenerateDriverManifestRequest $request): JsonResponse
    {
        $manifest = $this->driverManifestService->generate($request->validated(), $request->user()?->id);

        return (new DriverManifestResource($manifest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DriverManifest $manifest): DriverManifestResource
    {
        return new DriverManifestResource($this->driverManifestService->loadManifest($manifest));
    }

    public function close(CloseDriverManifestRequest $request, DriverManifest $manifest): DriverManifestResource
    {
        return new DriverManifestResource(
            $this->driverManifestService->close($manifest, $request->validated(), $request->user()?->id)
        );
    }

    public function print(Request $request, DriverManifest $manifest): DriverManifestPrintResource|Response
    {
        $manifest = $this->driverManifestService->loadManifest($manifest);

        if (! $request->has('format')) {
            return new DriverManifestPrintResource($manifest);
        }

        $validated = validator($request->all(), [
            'format' => ['nullable', 'string', Rule::in(LabelFormat::manifestValues())],
            'download' => ['nullable', 'boolean'],
        ])->validate();

        return $this->labelRenderService->renderManifest(
            $this->labelDataBuilderService->forDriverManifest($manifest),
            [
                ...$validated,
                'filename' => 'driver-manifest-'.$manifest->id,
            ]
        );
    }
}
