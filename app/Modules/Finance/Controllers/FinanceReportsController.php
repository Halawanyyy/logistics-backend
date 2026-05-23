<?php

namespace App\Modules\Finance\Controllers;

use App\Models\Merchant;
use App\Modules\Finance\Requests\FinanceReportRequest;
use App\Modules\Finance\Resources\FinanceDriversReportResource;
use App\Modules\Finance\Resources\FinanceExpensesReportResource;
use App\Modules\Finance\Resources\FinanceMerchantReportResource;
use App\Modules\Finance\Resources\FinanceOverviewReportResource;
use App\Modules\Finance\Resources\FinancePayoutsReportResource;
use App\Modules\Finance\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;

class FinanceReportsController
{
    public function __construct(
        private readonly FinanceReportService $financeReportService,
    ) {
    }

    public function overview(FinanceReportRequest $request): FinanceOverviewReportResource
    {
        return new FinanceOverviewReportResource(
            $this->financeReportService->getOverviewReport($request->validated())
        );
    }

    public function merchant(FinanceReportRequest $request, Merchant $merchant): FinanceMerchantReportResource
    {
        return new FinanceMerchantReportResource(
            $this->financeReportService->getMerchantReport($merchant, $request->validated())
        );
    }

    public function drivers(FinanceReportRequest $request): FinanceDriversReportResource
    {
        return new FinanceDriversReportResource(
            $this->financeReportService->getDriversReport($request->validated())
        );
    }

    public function expenses(FinanceReportRequest $request): FinanceExpensesReportResource
    {
        return new FinanceExpensesReportResource(
            $this->financeReportService->getExpensesReport($request->validated())
        );
    }

    public function payouts(FinanceReportRequest $request): FinancePayoutsReportResource
    {
        return new FinancePayoutsReportResource(
            $this->financeReportService->getPayoutsReport($request->validated())
        );
    }

    public function dailyCash(FinanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->financeReportService->getDailyCashReport($request->validated()),
        ]);
    }

    public function merchantLedger(FinanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->financeReportService->getMerchantLedgerReport($request->validated()),
        ]);
    }

    public function driverDiscrepancies(FinanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->financeReportService->getDriverDiscrepanciesReport($request->validated()),
        ]);
    }

    public function warehouseRevenue(FinanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->financeReportService->getWarehouseRevenueReport($request->validated()),
        ]);
    }

    public function profitLoss(FinanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->financeReportService->getProfitLossReport($request->validated()),
        ]);
    }
}
