<?php

namespace App\Libraries;

class BookingService
{
    public const METHODS = [
        ['id' => 'qris', 'name' => 'QRIS', 'description' => 'Scan QR unik untuk simulasi pembayaran.'],
        ['id' => 'bank_transfer', 'name' => 'Transfer bank', 'description' => 'Simulasi transfer melalui QR konfirmasi.'],
        ['id' => 'ewallet', 'name' => 'E-wallet', 'description' => 'Simulasi dompet digital melalui QR konfirmasi.'],
    ];

    public function create(int $userId, int $ticketId, string $passenger): array
    {
        $db = db_connect(); $db->transException(true)->transBegin();
        try {
            $ticket = $db->query('SELECT t.*, f.price, f.departure_at FROM tickets t JOIN flights f ON f.id = t.flight_id WHERE t.id = ? FOR UPDATE', [$ticketId])->getRowArray();
            if (!$ticket || $ticket['status'] !== 'open' || strtotime($ticket['departure_at']) <= time()) throw new \DomainException('Kursi tidak tersedia.', 409);
            $db->table('bookings')->where('ticket_id', $ticketId)->where('status', 'pending')->where('expires_at <=', date('Y-m-d H:i:s'))->update(['status' => 'expired', 'payment_token' => null]);
            if ($db->table('bookings')->where(['ticket_id' => $ticketId, 'status' => 'pending'])->countAllResults()) throw new \DomainException('Kursi sedang dipesan penumpang lain.', 409);
            $booking = ['reference' => 'SKY-' . strtoupper(bin2hex(random_bytes(6))), 'user_id' => $userId, 'ticket_id' => $ticketId, 'passenger_name' => $passenger, 'amount' => $ticket['price'], 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'expires_at' => date('Y-m-d H:i:s', min(time() + 900, strtotime($ticket['departure_at'])))];
            $db->table('bookings')->insert($booking); $booking['id'] = $db->insertID();
            $db->transCommit(); return $booking;
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }

    public function payment(int $userId, int $bookingId, string $method): array
    {
        if (!in_array($method, array_column(self::METHODS, 'id'), true)) throw new \DomainException('Metode pembayaran tidak valid.', 422);
        $db = db_connect(); $db->transException(true)->transBegin();
        try {
            $booking = $db->query('SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE', [$bookingId, $userId])->getRowArray();
            if (!$booking) throw new \DomainException('Booking tidak ditemukan.', 404);
            if ($booking['status'] !== 'pending' || strtotime($booking['expires_at']) <= time()) throw new \DomainException('Booking sudah dibayar, dibatalkan, atau kedaluwarsa.', 409);
            $token = $booking['payment_token'] ?: bin2hex(random_bytes(32));
            $db->table('bookings')->where('id', $bookingId)->update(['payment_token' => $token, 'payment_method' => $method]);
            $db->transCommit();
            return ['booking_id' => $bookingId, 'reference' => $booking['reference'], 'amount' => (int) $booking['amount'], 'payment_method' => $method, 'expires_at' => $booking['expires_at'], 'payment_url' => base_url('payment/' . $token), 'barcode_url' => base_url('api/pay/' . $bookingId . '/barcode'), 'dummy' => true];
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }

    public function complete(string $token): array
    {
        $db = db_connect();
        $candidate = $db->table('bookings')->where('payment_token', $token)->get()->getRowArray();
        if (!$candidate) throw new \DomainException('Kode pembayaran tidak valid.', 404);
        $db->transException(true)->transBegin();
        try {
            // Lock ticket before booking, matching the reservation lock order.
            $ticket = $db->query('SELECT * FROM tickets WHERE id = ? FOR UPDATE', [$candidate['ticket_id']])->getRowArray();
            $booking = $db->query('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$candidate['id']])->getRowArray();
            if (!$booking['payment_token'] || !hash_equals($booking['payment_token'], $token)) throw new \DomainException('Kode pembayaran tidak valid.', 404);
            if ($booking['status'] === 'paid') { $db->transCommit(); return ['reference' => $booking['reference'], 'status' => 'paid', 'message' => 'Pembayaran berhasil.']; }
            if ($booking['status'] !== 'pending' || strtotime($booking['expires_at']) <= time() || $ticket['status'] !== 'open') throw new \DomainException('Reservasi kedaluwarsa atau kursi tidak tersedia.', 409);
            $db->table('tickets')->where('id', $ticket['id'])->update(['status' => 'closed']);
            $db->table('bookings')->where('id', $booking['id'])->update(['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')]);
            $db->transCommit(); return ['reference' => $booking['reference'], 'status' => 'paid', 'message' => 'Pembayaran berhasil. Kursi Anda sudah dikonfirmasi.'];
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }
}
