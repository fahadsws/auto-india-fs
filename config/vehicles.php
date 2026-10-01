<?php

/** Vehicle types in the new-vehicle catalog. `path` is the public URL prefix; `budgets` are rupee bands: key => [label, min, max]. */
return [
    'car' => [
        'label' => 'Car', 'plural' => 'Cars', 'path' => 'new-cars', 'route' => 'newcars', 'schema' => 'Car',
        'budgets' => [
            'u5' => ['Under ₹5 Lakh', 0, 500000],
            '5-10' => ['₹5 - 10 Lakh', 500000, 1000000],
            '10-20' => ['₹10 - 20 Lakh', 1000000, 2000000],
            '20-50' => ['₹20 - 50 Lakh', 2000000, 5000000],
            'a50' => ['Above ₹50 Lakh', 5000000, null],
        ],
    ],
    'bike' => [
        'label' => 'Bike', 'plural' => 'Bikes', 'path' => 'new-bikes', 'route' => 'newbikes', 'schema' => 'Motorcycle',
        'budgets' => [
            'u1' => ['Under ₹1 Lakh', 0, 100000],
            '1-2' => ['₹1 - 2 Lakh', 100000, 200000],
            '2-4' => ['₹2 - 4 Lakh', 200000, 400000],
            'a4' => ['Above ₹4 Lakh', 400000, null],
        ],
    ],
    'truck' => [
        'label' => 'Truck', 'plural' => 'Trucks', 'path' => 'new-trucks', 'route' => 'newtrucks', 'schema' => 'Vehicle',
        'budgets' => [
            'u10' => ['Under ₹10 Lakh', 0, 1000000],
            '10-25' => ['₹10 - 25 Lakh', 1000000, 2500000],
            '25-50' => ['₹25 - 50 Lakh', 2500000, 5000000],
            'a50' => ['Above ₹50 Lakh', 5000000, null],
        ],
    ],
];
