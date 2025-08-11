<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Handles all payment-related logic using the Stripe API.
 */
class PaymentController extends Controller
{
    /**
     * @var \Stripe\StripeClient|null The Stripe client instance.
     */
    private $stripe;

    /**
     * Constructor to initialize the Stripe client.
     * Throws an exception if the secret key is not configured in a production environment.
     */
    public function __construct()
    {
        // Retrieve Stripe secret key from configuration.
        $stripeSecret = config('services.stripe.secret');

        // Check if the Stripe secret key is configured.
        if (empty($stripeSecret)) {
            Log::error('Stripe secret key is not configured. Payment features will be disabled.');
            $this->stripe = null;
            
            // In production, throw a fatal exception. In other environments, allow it to fail gracefully for testing.
            if (app()->environment('production')) {
                throw new \Exception('Stripe secret key is a required configuration. Please set STRIPE_SECRET environment variable.');
            }
            return;
        }

        try {
            $this->stripe = new StripeClient($stripeSecret);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Stripe client: ' . $e->getMessage());
            $this->stripe = null;

            if (app()->environment('production')) {
                throw new \Exception('Payment system initialization failed. Please contact support.');
            }
        }
    }

    /**
     * Middleware to check if Stripe is available before any payment action.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function checkStripeAvailability()
    {
        if (!$this->stripe) {
            return response()->json([
                'error' => 'Payment system is currently unavailable. Please try again later.',
                'code' => 'PAYMENT_SYSTEM_UNAVAILABLE'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        return null;
    }

    /**
     * Creates a Stripe Payment Intent for a specific booking.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Booking $booking
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPaymentIntent(Request $request, Booking $booking)
    {
        // Use the private method to check for Stripe availability.
        if ($errorResponse = $this->checkStripeAvailability()) {
            return $errorResponse;
        }

        try {
            // Verify the booking belongs to the authenticated user.
            if ($booking->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized access to booking'], Response::HTTP_FORBIDDEN);
            }

            // Check if booking already has a payment record associated with it.
            if ($booking->payment) {
                return response()->json(['error' => 'A payment intent already exists for this booking.'], Response::HTTP_BAD_REQUEST);
            }

            // Calculate total amount including fees.
            $paymentService = new Payment(); // Assuming Payment is a service class now, not a model.
            $processingFee = $paymentService->calculateProcessingFee($booking->total_price);
            $totalAmount = $paymentService->calculateTotalAmount($booking->total_price);

            // Create Stripe Payment Intent.
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) round($totalAmount * 100), // Stripe expects amount in cents as an integer.
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'booking_id' => $booking->id,
                    'user_id' => optional(auth()->user())->id,
                ],
                'description' => "Booking payment for property: {$booking->property->title}",
            ]);

            // Create payment record in your database.
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_price,
                'currency' => 'USD',
                'payment_method' => 'card',
                'provider' => 'stripe',
                'provider_payment_id' => $paymentIntent->id,
                'status' => 'pending',
                'processing_fee' => $processingFee,
                'total_amount' => $totalAmount,
                'metadata' => [
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                ],
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_id' => $payment->id,
                'amount' => $totalAmount,
                'processing_fee' => $processingFee,
            ], Response::HTTP_CREATED);

        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error creating Payment Intent: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Payment processing error. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('An unexpected error occurred: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while creating the payment intent.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Confirms a payment after a successful client-side payment flow.
     * This is a non-essential but useful endpoint for client-side polling.
     * The webhook is the primary source of truth.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Payment $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request, Payment $payment)
    {
        // Check if Stripe is available.
        if ($errorResponse = $this->checkStripeAvailability()) {
            return $errorResponse;
        }

        try {
            // Verify the payment belongs to the authenticated user.
            if ($payment->booking->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized access to payment'], Response::HTTP_FORBIDDEN);
            }

            // Retrieve the payment intent from Stripe.
            $paymentIntent = $this->stripe->paymentIntents->retrieve($payment->provider_payment_id);

            // Check the status from Stripe to update the local record.
            if ($paymentIntent->status === 'succeeded') {
                $payment->markAsCompleted();
                $payment->booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed successfully',
                    'booking' => $payment->booking->load('property'),
                ]);
            } else {
                // If payment is not succeeded, return an error.
                return response()->json(['error' => 'Payment is not yet successful or failed.'], Response::HTTP_BAD_REQUEST);
            }

        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error confirming Payment Intent: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Payment confirmation error. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('An unexpected error occurred during payment confirmation: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while confirming the payment.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle Stripe webhook events to update payment status asynchronously.
     * This is the recommended way to handle payment status updates.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook.secret');

        try {
            // Validate the webhook signature.
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (UnexpectedValueException $e) {
            Log::error('Webhook Error: Invalid payload', ['exception' => $e]);
            return response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook Error: Invalid signature', ['exception' => $e]);
            return response('Invalid signature', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Webhook Error: An unknown error occurred', ['exception' => $e]);
            return response('Webhook error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Handle the event based on its type.
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
            default:
                Log::info('Received unhandled webhook event type: ' . $event->type);
        }

        return response('Webhook handled', Response::HTTP_OK);
    }

    /**
     * Handles a successful payment intent event from the Stripe webhook.
     *
     * @param object $paymentIntent
     */
    private function handlePaymentSucceeded($paymentIntent)
    {
        // Find the payment record in your database.
        $payment = Payment::where('provider_payment_id', $paymentIntent->id)->first();
        
        // Only update if a record is found and it's not already completed.
        if ($payment && $payment->status !== 'completed') {
            $payment->markAsCompleted();
            $payment->booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            Log::info("Payment completed for booking ID: {$payment->booking_id}");
        }
    }

