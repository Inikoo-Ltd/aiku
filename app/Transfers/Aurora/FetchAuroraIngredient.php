<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Models\Goods\Ingredient;
use Illuminate\Support\Facades\DB;

class FetchAuroraIngredient extends FetchAurora
{
    protected function parseModel(): void
    {


        if (!DB::connection('aurora')->table('Part Material Bridge')->where('Material Key', $this->auroraModelData->{'Material Key'})->exists()) {
            return;
        }

        $name = $this->auroraModelData->{'Material Name'};
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        $name = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $name);

        list($name, $extraIngredients, $suffix) = $this->parseMultipleIngredients($name);


        $parsedIngredient = $this->parseIngredientName($name);
        $name             = $parsedIngredient['name'];
        $tradeUnitArg     = $parsedIngredient['trade_unit_args'];

        if ($suffix) {
            $tradeUnitArg['suffix'] = $suffix;
        }


        if ($name == null) {
            return;
        }
        if (count($tradeUnitArg) > 0) {
            $this->parsedData['trade_unit_args'] = $tradeUnitArg;
        }

        $this->parsedData['extra_ingredients'] = $extraIngredients;


        $this->parsedData['ingredient'] = [
            'name'            => $name,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Material Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function parseMultipleIngredients($name): array
    {
        $name = trim($name);
        $suffix           = '';
        $extraIngredients = [];

        if ($name == 'Lemon & Lime Fragrance Oil') {
            return [$name, $extraIngredients, $suffix];
        }


        if ($name == 'CI 45430 CI 77891') {
            $name             = 'CI 45430';
            $extraIngredients = $this->processExtra($extraIngredients, 'CI 77891');
        }

        if ($name == 'Cinnamyl Alcohol Chamomile and Honey - Sodium Bicarbonate') {
            $name             = 'Cinnamyl Alcoho';
            $extraIngredients = $this->processExtra($extraIngredients, 'Chamomile');
            $extraIngredients = $this->processExtra($extraIngredients, 'Honey');
            $extraIngredients = $this->processExtra($extraIngredients, 'Sodium Bicarbonate');


        }





        if (preg_match('/(.+)&(.+)Essential Oils in Grapeseed Oil/', $name, $matches)) {
            $name             = $matches[1].' Essential Oil';
            $extraIngredients = $this->processExtra($extraIngredients, $matches[2]);
            $suffix           = 'In Grapeseed Oil';

            return [$name, $extraIngredients, $suffix];
        }


        if ($name == 'CI 16255. With Himalayan Salt & Rose Petals') {
            $name             = 'CI 16255';
            $extraIngredients = $this->processExtra($extraIngredients, 'Himalayan Salt');
            $extraIngredients = $this->processExtra($extraIngredients, 'Rose Petals');
            return [$name, $extraIngredients, $suffix];
        }

        if ($name == 'CI 18050. With Himalayan salt & Lavender flowers') {
            $name             = 'CI 18050';
            $extraIngredients = $this->processExtra($extraIngredients, 'Himalayan Salt');
            $extraIngredients = $this->processExtra($extraIngredients, 'Lavender Flowers');
            return [$name, $extraIngredients, $suffix];
        }

        if ($name == 'CI 45430. With Himalayan salt & marigold petals') {
            $name             = 'CI 45430';
            $extraIngredients = $this->processExtra($extraIngredients, 'Himalayan Salt');
            $extraIngredients = $this->processExtra($extraIngredients, 'Marigold Petals');
            return [$name, $extraIngredients, $suffix];
        }

        if ($name == 'CI 47005. With Himalayan salt & Sunflower petals') {
            $name             = 'CI 47005';
            $extraIngredients = $this->processExtra($extraIngredients, 'Himalayan Salt');
            $extraIngredients = $this->processExtra($extraIngredients, 'Sunflower Petals');
            return [$name, $extraIngredients, $suffix];
        }


        if (
            $name == 'Calendula (infused) Calendula Officinalis Flower & Helianthus Annuus Seed Oil Origin: United Kingdom Extraction Method: Natural herb infusion' or
            $name == 'Calendula Officinalis Flower & Helianthus Annuus' or
            $name == 'Calendula Officinalis Flower & Helianthus Annuus Seed Oil') {
            $name             = 'Calendula Officinalis Flower';
            $extraIngredients = $this->processExtra($extraIngredients, 'Helianthus Annuus Seed Oil');
            return [$name, $extraIngredients, $suffix];

        }

        if ($name == 'Peppermint & Grapeseed Oil') {
            $name             = 'Peppermint Oil';
            $extraIngredients = $this->processExtra($extraIngredients, 'Grapeseed Oil');
            return [$name, $extraIngredients, $suffix];
        }

        if ($name == 'Peppermint & Eucalyptus Essential Oils') {
            $name             = 'Peppermint Essential Oil';
            $extraIngredients = $this->processExtra($extraIngredients, 'Eucalyptus Essential Oil');
            return [$name, $extraIngredients, $suffix];
        }


        if ($name == 'Peppermint & Tea Tree - Sodium Bicarbonate') {
            $name             = 'Peppermint';
            $extraIngredients = $this->processExtra($extraIngredients, 'Tea Tree');
            $extraIngredients = $this->processExtra($extraIngredients, 'Sodium Bicarbonate');
            return [$name, $extraIngredients, $suffix];
        }

        $ingredients = preg_split('/\s*&\s*/', $name);
        if (count($ingredients) > 1) {
            $name = $ingredients[0];
            for ($i = 1;$i < count($ingredients);$i++) {
                $extraIngredients = $this->processExtra($extraIngredients, $ingredients[$i]);
            }
        }



        return [$name, $extraIngredients, $suffix];
    }

    protected function processExtra($extraIngredients, $name): array
    {
        $name               = trim($name);
        $ingredient         = $this->storeExtraIngredient($name);
        $extraIngredients[] = $ingredient->id;

        return $extraIngredients;
    }


    protected function parseIngredientName($name): array
    {
        $prefix        = '';
        $suffix        = '';
        $notes         = '';
        $concentration = '';
        $purity        = '';
        $percentage    = '';
        $aroma         = '';

        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        $name = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $name);


        if (is_numeric($name) or $name == '' or strlen($name) == 1 or
            in_array(
                $name,
                [
                    '2 sheets of 18 Front Labels and 2 sheets of 18 Back (Ingredients) Labels',
                    '2 sheets of 30 Front Labels and 2 sheets of 30 Back (Ingredients) Labels',
                    '36x Front Label 36x Back Label',
                    'Bath',
                    'Bath Fizzes',
                    'Bath Bombs',
                    'A3 Printed Poster',
                    '*Occur naturally in Essential Oils',
                    '*Naturally occurring in Essential Oils',
                    'Gift Box',
                    'H: 2 cm L: 2.8 cm l: 4 cm environ',
                    'Label',
                    '5%)',
                    '37x40',
                    '21x',
                    'xx',
                    'www.ancient-wisdom.info',
                    '24hrs Burning time',
                    '30 hours burning time',
                    'Temps de combustion: 30h',
                    'Not Suitable for children under 36 months. This product contains pieces which may present a choking hazard',
                    'Not Available',
                    'Unfragranced',

                ]
            )) {
            return [
                'name'            => null,
                'trade_unit_args' => []
            ];
        }


        $name = preg_replace('/^\.\*/', '', $name);

        if (preg_match('/^\*\s*/', $name)) {
            $name   = preg_replace('/^\*\s*/', '', $name);
            $prefix = '*';
        }
        if (preg_match('/\s*\*$/', $name)) {
            $name   = preg_replace('/\s*\*$/', '', $name);
            $prefix = '*';
        }
        if (preg_match('/^±\s*/', $name)) {
            $name   = preg_replace('/^±\s*/', '', $name);
            $prefix = '±';
        }
        if (preg_match('|^\(\+/- \s*|', $name)) {
            $name   = preg_replace('|^\(\+/- \s*|', '', $name);
            $prefix = '±';
        }
        if (preg_match('|^\(\+/- \s*|', $name)) {
            $name   = preg_replace('|^\(\+/- \s*|', '', $name);
            $prefix = '±';
        }
        $name = preg_replace('/^"\s*/', '', $name);
        $name = preg_replace('/^~\s*/', '', $name);
        $name = preg_replace('/^\s*-\s*/', '', $name);
        $name = preg_replace('/^\.\s*/', '', $name);
        $name = preg_replace('/\s+\.\s*$/', '', $name);


        $name = preg_replace('/\s*\.*\s*\*?\s*(Occur naturally|Naturally occurring) in Essential Oils?$/', '', $name);

        $name = preg_replace('/\s*\*\s*Occurs naturally in Essential Oils$/', '', $name);

        $name = preg_replace('/^Ingredients\s*([:\-])\s*/', '', $name);


        $name = preg_replace('/\(\+\/Cl |\(\+\/_CI /', 'CI ', $name);

        if ($name == '(+/-Titanium Dioxide)') {
            $name   = 'Titanium Dioxide';
            $suffix = '±';
        }
        if ($name == '30 hours burning time. Wax') {
            $name = 'Wax';
        }
        if ($name == 'Acrilic Paint') {
            $name = 'Acrylic Paint';
        }
        if ($name == 'Terracota') {
            $name = 'Terracotta';
        }

        if ($name == 'Eugenol)') {
            $name = 'Eugenol';
        }

        if ($name == 'Coumarin)') {
            $name = 'Coumarin';
        }

        if ($name == 'Coconut) Oil)') {
            $name = 'Coconut Oil';
        }

        if ($name == 'Brass Metal') {
            $name = 'Brass';
        }


        if ($name == 'Citric A') {
            $name = 'Citric Acid';
        }


        if (in_array(
            $name,
            [
                'Alpah-Isomethyl Ionone',
                'Alph-Isomethyl Ionone',
                'Alpha Isomethyl Ionone',
                'Alpha isomethly ionone)',
                'Alpha-Iso-methylionone',
                'Alpha-Isomethyl Ionone',
                'Alpha-Isomethyl Ionone)',
                'Alpha-Isonethyl Ionone',
                'Alphaisomethyl Ionone',
                'Alpho-Isomethyl Ionone'
            ]
        )) {
            $name = 'α-Isomethyl Ionone';
        }


        if ($name == 'Glasses') {
            $name = 'Glass';
        }


        if ($name == 'Incense stick') {
            $name = 'Incense Sticks';
        }


        if ($name == 'Stainless' or $name == 'Stainless Steal') {
            $name = 'Stainless Steel';
        }

        if ($name == 'Teak' or $name == 'Teakwood' or $name == 'Teakwood wood') {
            $name = 'Teak Wood';
        }

        if ($name == 'Soy-bean Wax' or $name == 'Soywax') {
            $name = 'Soy Wax';
        }

        if ($name == 'Plastik') {
            $name = 'Plastic';
        }
        if ($name == 'Ruber') {
            $name = 'Rubber';
        }

        if ($name == 'Salt-50') {
            $name = 'Salt';
        }
        if ($name == 'Soaps') {
            $name = 'Soap';
        }

        if ($name == "Product Ingredients Lavender: Sodium Bicarbonate" or
            $name == "Ingredients Sodium Bicarbonate" or
            $name == 'Ingredients / Ingrédients / Bestandteile / Ingredientes / Skład / Ingredienti : Sodium Bicarbonate') {
            $name = 'Sodium Bicarbonate';
        }

        if ($name == 'CI 18050.') {
            $name = 'CI 18050';
        }
        if ($name == 'C.I. 14.720') {
            $name = 'CI 14720';
        }
        if ($name == 'C.I. 17.200') {
            $name = 'CI 17200';
        }
        if ($name == 'C.I. 50.420') {
            $name = 'CI 50420';
        }
        if ($name == 'C.I. 42.045') {
            $name = 'CI 42045';
        }

        if ($name == 'C16255') {
            $name = 'CI 16255';
        }
        if ($name == 'C.I. 77.891') {
            $name = 'CI 77891';
        }

        if ($name == 'Chlorophyl') {
            $name = 'Chlorophyll';
        }


        if ($name == 'Sucrosa') {
            $name = 'Sucrose';
        }

        if ($name == 'WATER') {
            $name = 'Water';
        }


        if ($name == 'Adam: Sodium Bicarbonate') {
            $name = 'Sodium Bicarbonate';
        }


        if (in_array($name, ['Acrilic', 'Acrillic'])) {
            $name = 'Acrylic';
        }

        if (in_array($name, ['Albesia', 'Albacia wood', 'Albasia Wood', 'Albesia Wood', 'Albesia Wood Stand', 'Albesian Wood', 'Albacia wood', 'Albasia Wood'])) {
            $name = 'Albasia Wood';
        }

        if (in_array($name, ['6-Trimetyl-2- Cyclohexen-1-Yl)-3-Buten-2-One', '6-Trimetyl-2-Cyclohexen-1-Yl) -3-Buten-2-One', '6-Trimetyl-2-Cyclohexen-1-Yl)-3- Buten-2-One'])) {
            $name = '6-Trimetyl-2-Cyclohexen-1-Yl)-3-Buten-2-One';
        }


        if (in_array($name, ['. *Naturally occurring in Essential Oils', '. *Occur naturally in Essential Oils', '. *Occur naturally in Essential Oils. Do not use internally. Keep out of reach of children'])) {
            return [
                'name'            => null,
                'trade_unit_args' => []
            ];
        }

        if (preg_match('/^\(.+\)/', $name)) {
            $name = preg_replace('/^\(\s*/', '', $name);
            $name = preg_replace('/^\s*\)/', '', $name);
        }

        if (preg_match('/^CI(\d+)$/', $name, $matches)) {
            $name = 'CI '.$matches[1];
        }
        if (preg_match('/^C\.I\.\s+(\d+)$/', $name, $matches)) {
            $name = 'CI '.$matches[1];
        }

        if (preg_match('/^Cl\s*(\d+)$/', $name, $matches)) {
            $name = 'CI '.$matches[1];
        }

        if ($name == '') {
            return [
                'name'            => null,
                'trade_unit_args' => []
            ];
        }

        if (preg_match('/(\d+) silver/i', $name, $matches)) {
            $name   = 'Silver';
            $purity = $matches[1];
        }

        if (preg_match('/(\d+K) gold/i', $name, $matches)) {
            $name   = 'Gold';
            $purity = strtolower($matches[1]);
        }

        if ($name == '100% Acrylic') {
            $name = 'Acrylic';
        }


        if (in_array($name, ['Gemstone / beads', 'Gemstone/Bead', 'Gemstone/beads'])) {
            $name   = 'Gemstone';
            $suffix = 'Beads';
        }


        if (preg_match('/(\d+)\s*%\s+(.+)/i', $name, $matches)) {
            $name       = $matches[2];
            $percentage = $matches[1].'%';
        }


        if (is_numeric($name) or $name == '' or strlen($name) == 1 or
            in_array(
                $name,
                [
                    '*Occur naturally in Essential Oils. Do not use internally. Keep out of reach of children',
                    '5%)',
                    '4hrs Burning time',
                    'SERF-01 BSV-Relax BSV-Detox BSV-Skin Revive Lavender & Clary Sage Lotion Coffee Sugar Scrub 80g',
                    'SERF-03 BSV-Sensual BSV-Energise BSV-Clarity Sandalwood & Rose Lotion 60g Strawberry Sugar Scrub 80g',
                    'Soybean Candles - Pomegranate & Orange',
                    'Soybean Jar Candles - Cucumber & Mint',
                    'Soybean Jar Candles - Fig & Cassis',
                    'Soybean Jar Candles - Grapefruit & Ginger',
                    'Soybean Jar Candles - Lavender & Basil',
                    'Soybean Jar Candles - Lily & Jasmine',
                    'Himalayan Salt Lamp - & Base',
                    'Sheesham Wood and All ingrediences from ExerScrub Soaps',
                    'Incense sticks are made from bamboo sticks coated in a special paste and dipped in fragrance'


                ]
            )) {
            return [
                'name'            => null,
                'trade_unit_args' => []
            ];
        }

        if ($name == '15 hours burning time. fragrance - lemon & lime') {
            $name  = 'Fragrance';
            $aroma = 'lemon & lime';
        }


        $tradeUnitArg = [];
        if ($prefix) {
            $tradeUnitArg['prefix'] = $prefix;
        }
        if ($suffix) {
            $tradeUnitArg['suffix'] = $suffix;
        }
        if ($notes) {
            $tradeUnitArg['notes'] = $notes;
        }
        if ($concentration) {
            $tradeUnitArg['concentration'] = $concentration;
        }
        if ($purity) {
            $tradeUnitArg['purity'] = $purity;
        }

        if ($aroma) {
            $tradeUnitArg['aroma'] = $aroma;
        }
        if ($percentage) {
            $tradeUnitArg['percentage'] = $percentage;
        }


        return [
            'name'            => $name,
            'trade_unit_args' => $tradeUnitArg
        ];
    }

    protected function storeExtraIngredient($name)
    {
        $ingredient = Ingredient::whereRaw('LOWER(name)=? ', [trim(strtolower($name))])->first();
        if ($ingredient) {
            return $ingredient;
        }

        //print "try to store extra ingredient >>>$name<<<\n";

        return StoreIngredient::make()->action(
            group: group(),
            modelData: [
                'name' => $name,
            ],
            hydratorsDelay: 60,
            strict: false,
            audit: false
        );
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Material Dimension')
            ->where('Material Key', $id)->first();
    }
}
