<?php
// CLI-only fixture helper. Mutations are restricted to demo integration-test users.
if (PHP_SAPI !== 'cli') exit(1);
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
date_default_timezone_set('Asia/Jakarta');
if (env('CI_ENVIRONMENT') !== 'development' || !env('app.demo', false)) exit(1);
$db = db_connect();
$user = $db->table('users')->where('id', (int) ($argv[2] ?? 0))->get()->getRowArray();
if (!$user || !preg_match('/^api-\d+@example\.com$/', $user['email'])) exit(1);
if ($argv[1] === 'expire') {
    $db->table('bookings')->where(['id' => (int) $argv[3], 'user_id' => $user['id'], 'status' => 'pending'])->update(['expires_at' => date('Y-m-d H:i:s', time() - 5)]);
    echo json_encode(['ok' => true]);
} elseif ($argv[1] === 'race') {
    while (microtime(true) < (float) $argv[4]) usleep(10000);
    try {
        $booking = (new App\Libraries\BookingService())->create((int) $user['id'], (int) $argv[3], 'Concurrency Traveler');
        echo json_encode(['code' => 201, 'booking' => $booking]);
    } catch (DomainException $error) {
        echo json_encode(['code' => $error->getCode()]);
    }
}
