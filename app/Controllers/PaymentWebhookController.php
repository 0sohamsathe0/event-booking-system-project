<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentWebhookService;
use DomainException;
use JsonException;
use RuntimeException;
use Throwable;

final class PaymentWebhookController
{
    public function razorpay(): void
    {
        header('Content-Type: application/json');
        $rawBody = file_get_contents('php://input') ?: '';
        $signature = trim((string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ''));
        $eventId = trim((string) ($_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? ''));
        try {
            $result = (new PaymentWebhookService())->process($rawBody, $signature, $eventId);
            http_response_code(200);
            echo json_encode(['status' => $result], JSON_THROW_ON_ERROR);
        } catch (DomainException|JsonException $exception) {
            http_response_code(400);
            echo json_encode(['status' => 'rejected']);
        } catch (RuntimeException $exception) {
            error_log($exception->__toString());
            http_response_code(503);
            echo json_encode(['status' => 'unavailable']);
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            http_response_code(500);
            echo json_encode(['status' => 'failed']);
        }
    }
}
