<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 May 2025 23:50:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Controllers\Api\Models;

/**
 * @OA\Schema(
 *     schema="Error",
 *     title="Error Response",
 *     description="Standard error response",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Error message"
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Validation errors"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     title="Order",
 *     description="Order model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Order ID"
 *     ),
 *     @OA\Property(
 *         property="number",
 *         type="string",
 *         description="Order number"
 *     ),
 *     @OA\Property(
 *         property="state",
 *         type="string",
 *         description="Order state"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation date"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update date"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Transaction",
 *     title="Transaction",
 *     description="Transaction model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Transaction ID"
 *     ),
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         format="int64",
 *         description="Order ID"
 *     ),
 *     @OA\Property(
 *         property="portfolio_id",
 *         type="integer",
 *         format="int64",
 *         description="Portfolio ID"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Transaction amount"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation date"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update date"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Portfolio",
 *     title="Portfolio",
 *     description="Portfolio model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Portfolio ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Portfolio name"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation date"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update date"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Client",
 *     title="Client",
 *     description="Client model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Client ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Client name"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="Client email"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation date"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Last update date"
 *     )
 * )
 */
class ApiModels
{
    // This class is used only for Swagger annotations
}
