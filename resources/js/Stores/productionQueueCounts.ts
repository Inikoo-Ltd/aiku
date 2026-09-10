/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"

export interface ProductionQueueCounts {
	to_produce: number
	pre_pick: number
	channel: string
}

export const useProductionQueueCounts = defineStore("production-queue-counts", {
	state: () => ({
		counts: null as ProductionQueueCounts | null,
		organisation: null as string | null,
		production: null as string | null,
		channel: null as string | null,
	}),
	actions: {
		async fetch() {
			if (!this.organisation || !this.production) return

			try {
				const response = await window.fetch(
					route("grp.org.productions.show.queue_counts", [
						this.organisation,
						this.production,
					]),
					{ headers: { Accept: "application/json" } }
				)
				if (!response.ok) return

				this.counts = await response.json()
				this.subscribe(this.counts?.channel)
			} catch {
				// a missed refresh just leaves the last known numbers on screen
			}
		},

		subscribe(channel?: string) {
			if (!channel || this.channel === channel) return

			this.unwatch()
			this.channel = channel
			window.Echo.private(channel).listen(".production-queues-changed", () => this.fetch())
		},

		watch(organisation: string, production: string) {
			if (this.organisation === organisation && this.production === production) return

			this.organisation = organisation
			this.production = production
			this.counts = null
			this.fetch()
		},

		unwatch() {
			if (this.channel) {
				window.Echo.leave(this.channel)
				this.channel = null
			}
		},
	},
})
