<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\PostGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\StockLocation;
use App\Services\Inventory\PostGoodsReceiptToInventory;
use Illuminate\Http\RedirectResponse;

class GoodsReceiptInventoryController extends Controller
{
    public function __invoke(
        PostGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
        PostGoodsReceiptToInventory $poster,
    ): RedirectResponse {
        $location = StockLocation::query()->findOrFail($request->validated('stock_location_id'));

        $posted = $poster->post($goodsReceipt, $location, $request->user()->id);

        return redirect()
            ->route('pharmacy.purchase-orders.show', $posted->purchase_order_id)
            ->with('success', "Goods receipt {$posted->receipt_number} posted to inventory.");
    }
}
