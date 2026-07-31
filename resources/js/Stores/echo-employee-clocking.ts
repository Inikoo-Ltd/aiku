/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import { router } from "@inertiajs/vue3"

export const useEchoEmployeeClocking = defineStore("echo-employee-clocking", {
	state: () => ({
		subscribedEmployeeId: null as number | null,
	}),
	actions: {

		subscribe(employeeId: number) {
			if (this.subscribedEmployeeId === employeeId) return

			if (this.subscribedEmployeeId) {
				window.Echo.leave("grp.employee." + this.subscribedEmployeeId + ".clocking")
			}

			this.subscribedEmployeeId = employeeId

			window.Echo.private("grp.employee." + employeeId + ".clocking").listen(".clocking-updated", () => {
				router.reload({ only: ["clock_in_out"] })
			})
		},
	},
})
