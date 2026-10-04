<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FlightSeeder extends Seeder
{
    public function run()
    {
        $schedules = [
            ['Garuda Indonesia', 'GA-402', 'CGK', 'DPS', '09:00', 1250000],
            ['Batik Air', 'ID-6580', 'SUB', 'CGK', '13:00', 850000],
            ['Citilink', 'QG-110', 'CGK', 'YIA', '15:00', 650000],
        ];
        $this->db->transStart();
        for ($day = 0; $day < 30; $day++) {
            foreach ($schedules as [$airline, $number, $origin, $destination, $time, $price]) {
                $departure = date('Y-m-d', strtotime("+$day days")) . ' ' . $time . ':00';
                if ($this->db->table('flights')->where(['flight_number' => $number, 'departure_at' => $departure])->countAllResults()) {
                    continue;
                }
                $this->db->table('flights')->insert([
                    'airline' => $airline, 'flight_number' => $number, 'origin' => $origin,
                    'destination' => $destination, 'departure_at' => $departure,
                    'arrival_at' => date('Y-m-d H:i:s', strtotime($departure . ' +2 hours')), 'price' => $price,
                ]);
                $id = $this->db->insertID();
                for ($row = 1; $row <= 4; $row++) {
                    foreach (range('A', 'F') as $letter) {
                        $this->db->table('tickets')->insert(['flight_id' => $id, 'seat_number' => $row . $letter]);
                    }
                }
            }
        }
        $this->db->transComplete();
    }
}
