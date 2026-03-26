<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GraphQLController extends Controller
{
    /**
     * POST /graphql — basic GraphQL query parser that maps to REST handlers.
     */
    public function handle(Request $request): JsonResponse
    {
        $query = $request->input('query', '');
        $variables = $request->input('variables', []);

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Query is required']],
            ], 400);
        }

        // Detect if mutation or query
        if (preg_match('/^\s*mutation\b/i', $query)) {
            return $this->handleMutation($request, $query, $variables);
        }

        return $this->handleQuery($request, $query, $variables);
    }

    /**
     * GET /graphql/playground — serve GraphiQL HTML.
     */
    public function playground(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>ParkHub GraphQL Playground</title>
  <link rel="stylesheet" href="https://unpkg.com/graphiql@3/graphiql.min.css" />
</head>
<body style="margin:0;overflow:hidden">
  <div id="graphiql" style="height:100vh"></div>
  <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
  <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
  <script crossorigin src="https://unpkg.com/graphiql@3/graphiql.min.js"></script>
  <script>
    const token = localStorage.getItem('parkhub_token') || '';
    const fetcher = GraphiQL.createFetcher({
      url: '/api/v1/graphql',
      headers: { 'Authorization': 'Bearer ' + token },
    });
    ReactDOM.createRoot(document.getElementById('graphiql')).render(
      React.createElement(GraphiQL, { fetcher })
    );
  </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    private function handleQuery(Request $request, string $query, array $variables): JsonResponse
    {
        $user = $request->user();
        $data = [];

        // me
        if (preg_match('/\bme\b/', $query)) {
            $data['me'] = $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ] : null;
        }

        // lots
        if (preg_match('/\blots\b/', $query) && ! preg_match('/\blot\s*\(/', $query)) {
            $data['lots'] = ParkingLot::select('id', 'name', 'total_slots', 'available_slots', 'status')
                ->limit(50)
                ->get()
                ->toArray();
        }

        // lot(id)
        if (preg_match('/\blot\s*\(\s*id\s*:\s*["\']?([^"\')\s]+)["\']?\s*\)/', $query, $m)) {
            $lot = ParkingLot::find($m[1]);
            $data['lot'] = $lot ? $lot->only('id', 'name', 'total_slots', 'available_slots', 'status') : null;
        } elseif (preg_match('/\blot\s*\(\s*id\s*:\s*\$(\w+)\s*\)/', $query, $m) && isset($variables[$m[1]])) {
            $lot = ParkingLot::find($variables[$m[1]]);
            $data['lot'] = $lot ? $lot->only('id', 'name', 'total_slots', 'available_slots', 'status') : null;
        }

        // bookings
        if (preg_match('/\bbookings\b/', $query) && ! preg_match('/\bbooking\s*\(/', $query)) {
            $data['bookings'] = $user
                ? Booking::where('user_id', $user->id)->select('id', 'lot_id', 'slot_id', 'start_time', 'end_time', 'status')->limit(50)->get()->toArray()
                : [];
        }

        // booking(id)
        if (preg_match('/\bbooking\s*\(\s*id\s*:\s*["\']?([^"\')\s]+)["\']?\s*\)/', $query, $m)) {
            $booking = $user ? Booking::where('user_id', $user->id)->find($m[1]) : null;
            $data['booking'] = $booking ? $booking->only('id', 'lot_id', 'slot_id', 'start_time', 'end_time', 'status') : null;
        }

        // myVehicles
        if (preg_match('/\bmyVehicles\b/', $query)) {
            $data['myVehicles'] = $user
                ? Vehicle::where('user_id', $user->id)->select('id', 'license_plate', 'make', 'model', 'color')->get()->toArray()
                : [];
        }

        if (empty($data)) {
            return response()->json([
                'success' => true,
                'errors' => [['message' => 'No recognized query fields. Available: me, lots, lot(id), bookings, booking(id), myVehicles']],
            ]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function handleMutation(Request $request, string $query, array $variables): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Authentication required for mutations']],
            ], 401);
        }

        // createBooking — delegate entirely to BookingController::store() so all booking
        // policies are enforced: credit checks, booking limits, operating hours, slot
        // locking inside a DB transaction, audit logging, webhooks, and email dispatch.
        if (preg_match('/\bcreateBooking\b/', $query)) {
            // Merge GraphQL variables into the current request so that BookingController
            // and StoreBookingRequest can validate and enforce every rule normally.
            $request->merge([
                'lot_id' => $variables['lot_id'] ?? $variables['lotId'] ?? null,
                'slot_id' => $variables['slot_id'] ?? $variables['slotId'] ?? null,
                'start_time' => $variables['start_time'] ?? $variables['startTime'] ?? null,
                'end_time' => $variables['end_time'] ?? $variables['endTime'] ?? null,
                'booking_type' => $variables['booking_type'] ?? $variables['bookingType'] ?? 'single',
                'notes' => $variables['notes'] ?? null,
                'vehicle_plate' => $variables['vehicle_plate'] ?? $variables['vehiclePlate'] ?? null,
                'license_plate' => $variables['license_plate'] ?? $variables['licensePlate'] ?? null,
            ]);

            try {
                // app()->call() resolves StoreBookingRequest via the service container,
                // which creates it from the current (merged) request, triggers validation,
                // and then calls store() — identical to a normal REST request.
                $innerResponse = app()->call([app(BookingController::class), 'store']);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => [['message' => $e->validator->errors()->first()]],
                ], 422);
            } catch (AuthorizationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => [['message' => 'You are not authorized to create a booking.']],
                ], 403);
            } catch (HttpResponseException $e) {
                $innerResponse = $e->getResponse();
            }

            $statusCode = $innerResponse->getStatusCode();
            $body = json_decode($innerResponse->getContent(), true);

            if ($statusCode >= 400) {
                $errorMsg = $body['error']['message'] ?? $body['message'] ?? 'Booking creation failed';

                return response()->json([
                    'success' => false,
                    'errors' => [['message' => $errorMsg]],
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'data' => ['createBooking' => $body['data'] ?? $body],
            ]);
        }

        // cancelBooking
        if (preg_match('/\bcancelBooking\b/', $query)) {
            $bookingId = $variables['id'] ?? $variables['booking_id'] ?? $variables['bookingId'] ?? null;

            if (! $bookingId) {
                return response()->json(['success' => false, 'errors' => [['message' => 'id is required for cancelBooking']]]);
            }

            $booking = Booking::where('user_id', $user->id)->find($bookingId);
            if (! $booking) {
                return response()->json(['success' => false, 'errors' => [['message' => 'Booking not found']]]);
            }

            $booking->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'data' => [
                    'cancelBooking' => ['id' => $booking->id, 'status' => 'cancelled'],
                ],
            ]);
        }

        // addVehicle
        if (preg_match('/\baddVehicle\b/', $query)) {
            $plate = $variables['license_plate'] ?? $variables['licensePlate'] ?? '';
            $make = $variables['make'] ?? '';
            $model = $variables['model'] ?? '';
            $color = $variables['color'] ?? '';

            if (empty($plate)) {
                return response()->json(['success' => false, 'errors' => [['message' => 'license_plate is required for addVehicle']]]);
            }

            $vehicle = Vehicle::create([
                'user_id' => $user->id,
                'license_plate' => $plate,
                'make' => $make,
                'model' => $model,
                'color' => $color,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'addVehicle' => $vehicle->only('id', 'license_plate', 'make', 'model', 'color'),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'errors' => [['message' => 'No recognized mutation. Available: createBooking, cancelBooking, addVehicle']],
        ]);
    }
}
