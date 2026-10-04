<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', 'Pages::home');
$routes->get('flights', 'Pages::flights');
$routes->get('login', 'Pages::login');
$routes->get('register', 'Pages::register');
$routes->get('forgot-password', 'Pages::forgot');
$routes->get('reset-password', 'Pages::reset');
$routes->get('account', 'Pages::account');
$routes->get('bookings', 'Pages::bookings');
$routes->get('checkout/(:num)', 'Pages::checkout/$1');
$routes->get('payment/(:segment)', 'Pages::payment/$1');
$routes->get('docs', 'Pages::docs');
$routes->get('openapi.json', 'Pages::openapi');
$routes->group('api', ['filter' => 'api'], static function ($routes) {
    $routes->post('auth/register', 'ApiController::register');
    $routes->post('auth/login', 'ApiController::login');
    $routes->post('auth/forgot-password', 'ApiController::forgotPassword');
    $routes->post('auth/reset-password', 'ApiController::resetPassword');
    $routes->get('tickets', 'FlightController::tickets');
    $routes->get('tickets/(:num)', 'FlightController::ticket/$1');
    $routes->get('airlines', 'FlightController::airlines');
    $routes->post('payments/(:segment)/confirm', 'FlightController::confirm/$1');
    $routes->group('', ['filter' => 'jwt'], static function ($routes) {
        $routes->get('users/me', 'ApiController::me');
        $routes->patch('users/me', 'ApiController::updateUser');
        $routes->delete('users/me', 'ApiController::deleteUser');
        $routes->post('auth/logout', 'ApiController::logout');
        $routes->post('book', 'FlightController::book');
        $routes->get('book', 'FlightController::bookings');
        $routes->get('book/(:num)', 'FlightController::booking/$1');
        $routes->post('pay', 'FlightController::pay');
        $routes->get('pay/(:num)/barcode', 'FlightController::barcode/$1');
    });
});
