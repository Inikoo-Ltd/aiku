/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { isEqual } from "lodash-es"
import type { DirectiveBinding, ObjectDirective } from "vue"

// floating-vue draws every v-tooltip of the page from one app, and its directive hands that app a
// new options object on each re-render of the element's component, even when the tooltip did not
// change, so any re-render redraws every tooltip on the page (INI-176: a Pick all re-rendered the
// header and redrew all the item rows' tooltips). An unchanged value now leaves the tooltip alone.
// The same object passed again may have been changed in place, so that one still goes through.
const isUnchanged = (value: unknown, oldValue: unknown) => value === oldValue
	? value === null || typeof value !== "object"
	: typeof value === "object" && typeof oldValue === "object" && isEqual(value, oldValue)

export const skipUnchangedTooltips = (tooltip: ObjectDirective) => {
	const updated = tooltip.updated

	if (!updated) {
		return
	}

	tooltip.updated = (el, binding: DirectiveBinding, vnode, prevVNode) => {
		if (!isUnchanged(binding.value, binding.oldValue)) {
			updated(el, binding, vnode, prevVNode)
		}
	}
}
