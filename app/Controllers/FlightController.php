<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\BookingService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class FlightController extends BaseController
{
    private function fail(\DomainException $error)
    {
        return $this->response->setStatusCode($error->getCode() ?: 422)->setJSON(['message' => $error->getMessage()]);
    }

    public function tickets()
    {
        $q = $this->request->getGet();
        $rules = ['date' => 'permit_empty|valid_date[Y-m-d]', 'time' => 'permit_empty|regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]', 'airline' => 'permit_empty|max_length[100]', 'origin' => 'permit_empty|exact_length[3]|alpha', 'destination' => 'permit_empty|exact_length[3]|alpha', 'page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[10000]', 'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[60]'];
        if (!service('validation')->setRules($rules)->run($q)) return $this->response->setStatusCode(422)->setJSON(['message' => 'Filter tidak valid.', 'errors' => service('validation')->getErrors()]);
        $page = max(1, (int) ($q['page'] ?? 1)); $limit = empty($q['per_page']) ? 12 : (int) $q['per_page']; $db = db_connect();
        $builder = $db->table('tickets t')->join('flights f', 'f.id = t.flight_id')->where('t.status', 'open')->where('f.departure_at >', date('Y-m-d H:i:s'));
        $builder->where('NOT EXISTS (SELECT 1 FROM bookings b WHERE b.ticket_id = t.id AND b.status = \'pending\' AND b.expires_at > ' . $db->escape(date('Y-m-d H:i:s')) . ')', null, false);
        $builder->where('DATE(f.departure_at)', !empty($q['date']) ? $q['date'] : date('Y-m-d'));
        if (!empty($q['time'])) $builder->where('TIME(f.departure_at)', $q['time'] . ':00');
        foreach (['airline', 'origin', 'destination'] as $field) if (!empty($q[$field])) $builder->where('f.' . $field, $q[$field]);
        $total = $builder->countAllResults(false);
        $data = $builder->select('t.id, t.seat_number, t.status, f.id AS flight_id, f.airline, f.flight_number, f.origin, f.destination, f.departure_at, f.arrival_at, f.price')->orderBy('f.departure_at')->orderBy('t.id')->get($limit, ($page - 1) * $limit)->getResultArray();
        return $this->response->setJSON(['data' => $data, 'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total, 'total_pages' => (int) ceil($total / $limit)], 'timezone' => 'Asia/Jakarta']);
    }

    public function airlines()
    {
        $now = date('Y-m-d H:i:s');
        $data = db_connect()->query("SELECT DISTINCT f.airline FROM flights f JOIN tickets t ON t.flight_id = f.id WHERE t.status = 'open' AND f.departure_at > ? AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.ticket_id = t.id AND b.status = 'pending' AND b.expires_at > ?) ORDER BY f.airline", [$now, $now])->getResultArray();
        return $this->response->setJSON(['data' => array_column($data, 'airline')]);
    }

    public function ticket($id)
    {
        $ticket = db_connect()->table('tickets t')->select('t.*, f.airline, f.flight_number, f.origin, f.destination, f.departure_at, f.arrival_at, f.price')->join('flights f', 'f.id = t.flight_id')->where('t.id', $id)->get()->getRowArray();
        if (!$ticket) return $this->response->setStatusCode(404)->setJSON(['message' => 'Tiket tidak ditemukan.']);
        $held = db_connect()->table('bookings')->where(['ticket_id' => $id, 'status' => 'pending'])->where('expires_at >', date('Y-m-d H:i:s'))->countAllResults();
        $ticket['available'] = $ticket['status'] === 'open' && !$held && strtotime($ticket['departure_at']) > time();
        return $this->response->setJSON(['ticket' => $ticket, 'payment_methods' => BookingService::METHODS]);
    }

    public function book()
    {
        $data = $this->request->getJSON(true);
        if (is_string($data['passenger_name'] ?? null)) $data['passenger_name'] = trim($data['passenger_name']);
        if (!service('validation')->setRules(['ticket_id' => 'required|is_natural_no_zero', 'passenger_name' => 'required|max_length[100]'])->run($data)) return $this->response->setStatusCode(422)->setJSON(['message' => 'ID tiket dan nama penumpang diperlukan.']);
        try {
            $booking = (new BookingService())->create((int) Auth::user()['id'], (int) $data['ticket_id'], trim($data['passenger_name']));
            return $this->response->setStatusCode(201)->setJSON(['booking' => $booking, 'payment_methods' => BookingService::METHODS]);
        } catch (\DomainException $error) { return $this->fail($error); }
    }

    private function bookingQuery()
    {
        return db_connect()->table('bookings b')->select('b.id, b.reference, b.user_id, b.ticket_id, b.passenger_name, b.amount, b.status, b.payment_method, b.created_at, b.expires_at, b.paid_at, t.seat_number, f.airline, f.flight_number, f.origin, f.destination, f.departure_at, f.arrival_at')->join('tickets t', 't.id = b.ticket_id')->join('flights f', 'f.id = t.flight_id')->where('b.user_id', Auth::user()['id']);
    }

    private function effectiveStatus(array $booking): array
    {
        if ($booking['status'] === 'pending' && strtotime($booking['expires_at']) <= time()) $booking['status'] = 'expired';
        return $booking;
    }

    public function bookings()
    {
        return $this->response->setJSON(['data' => array_map($this->effectiveStatus(...), $this->bookingQuery()->orderBy('b.id', 'DESC')->get()->getResultArray())]);
    }

    public function booking($id)
    {
        $booking = $this->bookingQuery()->where('b.id', $id)->get()->getRowArray();
        return $booking ? $this->response->setJSON(['booking' => $this->effectiveStatus($booking), 'payment_methods' => BookingService::METHODS]) : $this->response->setStatusCode(404)->setJSON(['message' => 'Booking tidak ditemukan.']);
    }

    public function pay()
    {
        $data = $this->request->getJSON(true);
        if (!service('validation')->setRules(['booking_id' => 'required|is_natural_no_zero', 'payment_method' => 'required|in_list[qris,bank_transfer,ewallet]'])->run($data)) return $this->response->setStatusCode(422)->setJSON(['message' => 'Booking dan metode pembayaran tidak valid.']);
        try { return $this->response->setJSON((new BookingService())->payment((int) Auth::user()['id'], (int) $data['booking_id'], $data['payment_method'])); }
        catch (\DomainException $error) { return $this->fail($error); }
    }

    public function barcode($id)
    {
        $booking = db_connect()->table('bookings')->where(['id' => $id, 'user_id' => Auth::user()['id'], 'status' => 'pending'])->where('expires_at >', date('Y-m-d H:i:s'))->get()->getRowArray();
        if (!$booking || !$booking['payment_token']) return $this->response->setStatusCode(404)->setJSON(['message' => 'Kode pembayaran tidak tersedia.']);
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd()));
        return $this->response->setContentType('image/svg+xml')->setBody($writer->writeString(base_url('payment/' . $booking['payment_token'])));
    }

    public function confirm($token)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return $this->response->setStatusCode(404)->setJSON(['message' => 'Kode tidak valid.']);
        try { return $this->response->setJSON((new BookingService())->complete($token)); }
        catch (\DomainException $error) { return $this->fail($error); }
    }
}
