<?php

namespace App\Support;

/** Major Indian cities with their centre (lat, lng): city picker, nearest-city lookup and the EV finder. */
class Cities
{
    public const ALL = [
        'Delhi' => [28.6139, 77.2090], 'Mumbai' => [19.0760, 72.8777], 'Bengaluru' => [12.9716, 77.5946], 'Hyderabad' => [17.3850, 78.4867],
        'Chennai' => [13.0827, 80.2707], 'Kolkata' => [22.5726, 88.3639], 'Pune' => [18.5204, 73.8567], 'Ahmedabad' => [23.0225, 72.5714],
        'Jaipur' => [26.9124, 75.7873], 'Lucknow' => [26.8467, 80.9462], 'Chandigarh' => [30.7333, 76.7794], 'Kochi' => [9.9312, 76.2673],
        'Indore' => [22.7196, 75.8577], 'Surat' => [21.1702, 72.8311], 'Gurugram' => [28.4595, 77.0266], 'Noida' => [28.5355, 77.3910],
        'Bhopal' => [23.2599, 77.4126], 'Nagpur' => [21.1458, 79.0882], 'Coimbatore' => [11.0168, 76.9558], 'Visakhapatnam' => [17.6868, 83.2185],
    ];

    /** Nearest listed city to a point, or null when none is within $maxKm. */
    public static function nearest(float $lat, float $lng, float $maxKm = 70): ?array
    {
        $best = null;
        foreach (self::ALL as $name => [$la, $lo]) {
            $p = M_PI / 180;
            $a = 0.5 - cos(($la - $lat) * $p) / 2 + cos($lat * $p) * cos($la * $p) * (1 - cos(($lo - $lng) * $p)) / 2;
            $km = 2 * 6371 * asin(sqrt(max(0, $a)));
            if ($km <= $maxKm && (! $best || $km < $best['km'])) $best = ['name' => $name, 'lat' => $la, 'lng' => $lo, 'km' => $km];
        }
        return $best;
    }
}
