<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Order;

class ShopifyWebhooksController extends Controller
{
    public function orderCreated(Request $request)
    {
        $orderData = $request->input();
        $formattedJsonData = json_encode($orderData);
        Log::info('Order Created: ID - ' . $orderData['id']);

        $id = $orderData['id'];
        $order = Order::where('order_id', $id)->first();
        Log::info('Order Created: ID - ' , [$order]);
        if ($order) {
            return response()->json(['message' => 'Order already exists in the database'], 200);
        } else {
            $order = new Order();
            $order->order_id = $orderData['id'];
            $order->save();
        }
        $customerData = $orderData["customer"];
        $billingData = $orderData["billing_address"];
        $line_items = $orderData["line_items"];
        $shipping = $orderData['current_shipping_price_set'];
        $note = $this->createNote($orderData);



        $customerId = $this->getCustomerID($customerData["email"]);
        if($customerId == null){
            $customerId = $this->registerCustomer($customerData,$billingData);
        }
        $products = [];
        // Log::info('Payload Data - ', [$payload]);
        foreach ($line_items as $item) {
            Log::info(' Data SKU - ', [$item['sku']]);
            $id = $this->getLightSpeedProductID($item['sku']);
            $price = $this->getPrice($item['price']);
            $tax = $this->getTax($item['price']);
            $loyalty_value = $this->getLoyality($item['price']);
            if($id != null){
                $products[] = [
                    "product_id" => $id,
                    "quantity"=> $item['current_quantity'],
                    "price"=> $price,
                    "tax"=> $tax,
                    "loyalty_value"=> $loyalty_value
                ];
            }
        }
        $products[] = [
            "product_id" => env("SHIPMENT_30"),
            "quantity"=> 1,
            "price"=> $shipping['shop_money']['amount'],
            "tax"=> 0,
        ];
        $paymentType = $orderData['payment_gateway_names'][0];
        $retailerPaymentTypeId = env('CREDIT_CARD_ID');
        if ($paymentType === "manual") {
            $retailerPaymentTypeId = env("CASH_ID");
        }
        $payload = [
            "source_id"=> "7677676",
            "register_id"=> env("REGISTER_ID"),
            "customer_id"=> $customerId,
            "status"=> "AWAITING_DISPATCH",
            "register_sale_products"=> $products,
            "note"=>$note,
            "register_sale_payments"=> [
                [
                    "register_id"=> env("REGISTER_ID"),
                    "retailer_payment_type_id"=> $retailerPaymentTypeId,
                    "amount"=> $orderData['total_price']
                ]
            ]
        ];

        $url = env("LIGHTSPEED_ROOT_URL") . "api/register_sales";
        Log::info('URL Link - ', [$url]);
        $token = env("LIGHTSPED_PERSONAL_TOKEN");
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                Log::info('Request successful: ' . $response->body());
                return response()->json($response->json());
            } else {
                Log::error('Request failed: ' . $response->body());
                return response()->json(['error' => 'Request failed'], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error sending request: ' . $e->getMessage());
            return response()->json(['error' => 'Error sending request'], 500);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }
    public function productCreated(Request $request)
    {
        $productData = $request->input();
        $formattedJsonData = json_encode($productData);
        Log::info('Product Created: ID - ' . $formattedJsonData);
    }

    function getLightSpeedProductID($sku)
    {
        $token = env("LIGHTSPED_PERSONAL_TOKEN");
        $url = env("LIGHTSPEED_ROOT_URL") . "api/2.0/search";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get($url, [
            'type' => 'products',
            'sku' => $sku,
        ]);
        $data = $response->json();
        if (!empty($data['data'])) {
            foreach ($data['data'] as $item) {
                if (!empty($item['id'])) {
                    $id = $item['id'];
                    return $id;
                }
            }
        } else {
            return null;
        }
    }

    function getCustomerID($email)
    {
        $token = env("LIGHTSPED_PERSONAL_TOKEN");
        $url = env("LIGHTSPEED_ROOT_URL") . "api/2.0/search";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get($url, [
            'type' => 'customers',
            'email' => $email,
        ]);
        $data = $response->json();
        Log::info('Customer Data - ', [$data]);
        if (!empty($data['data'])) {
            foreach ($data['data'] as $item) {
                if (!empty($item['id'])) {
                    $id = $item['id'];
                    return $id;
                }
            }
        } else {
            return null;
        }
    }

    function registerCustomer($customerData, $billingData)
    {
        $payload = [
            "phone" => $billingData["phone"],
            "mobile" => $billingData["phone"],
            "email" => $customerData["email"],
            "first_name" => $customerData["first_name"],
            "last_name" =>  $customerData["last_name"],
            "physical_address_1" => $billingData["address1"],
            "physical_city" => $billingData["city"],
            "physical_postcode" => $billingData["zip"]
        ];
        $token = env("LIGHTSPED_PERSONAL_TOKEN");
        $url = env("LIGHTSPEED_ROOT_URL") . "api/2.0/customers";
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                Log::info('Custoemr Register successful: ' . $response->json());
                $data = $response->json();
                if (!empty($data['data'])) {
                    foreach ($data['data'] as $item) {
                        if (!empty($item['id'])) {
                            $id = $item['id'];
                            return $id;
                        }
                    }
                } else {
                    return null;
                }
                return response()->json($response->json());
            } else {
                Log::error('Custoemr register failed: ' . $response->body());
                return response()->json(['error' => 'Request failed'], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error sending request: ' . $e->getMessage());
            return response()->json(['error' => 'Error sending request'], 500);
        }
    }

    function getPrice($price)
    {
        $priceFloat = (float) $price;
        $discountedPrice = $priceFloat / 1.05;
        //return round($discountedPrice, 0, PHP_ROUND_HALF_UP);
        return $discountedPrice;
    }
    function getTax($price)
    {
        $taxFloat = (float) $price;
        $taxPrice = ($taxFloat / 1.05) * 0.05;
        //$roundedTaxPrice = round($taxPrice, 0, PHP_ROUND_HALF_UP);
        return $taxPrice;
    }

    function getLoyality($price)
    {
        return ($price / 100);
    }

    function createNote($orderData)
    {
        $billingData = $orderData["billing_address"];
        $phone = $billingData["phone"];
        $customerName = $orderData['customer']['first_name'] . ' ' . $orderData['customer']['last_name'];
        $shippingAddress = $orderData['shipping_address']['address1'] . ', ' . $orderData['shipping_address']['city'] . ', ' . $orderData['shipping_address']['province'] . ', ' . $orderData['shipping_address']['country'];
        $billingAddress = $orderData['billing_address']['address1'] . ', ' . $orderData['billing_address']['city'] . ', ' . $orderData['billing_address']['province'] . ', ' . $orderData['billing_address']['country'];
        $orderId = $orderData['id'];
        $orderName = $orderData['name'];
        $paymentType = $orderData['payment_gateway_names'][0];
        $shippingPrice = $orderData['total_shipping_price_set']['shop_money']['amount'];
        $note = "Customer Name: $customerName\nPhone Number: $phone\nShipping Address: $shippingAddress\nBilling Address: $billingAddress\nOrder ID: $orderId\nOrder Name: $orderName\nPayment Types: $paymentType\nShipping Amount: $shippingPrice";
        return $note;
    }

    function getShippmentProduct($shippment)
    {
        Log::info('Shippment DAta: - ', [$shippment]);
        $amount = $shippment['shop_money']['amount'];
        if ($amount == 30.0) {
            Log::info('Product DAta: - ', [env("SHIPMENT_30")]);
        } elseif ($amount == 50.0) {
            Log::info('Product DAta: - ', [env("SHIPMENT_50")]);
        } elseif ($amount == 80.0) {
            Log::info('Product DAta: - ', [env("SHIPMENT_80")]);
        } elseif ($amount == 135.0) {
            Log::info('Product DAta: - ', [env("SHIPMENT_135")]);
        }
    }

    function getCustomSKU($sku)
    {
        $id =  $this->getLightSpeedProductID($sku);
        dd($id);
    }
}