    /**
     * Handles a failed payment intent event from the Stripe webhook.
     *
     * @param object $paymentIntent
     */
    private function handlePaymentFailed($paymentIntent)
    {
        $payment = Payment::where('provider_payment_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'failed') {
            // Assuming a method `markAsFailed` exists on your Payment model.
            $payment->markAsFailed('Payment failed via webhook');
            Log::warning("Payment failed for booking ID: {$payment->booking_id}");
        }
    }

    /**
     * Retrieves and returns a specific payment record.
     *
     * @param \App\Models\Payment $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Payment $payment)
    {
        // Verify the payment belongs to the authenticated user.
        if ($payment->booking->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized access to payment'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['payment' => $payment->load('booking.property')]);
    }

    /**
     * Refunds a payment.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Payment $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund(Request $request, Payment $payment)
    {
        if ($errorResponse = $this->checkStripeAvailability()) {
            return $errorResponse;
        }

        try {
            $booking = $payment->booking;
            // Verify authorization: either the user who made the booking or the property owner.
            if ($booking->user_id !== auth()->id() && $booking->property->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized to process refund'], Response::HTTP_FORBIDDEN);
            }

            // Only allow refunds for completed payments.
            if (!$payment->isCompleted()) {
                return response()->json(['error' => 'Payment is not completed and cannot be refunded'], Response::HTTP_BAD_REQUEST);
            }

            // Validate refund amount.
            $requestedAmount = (float) $request->input('amount');
            if ($requestedAmount <= 0 || $requestedAmount > $payment->total_amount) {
                return response()->json(['error' => 'Invalid refund amount'], Response::HTTP_BAD_REQUEST);
            }
            $amountInCents = (int) round($requestedAmount * 100);

            // Create refund in Stripe.
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->provider_payment_id,
                'amount' => $amountInCents,
                'reason' => $request->input('reason', 'requested_by_customer'),
            ]);

            // Store refund details in the payment's metadata.
            $metadata = $payment->metadata ?? [];
            $metadata['refunds'][] = [
                'refund_id' => $refund->id,
                'amount' => $requestedAmount,
                'reason' => $request->input('reason', 'requested_by_customer'),
                'created_at' => now()->toISOString(),
            ];
            $payment->update(['metadata' => $metadata]);

            // Update booking status if a full refund is processed.
            if ($requestedAmount >= $payment->total_amount) {
                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->input('reason', 'Full refund issued.'),
                ]);
            }

            Log::info("Refund processed successfully for payment ID: {$payment->id}");

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => $refund,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe Refund Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Refund processing error. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('An unexpected error occurred during refund: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while processing the refund.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
