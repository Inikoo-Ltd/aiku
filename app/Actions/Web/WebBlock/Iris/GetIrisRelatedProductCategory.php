<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisRelatedProductCategory
{
    use AsObject;


    public function handle(Webpage $webpage, array $webBlock): array
    {

        // need like this in the fieldvalue
        //         {
        //     "id": "related-product-category",
        //     "title": "<p>haloooo</p>",
        //     "settings": {
        //         "product_category": [
        //             {
        //                 "id": 40009,
        //                 "key": "department-40009",
        //                 "code": "music",
        //                 "name": "Music",
        //                 "slug": "music-aeu",
        //                 "sold": 0,
        //                 "type": "department",
        //                 "image": {
        //                     "png": "https://media.aiku.io/20bOlzbdmCU1WgBmgGWI8kseMSd_-z21viZsU1J27Mw/rs::0:600::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.png",
        //                     "avif": "https://media.aiku.io/kVy0F1ymex9iowM8zLxSRKX51RYWHkFaOYE7wsrvjIU/rs::0:600::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.avif",
        //                     "webp": "https://media.aiku.io/B3aNw8gYsewvcRswuT40syB5TG-ujulqcXlWDYJHKEI/rs::0:600::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.webp",
        //                     "png_2x": "https://media.aiku.io/_qMF5Te9TGBoQUSOVcQWtchhtjRnc2xxlS-_y_G5IRU/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.png",
        //                     "avif_2x": "https://media.aiku.io/fpU5nzOQgUu55n9U1ljCwrHaNuhAqu_jPhfo51GKXE0/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.avif",
        //                     "webp_2x": "https://media.aiku.io/RBropf_xQNvveDnblwDVHRyWzJoY9IKg_9JsClP4FSE/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.webp",
        //                     "original": "https://media.aiku.io/cS5KyA_bu6AE2vD0LKZru6IHmNE1pm0SGgiVbKQ3z_s/rs::0:600::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw",
        //                     "original_2x": "https://media.aiku.io/UUqObuYeDRrMnLR0zcn4TdRtGmpX4UJ_qt6AAvq9DHA/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw"
        //                 },
        //                 "state": {
        //                     "icon": "fal fa-check",
        //                     "class": "text-emerald-500",
        //                     "label": "Aktif"
        //                 },
        //                 "invoices": 0,
        //                 "listings": 0,
        //                 "shop_code": "AEU",
        //                 "shop_name": "AW Artisan Europe",
        //                 "shop_slug": "aeu",
        //                 "created_at": "2026-01-08T14:53:22.000000Z",
        //                 "public_url": null,
        //                 "updated_at": "2026-05-12T01:03:30.000000Z",
        //                 "description": "<p><span style=\"font-family: Inter, ui-sans-serif, system-ui, sans-serif, &quot;Apple Color Emoji&quot;, &quot;Segoe UI Emoji&quot;, &quot;Segoe UI Symbol&quot;, &quot;Noto Color Emoji&quot;; font-size: 14px; color: rgb(55, 65, 81);\">This Music &amp; Sound Therapy Department by AW Artisan Europe brings together sound-based products designed for relaxation, meditation, décor and wellbeing-focused lifestyles. This department combines visual appeal with sensory experience, helping customers create calming and atmospheric spaces.</span></p>",
        //                 "health_rank": {
        //                     "text": "C",
        //                     "class": "text-yellow-500",
        //                     "color": "yellow",
        //                     "tooltip": "Average | Covers 50–100% of revenue in last 90 days"
        //                 },
        //                 "invoices_ly": 0,
        //                 "dropshippers": 0,
        //                 "currency_code": "EUR",
        //                 "webpage_state": null,
        //                 "invoices_delta": null,
        //                 "image_thumbnail": {
        //                     "png": "https://media.aiku.io/hCQEBQJgVqGbR31VvIoWanyOZVLByffm_lJY4d55YYo/rs::0:48::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.png",
        //                     "avif": "https://media.aiku.io/1JSaMi9y-0YTBTq21htlkOkjx0sy5r_wf-89z138FD4/rs::0:48::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.avif",
        //                     "webp": "https://media.aiku.io/fEt5bltoyW_ut3K5oO7rnPyivMpoJzRkumHDT08wbL8/rs::0:48::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.webp",
        //                     "png_2x": "https://media.aiku.io/DzaI21YEzPiTAz-mKIL4bc9_0-hB4xx48hgX3UF49D8/rs::0:96::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.png",
        //                     "avif_2x": "https://media.aiku.io/P_PHO3Wq4T8xEgwr__tw47xh97skyIEBnHJ0-nazHP4/rs::0:96::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.avif",
        //                     "webp_2x": "https://media.aiku.io/24oMWy0dfYfbaXWJhO0qLUrCdodYyzgFaISbkslNno0/rs::0:96::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw.webp",
        //                     "original": "https://media.aiku.io/ctdUCMjbKYU57_PkaAZ88uP5y2Py6-vOYfRP0fIymaE/rs::0:48::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw",
        //                     "original_2x": "https://media.aiku.io/lJya7HyGLXACroneZVif_WSZQrENIPdNpRVInhsj9Zk/rs::0:96::/bG9jYWw6Ly9tZWRpYS9ISC9FQy82MFIzMEMxSjY4UktFQ0hIL2JkN2UzNmRhLmpwZw"
        //                 },
        //                 "is_name_reviewed": null,
        //                 "organisation_code": null,
        //                 "organisation_name": "AW Artisan S.L.",
        //                 "organisation_slug": "es",
        //                 "is_description_reviewed": null,
        //                 "number_current_families": 5,
        //                 "number_current_products": 73,
        //                 "master_product_category_id": 1515,
        //                 "number_current_collections": 1,
        //                 "sales_grp_currency_external": 0,
        //                 "is_description_extra_reviewed": null,
        //                 "is_description_title_reviewed": null,
        //                 "number_current_sub_departments": 3,
        //                 "sales_grp_currency_external_ly": 0,
        //                 "sales_grp_currency_external_delta": null
        //             },
        //             {
        //                 "id": 40006,
        //                 "key": "department-40006",
        //                 "code": "retail-display-stands",
        //                 "name": "Retail Display Stands",
        //                 "slug": "retail-display-stands-aeu",
        //                 "sold": 0,
        //                 "type": "department",
        //                 "image": null,
        //                 "state": {
        //                     "icon": "fal fa-check",
        //                     "class": "text-emerald-500",
        //                     "label": "Aktif"
        //                 },
        //                 "invoices": 0,
        //                 "listings": 0,
        //                 "shop_code": "AEU",
        //                 "shop_name": "AW Artisan Europe",
        //                 "shop_slug": "aeu",
        //                 "created_at": "2026-01-08T14:53:20.000000Z",
        //                 "public_url": null,
        //                 "updated_at": "2026-05-12T01:03:31.000000Z",
        //                 "description": "<p><strong>Welcome to the New Wholesale Retail Displays &amp; Stands Department.&nbsp;</strong><span style=\"font-family: Raleway, sans-serif; font-size: 15px; color: rgb(114, 114, 114);\">Our extensive wholesale ranges include Wooden Earrings Displays, Display Hands, Jewellery Boxes, Wooden Necklace Stands and very popular Aromatherapy and Bathroom Retail Stands.</span></p>",
        //                 "health_rank": {
        //                     "text": "C",
        //                     "class": "text-yellow-500",
        //                     "color": "yellow",
        //                     "tooltip": "Average | Covers 50–100% of revenue in last 90 days"
        //                 },
        //                 "invoices_ly": 0,
        //                 "dropshippers": 0,
        //                 "currency_code": "EUR",
        //                 "webpage_state": null,
        //                 "invoices_delta": null,
        //                 "image_thumbnail": null,
        //                 "is_name_reviewed": null,
        //                 "organisation_code": null,
        //                 "organisation_name": "AW Artisan S.L.",
        //                 "organisation_slug": "es",
        //                 "is_description_reviewed": null,
        //                 "number_current_families": 10,
        //                 "number_current_products": 191,
        //                 "master_product_category_id": 9,
        //                 "number_current_collections": 2,
        //                 "sales_grp_currency_external": 0,
        //                 "is_description_extra_reviewed": null,
        //                 "is_description_title_reviewed": null,
        //                 "number_current_sub_departments": 2,
        //                 "sales_grp_currency_external_ly": 0,
        //                 "sales_grp_currency_external_delta": null
        //             },
        //             {
        //                 "id": 40003,
        //                 "key": "department-40003",
        //                 "code": "clothing",
        //                 "name": "Clothing",
        //                 "slug": "clothing-aeu",
        //                 "sold": 0,
        //                 "type": "department",
        //                 "image": null,
        //                 "state": {
        //                     "icon": "fal fa-check",
        //                     "class": "text-emerald-500",
        //                     "label": "Aktif"
        //                 },
        //                 "invoices": 0,
        //                 "listings": 0,
        //                 "shop_code": "AEU",
        //                 "shop_name": "AW Artisan Europe",
        //                 "shop_slug": "aeu",
        //                 "created_at": "2026-01-08T14:53:17.000000Z",
        //                 "public_url": null,
        //                 "updated_at": "2026-05-12T01:02:33.000000Z",
        //                 "description": "<p>Our Clothing Department offers a straightforward wholesale range of wearable fashion designed to suit independent retailers and gift shops. The collection features practical, everyday apparel with broad customer appeal, including <strong>Socks &amp; Legwear, Hats &amp; Headwear, Pants &amp; Trousers, Scarves, Sarongs &amp; Wraps, and Tops &amp; T-Shirts</strong>. Each category provides reliable stock lines that complement your shop’s offer and support consistent sales throughout the year</p>",
        //                 "health_rank": {
        //                     "text": "C",
        //                     "class": "text-yellow-500",
        //                     "color": "yellow",
        //                     "tooltip": "Average | Covers 50–100% of revenue in last 90 days"
        //                 },
        //                 "invoices_ly": 0,
        //                 "dropshippers": 0,
        //                 "currency_code": "EUR",
        //                 "webpage_state": null,
        //                 "invoices_delta": null,
        //                 "image_thumbnail": null,
        //                 "is_name_reviewed": null,
        //                 "organisation_code": null,
        //                 "organisation_name": "AW Artisan S.L.",
        //                 "organisation_slug": "es",
        //                 "is_description_reviewed": null,
        //                 "number_current_families": 16,
        //                 "number_current_products": 229,
        //                 "master_product_category_id": 1513,
        //                 "number_current_collections": 1,
        //                 "sales_grp_currency_external": 0,
        //                 "is_description_extra_reviewed": null,
        //                 "is_description_title_reviewed": null,
        //                 "number_current_sub_departments": 5,
        //                 "sales_grp_currency_external_ly": 0,
        //                 "sales_grp_currency_external_delta": null
        //             },
        //             {
        //                 "id": 443,
        //                 "key": "department-443",
        //                 "code": "candles",
        //                 "name": "Candles",
        //                 "slug": "candles-aeu",
        //                 "sold": 0,
        //                 "type": "department",
        //                 "image": {
        //                     "png": "https://media.aiku.io/XiNzrE0yzgL2OaBFastVt7iXGL9WbGnN7ihcrwzAnhg/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.png",
        //                     "avif": "https://media.aiku.io/ztKTa9JX5XORVVFb07UVFkh9DSUON9e-rEQpf85yBTM/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.avif",
        //                     "webp": "https://media.aiku.io/qB7g674TVm_9KE92KTy0QNSAPsS5sUY-1or8dTy6Dss/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.webp",
        //                     "png_2x": "https://media.aiku.io/cIg7JIr8L3sbliKWf22Nogy3Mo9wPcZ1ePbmoy1KyKk/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.png",
        //                     "avif_2x": "https://media.aiku.io/C_We2OsJZIJcCuKHDBuC8LCJmstOs3wF9C307sGgxvY/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.avif",
        //                     "webp_2x": "https://media.aiku.io/oknlCavfaBHsQahtRL4zStOC35iTC0ZfxAQDNFmpBc8/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.webp",
        //                     "original": "https://media.aiku.io/MEjnuIug7_hPnXLLKzT-OHA0moQ0XF49d3QI7f8yR8A/rs::0:600::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw",
        //                     "original_2x": "https://media.aiku.io/MWPlv_VvJ2XDIwzr0Ssp22OOLSqlCvSOAEFGdWymBdg/rs::0:1200::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw"
        //                 },
        //                 "state": {
        //                     "icon": "fal fa-check",
        //                     "class": "text-emerald-500",
        //                     "label": "Aktif"
        //                 },
        //                 "invoices": 0,
        //                 "listings": 0,
        //                 "shop_code": "AEU",
        //                 "shop_name": "AW Artisan Europe",
        //                 "shop_slug": "aeu",
        //                 "created_at": "2020-10-26T07:04:10.000000Z",
        //                 "public_url": null,
        //                 "updated_at": "2026-05-12T01:02:29.000000Z",
        //                 "description": "<p><span style=\"font-family: Raleway, sans-serif; font-size: 1rem;\"><strong>Welcome to the Europe´s most popular wholesale candles and candle holder supplier.&nbsp;</strong></span><span style=\"font-family: Raleway, sans-serif; font-size: 1rem; color: rgb(114, 114, 114);\">Over many years we have been working hard to find you the fastest selling and high quality candles, candle holders and all of course at competitive prices.</span></p><p><span style=\"font-family: Raleway, sans-serif; font-size: 1rem;\"><strong>Our fragrances are carefully selected to provide the perfect ambience for any occasion, whether it be a romantic dinner or a relaxing evening at home.&nbsp;</strong></span></p>",
        //                 "health_rank": {
        //                     "text": "C",
        //                     "class": "text-yellow-500",
        //                     "color": "yellow",
        //                     "tooltip": "Average | Covers 50–100% of revenue in last 90 days"
        //                 },
        //                 "invoices_ly": 0,
        //                 "dropshippers": 0,
        //                 "currency_code": "EUR",
        //                 "webpage_state": null,
        //                 "invoices_delta": null,
        //                 "image_thumbnail": {
        //                     "png": "https://media.aiku.io/i0pYyvSxBfSJucjU8xM1PVdccXw55-eDAFL03QE7Vdc/rs::0:48::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.png",
        //                     "avif": "https://media.aiku.io/6rGGjPNZKkpjya94H-chgNh9tJXDOHfuY1xcn7PS9sc/rs::0:48::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.avif",
        //                     "webp": "https://media.aiku.io/q56feABa2iFyVLnXrqXoGtbe1sQmtIVgjPePYADsz2E/rs::0:48::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.webp",
        //                     "png_2x": "https://media.aiku.io/XjJHs5ksYzBX0wluC_O137h3I-X8ZvuCrztVCH0fiuk/rs::0:96::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.png",
        //                     "avif_2x": "https://media.aiku.io/F-zV1VNHFv9YJkJXaT1DJchRo6Sxz4K7gDo6pNToH9M/rs::0:96::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.avif",
        //                     "webp_2x": "https://media.aiku.io/Fb5roB2h91K-mFkfJzg82XQFKnYG68k71cZhUc72W-c/rs::0:96::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw.webp",
        //                     "original": "https://media.aiku.io/xV85Yz2NWPJAH4eQvJA3ruWvY2dglvc6zpshzPqBVeI/rs::0:48::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw",
        //                     "original_2x": "https://media.aiku.io/RLFqrGBSWjEJfNlBrZQp8jwiej4AkmdVdKiwuw_AOok/rs::0:96::/bG9jYWw6Ly9tZWRpYS9TUC84RC82MFIzMEMxSjY4Uks4RFNQLzY4ZGIxMWEzLmpwZw"
        //                 },
        //                 "is_name_reviewed": null,
        //                 "organisation_code": null,
        //                 "organisation_name": "AW Artisan S.L.",
        //                 "organisation_slug": "es",
        //                 "is_description_reviewed": null,
        //                 "number_current_families": 40,
        //                 "number_current_products": 358,
        //                 "master_product_category_id": 3,
        //                 "number_current_collections": 7,
        //                 "sales_grp_currency_external": 0,
        //                 "is_description_extra_reviewed": null,
        //                 "is_description_title_reviewed": null,
        //                 "number_current_sub_departments": 3,
        //                 "sales_grp_currency_external_ly": 0,
        //                 "sales_grp_currency_external_delta": null
        //             }
        //         ]
        //     }
        // }
    }
}
