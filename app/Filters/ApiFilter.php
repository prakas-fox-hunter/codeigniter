<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $limit = str_contains($request->getUri()->getPath(), '/auth/') ? 20 : 120;
        if (!service('throttler')->check('api-' . $limit . '-' . $request->getIPAddress(), $limit, 60)) {
            return service('response')->setStatusCode(429)->setHeader('Retry-After', '60')
                ->setJSON(['message' => 'Terlalu banyak permintaan. Coba lagi dalam satu menit.']);
        }
        if (in_array(strtoupper($request->getMethod()), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            if (!str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
                return service('response')->setStatusCode(415)->setJSON(['message' => 'Gunakan Content-Type: application/json.']);
            }
            try {
                $body = json_decode($request->getBody(), true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($body) || array_is_list($body) && $body !== []) {
                    throw new \RuntimeException();
                }
            } catch (\Throwable) {
                return service('response')->setStatusCode(400)->setJSON(['message' => 'Body JSON tidak valid.']);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store')->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
