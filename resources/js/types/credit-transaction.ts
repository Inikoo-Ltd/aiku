/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 12:11:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

export interface CreditTransaction {
  id: number
  payment_id: string
  type: string // Human-readable label from CreditTransactionTypeEnum
  amount: string // Decimal serialized as a string (e.g., "123.45")
  running_amount: string | null // Maybe null
  payment_reference: string | null
  payment_type: string | null
  currency_code: string
  created_at: string // ISO date-time strings
}
