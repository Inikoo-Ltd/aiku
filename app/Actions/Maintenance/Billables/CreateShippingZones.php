<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Sept 2025 14:55:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Billables;

use App\Actions\Ordering\ShippingZone\StoreShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShippingZones
{
    use AsAction;

    public function handle(ShippingZoneSchema $shippingZoneSchema, array $shippingZonesData)
    {
        foreach ($shippingZonesData as $shippingZoneData) {
            StoreShippingZone::make()->action(
                $shippingZoneSchema,
                $shippingZoneData,
            );
        }
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:create_shipping_zones {shippingZoneSchema}';
    }

    public function asCommand(Command $command): void
    {
        $shippingZoneSchema = ShippingZoneSchema::where('slug', $command->argument('shippingZoneSchema'))->first();
        ;
        ;

        $data['bg'] = [
            [
                'code'        => 'Z1',
                'name'        => 'Zone 1',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 250,
                            'price' => 9.95,
                        ],
                        [
                            'from'  => 250,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    [
                        'country_code' => 'BG'
                    ]
                ],
                'position'    => 5,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z2',
                'name'        => 'Zone 2',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 350,
                            'price' => 14.95,
                        ],
                        [
                            'from'  => 350,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    ['country_code' => 'HU'], // Hungary
                    ['country_code' => 'RO'], // Romania
                    ['country_code' => 'HR'], // Croatia
                    ['country_code' => 'SI'], // Slovenia
                    ['country_code' => 'AT'], // Austria
                    ['country_code' => 'SK'], // Slovakia
                    ['country_code' => 'CZ'], // Czechia
                    ['country_code' => 'IT'], // Italy
                ],
                'position'    => 4,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z3',
                'name'        => 'Zone 3',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 795,
                            'price' => 29.95,
                        ],
                        [
                            'from'  => 795,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    ['country_code' => 'FR'], // France
                    ['country_code' => 'DE'], // Germany
                    ['country_code' => 'BE'], // Belgium
                    ['country_code' => 'LU'], // Luxembourg
                    ['country_code' => 'ES'], // Spain
                    ['country_code' => 'PT'], // Portugal
                    ['country_code' => 'DK'], // Denmark
                    ['country_code' => 'LV'], // Latvia
                    ['country_code' => 'LT'], // Lithuania
                    ['country_code' => 'EE'], // Estonia
                ],
                'position'    => 3,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z4',
                'name'        => 'Zone 4',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 795,
                            'price' => 39.95,
                        ],
                        [
                            'from'  => 795,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    [
                        'country_code' => 'Gr'
                    ]
                ],
                'position'    => 2,
                'is_failover' => false,
            ],
            [
                'code'        => 'Other',
                'name'        => 'Rest of the world',
                'status'      => true,
                'price'       => [
                    'type' => 'TBC',
                ],
                'territories' => [],
                'position'    => 1,
                'is_failover' => true,
            ]
        ];

        $data['bg-1'] = [
            [
                'code'        => 'Z1',
                'name'        => 'Zone 1',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 150,
                            'price' => 4.95,
                        ],
                        [
                            'from'  => 150,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    [
                        'country_code' => 'BG'
                    ]
                ],
                'position'    => 5,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z2',
                'name'        => 'Zone 2',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 250,
                            'price' => 7.95,
                        ],
                        [
                            'from'  => 250,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    ['country_code' => 'HU'], // Hungary
                    ['country_code' => 'RO'], // Romania
                    ['country_code' => 'HR'], // Croatia
                    ['country_code' => 'SI'], // Slovenia
                    ['country_code' => 'AT'], // Austria
                    ['country_code' => 'SK'], // Slovakia
                    ['country_code' => 'CZ'], // Czechia
                    ['country_code' => 'IT'], // Italy
                ],
                'position'    => 4,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z3',
                'name'        => 'Zone 3',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 500,
                            'price' => 14.95,
                        ],
                        [
                            'from'  => 500,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    ['country_code' => 'FR'], // France
                    ['country_code' => 'DE'], // Germany
                    ['country_code' => 'BE'], // Belgium
                    ['country_code' => 'LU'], // Luxembourg
                    ['country_code' => 'ES'], // Spain
                    ['country_code' => 'PT'], // Portugal
                    ['country_code' => 'DK'], // Denmark
                    ['country_code' => 'LV'], // Latvia
                    ['country_code' => 'LT'], // Lithuania
                    ['country_code' => 'EE'], // Estonia
                ],
                'position'    => 3,
                'is_failover' => false,
            ],
            [
                'code'        => 'Z4',
                'name'        => 'Zone 4',
                'status'      => true,
                'price'       => [
                    'type'  => 'Step Order Items Net Amount',
                    'steps' => [
                        [
                            'from'  => 0,
                            'to'    => 500,
                            'price' => 19.95,
                        ],
                        [
                            'from'  => 500,
                            'to'    => "INF",
                            'price' => 0,
                        ]
                    ]
                ],
                'territories' => [
                    [
                        'country_code' => 'GR'
                    ]
                ],
                'position'    => 2,
                'is_failover' => false,
            ],
            [
                'code'        => 'Other',
                'name'        => 'Rest of the world',
                'status'      => true,
                'price'       => [
                    'type' => 'TBC',
                ],
                'territories' => [],
                'position'    => 1,
                'is_failover' => true,
            ]
        ];


        $this->handle($shippingZoneSchema, $data[$shippingZoneSchema->slug]);
    }


}
