<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\ShopifyProductUpdate;

class LightSpeedWebhooksController extends Controller
{
    protected $shopifyHelper;
    public function __construct()
    {
        $this->shopifyHelper = new ShopifyProductUpdate();
    }

    public function productUpdated(Request $request){
        // $formattedJsonData = json_decode($request->input('payload'), true);
        // Log::info('Order Created', ['order_data' => $formattedJsonData]);
        $formattedJsonData =json_decode( $request->input("payload"),true);
        Log::info('Product updated Lightspeed: ID - ' , [$formattedJsonData]);
        // $formattedJsonData  = $formattedJsonData["order_data"];
        // Log::info('Product updated Lightspeed: ID - ' , ['order_data' => $formattedJsonData]);
        if (!empty($formattedJsonData['inventory'])) {
            $sku = $formattedJsonData['sku'];
            $inventoryCount = null;
            foreach ($formattedJsonData['inventory'] as $item) {
                if ($item['outlet_id'] === env("OUTLET_ID")) {
                    $inventoryCount = $item['count'];
                    break; 
                }
            }
            if ($inventoryCount !== null) {
                $this->shopifyHelper->inventoryUpdate($sku,$inventoryCount);
                Log::info('Product updated Lightspeed: ID - ' , ['Price' => $formattedJsonData["supply_price"]]);
                $this->shopifyHelper->updatePrice($sku,$formattedJsonData["supply_price"]);
            }
            Log::info(['SKU of the variant' , $sku]);
        }
        return response()->json(['message' => 'Webhook received'], 200);
    }
    public function inventoryUpdated(Request $request){
        $formattedJsonData = $request->input();
        Log::info('Inventory updated Lightspeed: ID - ' , ['order_data' => $formattedJsonData]);
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
