/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"

export interface CascadeProgress {
	state: string
	done: number
	total: number
}

/*
 * A master product category broadcasts one cascade progress event per jsonb field it copies
 * down to the shops that follow it. They all travel on the same channel, so the edit page
 * subscribes once here instead of once per field component.
 */
const cascadeEvents: Record<string, string> = {
	".faq-cascade-progress": "faq",
	".customize-option-cascade-progress": "customize_option",
	".storage-option-cascade-progress": "storage_option",
}

const channelName = (masterProductCategoryId: number) =>
	"grp.master-product-category." + masterProductCategoryId

export const useEchoMasterProductCategory = defineStore("echo-master-product-category", {
	state: () => ({
		subscribedId: null as number | null,
		subscriberCount: 0,
		cascadeProgress: {} as Record<string, CascadeProgress>,
	}),
	actions: {

		subscribe(masterProductCategoryId?: number | null) {
			if (!window.Echo || !masterProductCategoryId) return

			if (this.subscribedId !== masterProductCategoryId) {
				this.leave()
				this.subscribedId = masterProductCategoryId

				const channel = window.Echo.private(channelName(masterProductCategoryId))

				Object.entries(cascadeEvents).forEach(([event, field]) => {
					channel.listen(event, (progress: CascadeProgress) => {
						this.cascadeProgress[field] = progress
					})
				})
			}

			this.subscriberCount++
		},

		// Fields unmount one by one, the channel closes only once the last of them is gone
		unsubscribe(masterProductCategoryId?: number | null) {
			if (!masterProductCategoryId || this.subscribedId !== masterProductCategoryId) return

			this.subscriberCount--

			if (this.subscriberCount > 0) return

			this.leave()
		},

		leave() {
			if (this.subscribedId && window.Echo) {
				window.Echo.leave(channelName(this.subscribedId))
			}

			this.subscribedId = null
			this.subscriberCount = 0
			this.cascadeProgress = {}
		},
	},
})
