<?php

namespace App\Controllers;

class Pages extends BaseController
{
    private function page(string $page, string $title, array $data = []): string
    {
        $this->response->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer');
        return view('skybook/layout', array_merge($data, ['page' => $page, 'title' => $title]));
    }

    public function home() { return $this->page('home', 'Perjalanan hebat dimulai di sini'); }
    public function flights() { return $this->page('flights', 'Temukan penerbangan'); }
    public function login() { return $this->page('login', 'Selamat datang kembali'); }
    public function register() { return $this->page('register', 'Mulai perjalanan Anda'); }
    public function forgot() { return $this->page('forgot', 'Lupa password'); }
    public function reset() { return $this->page('reset', 'Password baru'); }
    public function account() { return $this->page('account', 'Akun saya'); }
    public function bookings() { return $this->page('bookings', 'Perjalanan saya'); }
    public function checkout($id) { return $this->page('checkout', 'Selesaikan pesanan', ['ticketId' => $id]); }
    public function payment($token) { return $this->page('payment', 'Konfirmasi pembayaran', ['paymentToken' => $token]); }
    public function docs() { return view('skybook/docs'); }
    public function openapi() { return $this->response->setJSON(\App\Libraries\OpenApi::document()); }
}
