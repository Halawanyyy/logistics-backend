<?php

namespace App\Modules\CompanyCatalog\Controllers;

use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\CompanyCatalog\Requests\ListCompanyCatalogItemsRequest;
use App\Modules\CompanyCatalog\Requests\StoreCompanyCatalogItemRequest;
use App\Modules\CompanyCatalog\Requests\UpdateCompanyCatalogItemRequest;
use App\Modules\CompanyCatalog\Resources\CompanyCatalogItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyCatalogItemController
{
    public function index(ListCompanyCatalogItemsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $items = CompanyCatalogItem::query()
            ->with(['createdBy', 'updatedBy'])
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['category']), fn ($query) => $query->where('category', $validated['category']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(array_key_exists('is_visible_to_merchants', $validated), fn ($query) => $query->where('is_visible_to_merchants', (bool) $validated['is_visible_to_merchants']))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CompanyCatalogItemResource::collection($items);
    }

    public function store(StoreCompanyCatalogItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['unit_price'] = isset($data['unit_price']) ? number_format((float) $data['unit_price'], 2, '.', '') : '0.00';
        $data['currency'] = $data['currency'] ?? 'EGP';
        $data['is_visible_to_merchants'] = $request->boolean('is_visible_to_merchants', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status'] = $data['status'] ?? CompanyCatalogItem::STATUS_ACTIVE;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $item = CompanyCatalogItem::create($data);

        return (new CompanyCatalogItemResource($item->load(['createdBy', 'updatedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CompanyCatalogItem $companyCatalogItem): CompanyCatalogItemResource
    {
        return new CompanyCatalogItemResource($companyCatalogItem->load(['createdBy', 'updatedBy']));
    }

    public function update(UpdateCompanyCatalogItemRequest $request, CompanyCatalogItem $companyCatalogItem): CompanyCatalogItemResource
    {
        $data = $request->validated();

        if (array_key_exists('unit_price', $data) && $data['unit_price'] !== null) {
            $data['unit_price'] = number_format((float) $data['unit_price'], 2, '.', '');
        }

        if ($request->has('is_visible_to_merchants')) {
            $data['is_visible_to_merchants'] = $request->boolean('is_visible_to_merchants');
        }

        $data['updated_by'] = $request->user()?->id;

        $companyCatalogItem->update($data);

        return new CompanyCatalogItemResource($companyCatalogItem->fresh()->load(['createdBy', 'updatedBy']));
    }

    public function destroy(Request $request, CompanyCatalogItem $companyCatalogItem): JsonResponse
    {
        $companyCatalogItem->delete();

        return response()->json([
            'message' => 'Company catalog item deleted successfully',
        ]);
    }
}
