<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Nov 2023 19:17:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query;

use App\Enums\CRM\Prospect\ProspectStateEnum;
use Exception;
use Illuminate\Support\Arr;

trait WithQueryCompiler
{
    private array $arguments = [];
    private array $joins     = [];
    private bool $returnZero = false;

    /**
     * @throws \Exception
     */
    public function compileConstrains(array $constrains): array
    {
        $compiledConstrains = [];
        foreach ($constrains as $type => $constrain) {
            if ($compiledConstrain = $this->compileConstrain($type, $constrain)) {
                $compiledConstrains[] = $compiledConstrain;
            }
        }

        return [
            'constrains'  => $compiledConstrains,
            'arguments'   => $this->arguments,
            'joins'       => $this->joins,
            'returnZero'  => $this->returnZero
        ];
    }


    public function compileConstrain(string $type, array $constrainData): ?array
    {
        try {
            $compiledConstrain = match ($type) {
                'can_contact_by' => $this->canContactBy($constrainData),
                'with'           => [
                    'type'       => 'with',
                    'parameters' => $constrainData['fields']
                ],


                'prospect_last_contacted' => $this->prospectLastContactedConstrain($constrainData),

                default => throw new Exception('Unknown constrain type: '.Arr::get($constrainData, 'type', 'Type not set'))
            };
        } catch (Exception) {
            $compiledConstrain = null;
        }


        return $compiledConstrain;
    }




    public function canContactBy(array $constrainData): ?array
    {
        $compiledConstrains = [];
        foreach (Arr::get($constrainData, 'fields', []) as $field) {
            if (in_array($field, ['email', 'phone', 'address'])) {
                $compiledConstrains[] = [
                    'type'       => Arr::get($constrainData, 'logic', 'any') == 'any'
                        ?
                        count($compiledConstrains) > 0 ? 'orWhere' : 'where'
                        :
                        'where',
                    'parameters' => [
                        'can_contact_by_'.$field,
                        '=',
                        true
                    ]
                ];
            }
        }

        return match (count($compiledConstrains)) {
            0       => null,
            1       => $compiledConstrains[0],
            default => [
                'type'       => 'group',
                'parameters' => $compiledConstrains
            ]
        };
    }

    public function prospectLastContactedConstrain(array $constrain): array
    {

        $state = Arr::get($constrain, 'state');
        if (is_string($state)) {
            $state = $state == 'true';
        }


        if ($state) {
            $this->arguments['__date__'] = [
                'type'  => 'dateSubtraction',
                'value' => $constrain['argument']

            ];

            return [
                'type'       => 'group',
                'parameters' => [
                    [
                        'type'       => 'where',
                        'parameters' => [
                            'state',
                            '=',
                            ProspectStateEnum::NO_CONTACTED->value
                        ]
                    ],
                    [
                        'type'       => 'orGroup',
                        'parameters' => [
                            [
                                'type'       => 'where',
                                'parameters' => ['state', '=', ProspectStateEnum::CONTACTED->value],
                            ],
                            [
                                'type'       => 'where',
                                'parameters' => [
                                    'last_contacted_at',
                                    '<=',
                                    '__date__'
                                ],
                            ]


                        ]
                    ],
                ]
            ];
        } else {
            return [
                'type'       => 'where',
                'parameters' => [
                    'state',
                    '=',
                    ProspectStateEnum::NO_CONTACTED->value
                ]
            ];
        }
    }


}
