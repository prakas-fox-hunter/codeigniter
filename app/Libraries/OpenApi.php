<?php

namespace App\Libraries;

class OpenApi
{
    public static function document(): array
    {
        $ref = static fn (string $name) => ['$ref' => '#/components/schemas/' . $name];
        $string = ['type' => 'string']; $int = ['type' => 'integer'];
        $object = static fn (array $properties, array $required = []) => ['type' => 'object', 'properties' => $properties, 'required' => $required];
        $json = static fn (array $schema) => ['application/json' => ['schema' => $schema]];
        $error = ['description' => 'Kesalahan validasi, autentikasi, kepemilikan, atau konflik reservasi.', 'content' => $json($ref('Error'))];
        $op = static function (string $tag, string $summary, array $response, ?array $body = null, bool $auth = false, string $success = '200') use ($json, $error): array {
            $operation = ['tags' => [$tag], 'summary' => $summary, 'responses' => [$success => ['description' => 'Berhasil', 'content' => $json($response)], '400' => $error, '401' => $error, '404' => $error, '409' => $error, '422' => $error, '429' => $error], 'security' => $auth ? [['bearerAuth' => []]] : []];
            if ($body) $operation['requestBody'] = ['required' => true, 'content' => $json($body)];
            return $operation;
        };
        $pathId = static fn (string $name) => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]];
        $credentials = $object(['email' => ['type' => 'string', 'format' => 'email', 'example' => 'traveler@example.com'], 'password' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 72, 'example' => 'TravelPass123!']], ['email', 'password']);
        $user = $object(['id' => $int, 'name' => $string, 'email' => ['type' => 'string', 'format' => 'email'], 'created_at' => $string]);
        $ticket = $object(['id' => $int, 'flight_id' => $int, 'seat_number' => $string, 'status' => ['type' => 'string', 'enum' => ['open', 'closed']], 'airline' => $string, 'flight_number' => $string, 'origin' => $string, 'destination' => $string, 'departure_at' => $string, 'arrival_at' => $string, 'price' => $int, 'available' => ['type' => 'boolean']]);
        $booking = $object(['id' => $int, 'reference' => $string, 'user_id' => $int, 'ticket_id' => $int, 'passenger_name' => $string, 'amount' => $int, 'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'expired', 'cancelled']], 'payment_method' => ['type' => 'string', 'nullable' => true], 'created_at' => $string, 'expires_at' => $string, 'paid_at' => ['type' => 'string', 'nullable' => true], 'seat_number' => $string, 'airline' => $string, 'flight_number' => $string, 'origin' => $string, 'destination' => $string, 'departure_at' => $string, 'arrival_at' => $string]);
        $methods = ['type' => 'array', 'items' => $object(['id' => $string, 'name' => $string, 'description' => $string])];
        $authResponse = $object(['user' => $ref('User'), 'token' => $string, 'expires_in' => $int]);
        $message = $object(['message' => $string]);
        $paths = [];
        $register = $credentials; $register['properties']['name'] = ['type' => 'string', 'maxLength' => 100, 'example' => 'Prakosa Dwi Prasetya']; $register['required'][] = 'name';
        $paths['/api/auth/register']['post'] = $op('Auth', 'Daftarkan akun baru', $authResponse, $register, false, '201');
        $paths['/api/auth/login']['post'] = $op('Auth', 'Masuk dan dapatkan JWT (berlaku 1 jam)', $authResponse, $credentials);
        $paths['/api/auth/logout']['post'] = $op('Auth', 'Cabut semua token akun ini', $message, $object([]), true);
        $paths['/api/auth/forgot-password']['post'] = $op('Auth', 'Kirim tautan reset; mode development/demo mengembalikan demo_reset_url', $object(['message' => $string, 'demo_reset_url' => $string]), $object(['email' => ['type' => 'string', 'format' => 'email']], ['email']));
        $paths['/api/auth/reset-password']['post'] = $op('Auth', 'Reset sekali pakai (30 menit) dan cabut JWT lama', $message, $object(['token' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64], 'password' => $credentials['properties']['password']], ['token', 'password']));
        $paths['/api/users/me']['get'] = $op('Account', 'Profil pemilik JWT', $object(['user' => $ref('User')]), null, true);
        $paths['/api/users/me']['patch'] = $op('Account', 'Perbarui profil; perubahan password memerlukan current_password', $authResponse, $object(['name' => $string, 'email' => ['type' => 'string', 'format' => 'email'], 'password' => $credentials['properties']['password'], 'current_password' => $string], ['name', 'email']), true);
        $paths['/api/users/me']['delete'] = $op('Account', 'Hapus akun dan batalkan reservasi pending', $message, $object(['password' => $string], ['password']), true);
        $paths['/api/airlines']['get'] = $op('Flights', 'Maskapai dengan kursi tersedia', $object(['data' => ['type' => 'array', 'items' => $string]]));
        $list = $op('Flights', 'Kursi tersedia per hari, filter jam/maskapai, pagination', $object(['data' => ['type' => 'array', 'items' => $ref('Ticket')], 'pagination' => $object(['page' => $int, 'per_page' => $int, 'total' => $int, 'total_pages' => $int]), 'timezone' => $string]));
        $list['parameters'] = [
            ['name' => 'date', 'in' => 'query', 'description' => 'Default hari ini, WIB', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'time', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => '13:00']],
            ['name' => 'airline', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'Batik Air']],
            ['name' => 'origin', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'CGK']],
            ['name' => 'destination', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'DPS']],
            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1]],
            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60, 'default' => 12]],
        ];
        $paths['/api/tickets']['get'] = $list;
        $paths['/api/tickets/{id}'] = ['parameters' => [$pathId('id')], 'get' => $op('Flights', 'Detail tiket dan metode pembayaran sebelum booking', $object(['ticket' => $ref('Ticket'), 'payment_methods' => $methods]))];
        $paths['/api/book']['get'] = $op('Bookings', 'Riwayat booking milik pengguna', $object(['data' => ['type' => 'array', 'items' => $ref('Booking')]]), null, true);
        $paths['/api/book']['post'] = $op('Bookings', 'Reservasi kursi maksimal 15 menit (transaction + row lock)', $object(['booking' => $ref('Booking'), 'payment_methods' => $methods]), $object(['ticket_id' => $int, 'passenger_name' => ['type' => 'string', 'maxLength' => 100]], ['ticket_id', 'passenger_name']), true, '201');
        $paths['/api/book/{id}'] = ['parameters' => [$pathId('id')], 'get' => $op('Bookings', 'Detail booking milik pengguna', $object(['booking' => $ref('Booking'), 'payment_methods' => $methods]), null, true)];
        $paths['/api/pay']['post'] = $op('Payments', 'Buat QR pembayaran dummy unik; scan URL untuk konfirmasi', $object(['booking_id' => $int, 'reference' => $string, 'amount' => $int, 'payment_method' => $string, 'payment_url' => ['type' => 'string', 'format' => 'uri'], 'barcode_url' => ['type' => 'string', 'format' => 'uri'], 'expires_at' => $string, 'dummy' => ['type' => 'boolean']]), $object(['booking_id' => $int, 'payment_method' => ['type' => 'string', 'enum' => ['qris', 'bank_transfer', 'ewallet']]], ['booking_id', 'payment_method']), true);
        $paths['/api/pay/{id}/barcode'] = ['parameters' => [$pathId('id')], 'get' => ['tags' => ['Payments'], 'summary' => 'QR SVG (pemilik booking, JWT diperlukan)', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'QR berisi payment_url', 'content' => ['image/svg+xml' => ['schema' => ['type' => 'string']]]], '401' => $error, '404' => $error]]];
        $paths['/api/payments/{token}/confirm'] = ['parameters' => [['name' => 'token', 'in' => 'path', 'required' => true, 'description' => 'Token acak 256-bit dari payment_url, perlakukan sebagai rahasia.', 'schema' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$']]], 'post' => $op('Payments', 'Konfirmasi dummy idempotent; kursi menjadi closed setelah sukses', $object(['reference' => $string, 'status' => ['type' => 'string', 'enum' => ['paid']], 'message' => $string]), $object([]))];
        return ['openapi' => '3.0.3', 'info' => ['title' => 'Skybook Flight Booking API', 'version' => '1.0.0', 'description' => 'Portofolio CodeIgniter 4 + MariaDB + JWT. Harga IDR dan jadwal Asia/Jakarta. Pembayaran sepenuhnya dummy. Reservasi 15 menit; QR berisi URL unik yang menjalankan konfirmasi POST ketika dibuka. Login hanya mengautentikasi; perubahan profil menggunakan PATCH /api/users/me. Gunakan Authorize dengan JWT dari register/login.'], 'servers' => [['url' => rtrim(base_url(), '/')]], 'tags' => array_map(static fn ($name) => ['name' => $name], ['Auth','Account','Flights','Bookings','Payments']), 'paths' => $paths, 'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']], 'schemas' => ['User' => $user, 'Ticket' => $ticket, 'Booking' => $booking, 'Error' => $object(['message' => $string, 'errors' => ['type' => 'object', 'additionalProperties' => $string]])]]];
    }
}
