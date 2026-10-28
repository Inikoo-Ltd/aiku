<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * @var array<string, array{sk: string, es: string}>
     */
    private array $translations = [
        'Base Oil'                                        => ['sk' => 'Základový olej', 'es' => 'Aceite base'],
        'Bentonite Clay Mask'                             => ['sk' => 'Maska z bentonitového ílu', 'es' => 'Mascarilla de arcilla bentonita'],
        'Benzoin (Dilute/Dpg) Essential Oil'              => ['sk' => 'Benzoínový esenciálny olej (riedený/DPG)', 'es' => 'Aceite esencial de benjuí (diluido/DPG)'],
        'Candles'                                         => ['sk' => 'Sviečky', 'es' => 'Velas'],
        'Canvas Bags'                                     => ['sk' => 'Plátené tašky', 'es' => 'Bolsas de lona'],
        'Caps for bottles'                                => ['sk' => 'Uzávery na fľaše', 'es' => 'Tapones para botellas'],
        'Carton Tape 4.8cm x 91m'                         => ['sk' => 'Lepiaca páska na kartóny 4,8 cm x 91 m', 'es' => 'Cinta de embalar 4,8 cm x 91 m'],
        'Castor Oil'                                      => ['sk' => 'Ricínový olej', 'es' => 'Aceite de ricino'],
        'Clay Face Mask'                                  => ['sk' => 'Ílová pleťová maska', 'es' => 'Mascarilla facial de arcilla'],
        'Coconut Organic Base Oil'                        => ['sk' => 'Bio kokosový základový olej', 'es' => 'Aceite base de coco ecológico'],
        'Cotton Bags'                                     => ['sk' => 'Bavlnené tašky', 'es' => 'Bolsas de algodón'],
        'Decorative Ornament'                             => ['sk' => 'Dekoratívna ozdoba', 'es' => 'Adorno decorativo'],
        'Deodorant Stone'                                 => ['sk' => 'Deodoračný kameň', 'es' => 'Piedra desodorante'],
        'Dry Flowers'                                     => ['sk' => 'Sušené kvety', 'es' => 'Flores secas'],
        'Essential Oil'                                   => ['sk' => 'Esenciálny olej', 'es' => 'Aceite esencial'],
        'Europalet Heat Treat 120x80x145cm'               => ['sk' => 'Europaleta tepelne ošetrená 120x80x145 cm', 'es' => 'Europalé tratado térmicamente 120x80x145 cm'],
        'Film Wrap'                                       => ['sk' => 'Baliaca fólia', 'es' => 'Film de embalar'],
        'Flower Water'                                    => ['sk' => 'Kvetová voda', 'es' => 'Agua floral'],
        'Fractionated Coconut Oil'                        => ['sk' => 'Frakcionovaný kokosový olej', 'es' => 'Aceite de coco fraccionado'],
        'Fragrance Enhancing Solution - 1Litre'           => ['sk' => 'Roztok na zosilnenie vône - 1 liter', 'es' => 'Solución potenciadora de fragancia - 1 litro'],
        'Fragrance Oil'                                   => ['sk' => 'Vonný olej', 'es' => 'Aceite de fragancia'],
        'Handmade Soap'                                   => ['sk' => 'Ručne vyrábané mydlo', 'es' => 'Jabón artesanal'],
        'Incense'                                         => ['sk' => 'Kadidlo', 'es' => 'Incienso'],
        'Jewellery Display Stand'                         => ['sk' => 'Stojan na šperky', 'es' => 'Expositor de joyería'],
        'Jojoba Oil'                                      => ['sk' => 'Jojobový olej', 'es' => 'Aceite de jojoba'],
        'Konjac Sponge'                                   => ['sk' => 'Konjaková špongia', 'es' => 'Esponja konjac'],
        'Massage Oil'                                     => ['sk' => 'Masážny olej', 'es' => 'Aceite de masaje'],
        'Mitts and Scrubs'                                => ['sk' => 'Peelingové rukavice a žinky', 'es' => 'Guantes y esponjas exfoliantes'],
        'Mug'                                             => ['sk' => 'Hrnček', 'es' => 'Taza'],
        'Olive Oil'                                       => ['sk' => 'Olivový olej', 'es' => 'Aceite de oliva'],
        'Perfumed bath salts and other bath preparations' => ['sk' => 'Parfumované soli do kúpeľa a iné kúpeľové prípravky', 'es' => 'Sales de baño perfumadas y otras preparaciones para el baño'],
        'Plastic Bags'                                    => ['sk' => 'Plastové vrecká', 'es' => 'Bolsas de plástico'],
        'Plastic Caps'                                    => ['sk' => 'Plastové uzávery', 'es' => 'Tapones de plástico'],
        'Plastic Sheets For Soap'                         => ['sk' => 'Plastové fólie na mydlo', 'es' => 'Láminas de plástico para jabón'],
        'Poly Bags'                                       => ['sk' => 'Polyetylénové vrecká', 'es' => 'Bolsas de polietileno'],
        'Poly-Prop Bags'                                  => ['sk' => 'Polypropylénové vrecká', 'es' => 'Bolsas de polipropileno'],
        'Preparations for the care of skin'               => ['sk' => 'Prípravky na starostlivosť o pleť', 'es' => 'Preparaciones para el cuidado de la piel'],
        'Pumice Stones'                                   => ['sk' => 'Pemzové kamene', 'es' => 'Piedras pómez'],
        'Rapeseed Oil'                                    => ['sk' => 'Repkový olej', 'es' => 'Aceite de colza'],
        'Rayung Dry Grass'                                => ['sk' => 'Sušená tráva rayung', 'es' => 'Hierba seca rayung'],
        'Rayung Dry Grass Natural'                        => ['sk' => 'Sušená tráva rayung, prírodná', 'es' => 'Hierba seca rayung natural'],
        'Reed Diffuser Sticks/flowers'                    => ['sk' => 'Tyčinky/kvety do difuzéra', 'es' => 'Varillas/flores para difusor'],
        'Ribbon'                                          => ['sk' => 'Stuha', 'es' => 'Cinta'],
        'Safflower Oil'                                   => ['sk' => 'Saflorový olej', 'es' => 'Aceite de cártamo'],
        'Salt Lamp'                                       => ['sk' => 'Soľná lampa', 'es' => 'Lámpara de sal'],
        'Selenite Shapes'                                 => ['sk' => 'Selenitové tvary', 'es' => 'Figuras de selenita'],
        'Shopping Bag'                                    => ['sk' => 'Nákupná taška', 'es' => 'Bolsa de la compra'],
        'Sling Bags'                                      => ['sk' => 'Tašky cez rameno', 'es' => 'Bolsos bandolera'],
        'Smudge Stick'                                    => ['sk' => 'Bylinkový zväzok na vykiadzanie', 'es' => 'Atado de hierbas para sahumar'],
        'Soap Flower'                                     => ['sk' => 'Mydlové kvety', 'es' => 'Flores de jabón'],
        'Solid Shampoo'                                   => ['sk' => 'Tuhý šampón', 'es' => 'Champú sólido'],
        'Tea Blend'                                       => ['sk' => 'Čajová zmes', 'es' => 'Mezcla de té'],
        'Toiletry Bag'                                    => ['sk' => 'Toaletná taška', 'es' => 'Neceser'],
        'Unopened Geodes'                                 => ['sk' => 'Neotvorené geódy', 'es' => 'Geodas sin abrir'],
        'Vegetable Base Oil'                              => ['sk' => 'Rastlinný základový olej', 'es' => 'Aceite vegetal base'],
    ];

    public function up(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
        });

        foreach ($this->translations as $name => $translation) {
            DB::table('tariff_codes')->where('name', $name)->update(['name_i8n' => json_encode($translation)]);
        }
    }

    public function down(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->dropColumn('name_i8n');
        });
    }
};
