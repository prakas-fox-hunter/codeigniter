<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSkybook extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
            token_version INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL
        ) ENGINE=InnoDB");
        $this->db->query("CREATE TABLE password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $this->db->query("CREATE TABLE flights (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, airline VARCHAR(100) NOT NULL,
            flight_number VARCHAR(20) NOT NULL, origin CHAR(3) NOT NULL, destination CHAR(3) NOT NULL,
            departure_at DATETIME NOT NULL, arrival_at DATETIME NOT NULL, price INT UNSIGNED NOT NULL,
            UNIQUE KEY flight_schedule (flight_number, departure_at), INDEX (departure_at)
        ) ENGINE=InnoDB");
        $this->db->query("CREATE TABLE tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, flight_id INT UNSIGNED NOT NULL,
            seat_number VARCHAR(5) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'open',
            UNIQUE KEY flight_seat (flight_id, seat_number),
            FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $this->db->query("CREATE TABLE bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(30) NOT NULL UNIQUE,
            user_id INT UNSIGNED NULL, ticket_id INT UNSIGNED NOT NULL,
            passenger_name VARCHAR(100) NOT NULL, amount INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', payment_method VARCHAR(30) NULL,
            payment_token CHAR(64) NULL UNIQUE, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
            paid_at DATETIME NULL, INDEX ticket_pending (ticket_id, status, expires_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ) ENGINE=InnoDB");
    }

    public function down()
    {
        foreach (['bookings', 'tickets', 'flights', 'password_resets', 'users'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
