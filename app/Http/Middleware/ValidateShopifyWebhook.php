<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ValidateShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sharedSecret = env('SECERT_KEY');

        // Get the signature from the request header
        $signature = $request->header('X-Shopify-Hmac-Sha256');

        // Get the request body
        $payload = $request->getContent();

        // Calculate the HMAC SHA256 hash of the payload using the shared secret
        $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $sharedSecret, true));

        // Compare the calculated signature with the received signature
        if (!hash_equals($calculatedSignature, $signature)) {
            Log::warning('Invalid Shopify webhook signature.', [
                'signature' => $signature,
                'calculated' => $calculatedSignature,
                'payload' => $payload,
            ]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
