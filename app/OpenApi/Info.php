<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="ParkHub API",
 *     version="1.3.0",
 *     description="Self-hosted parking management API — Laravel 12, GDPR-ready. Compatible with the Rust backend endpoint structure.",
 *
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="ParkHub Support"
 *     ),
 *
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Laravel Sanctum bearer token. Obtain via POST /api/v1/auth/login."
 * )
 *
 * @OA\Tag(name="Auth", description="Authentication endpoints")
 * @OA\Tag(name="Bookings", description="Parking booking management")
 * @OA\Tag(name="Vehicles", description="User vehicle management")
 * @OA\Tag(name="Absences", description="Homeoffice and vacation absences")
 * @OA\Tag(name="Lots", description="Parking lot management")
 * @OA\Tag(name="Admin", description="Admin-only endpoints")
 */
class Info {}
