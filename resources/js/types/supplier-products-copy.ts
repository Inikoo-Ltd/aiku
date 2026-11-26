/*
 * Author: Vika Aqordi
 * Created on: 2026-11-18 16:50
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

import { routeType } from "@/types/route"
import { SupplierOptionFields } from "@/types/supplier"

export interface CopyProducts {
	agent_id: number
	agents: { id: number, label: string, create_route: routeType }[]
	copy_route: routeType
	supplier_id: number
	source_products_route: routeType
	can_make_preferred: boolean
}

export interface CopyTarget extends SupplierOptionFields {
	id: number
	agent_id: number
	label: string
	number_products: number
}
