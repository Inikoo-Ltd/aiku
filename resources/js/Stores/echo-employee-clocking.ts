/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import { router } from "@inertiajs/vue3"

export interface EchoClockEvent {
	actionType: "clock_in" | "clock_out"
	clockedAt: string | null
	id: number
}

export const useEchoEmployeeClocking = defineStore("echo-employee-clocking", {
	state: () => ({
		subscribedEmployeeId: null as number | null,
		lastClockEvent: null as EchoClockEvent | null,
	}),
	actions: {

		subscribe(employeeId: number) {
			if (this.subscribedEmployeeId === employeeId) return

			if (this.subscribedEmployeeId) {
				window.Echo.leave("grp.employee." + this.subscribedEmployeeId + ".clocking")
			}

			this.subscribedEmployeeId = employeeId

			window.Echo.private("grp.employee." + employeeId + ".clocking").listen(".clocking-updated", () => {
				router.reload({
					only: ["clock_in_out"],
					onSuccess: (page: any) => {
						const data = page?.props?.clock_in_out
						if (!data) return

						const isClockedIn = data.clocking_status === "clocked_in"
						const clocking = isClockedIn ? data.last_clock_in : data.last_clock_out
						if (!clocking) return

						this.lastClockEvent = {
							actionType: isClockedIn ? "clock_in" : "clock_out",
							clockedAt: clocking.clocked_at ?? null,
							id: clocking.id,
						}
					},
				})
			})
		},
	},
})
