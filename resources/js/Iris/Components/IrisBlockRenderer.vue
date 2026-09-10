<script setup lang="ts">
import { computed, nextTick, ref, watchEffect } from "vue"
import { getIrisComponent } from "@/Iris/Composables/getIrisComponents"

const props = defineProps<{
	type: string
	shopType?: string
	code: string
	fieldValue: any
	indexBlock: number
	screenType?: string
}>()

const blockComponent = computed(() => getIrisComponent(props.type, { shop_type: props.shopType }))

// SSR renders every block with screenType 'desktop' and the live value only flips after
// mount; changing a prop on a still-loading async component swaps its SSR DOM for the
// loading placeholder (a big layout shift). Hold 'desktop' until the chunk is in.
const chunkResolved = ref(false)

watchEffect(() => {
	const component: any = blockComponent.value
	if (!component) {
		return
	}
	if (component.__asyncResolved || typeof component.__asyncLoader !== "function") {
		chunkResolved.value = true
		return
	}
	component.__asyncLoader()
		.catch(() => null)
		.then(() => nextTick())
		.then(() => {
			chunkResolved.value = true
		})
})

const effectiveScreenType = computed(() => (chunkResolved.value ? props.screenType : "desktop"))

/*
 * Block container padding/margin is per-device but SSR always renders desktop values
 * and Varnish shares one cache across devices, so the values a block inlines via
 * getStyles(container.properties, screenType) shift the layout when screenType settles
 * after first paint. Rewrite them once to per-block CSS variables (getStyles' withUnit
 * passes var() values through) and emit the per-breakpoint definitions; runs in both
 * SSR and client setup, keeping hydration identical.
 */
const resolveBoxProp = (box: any, screen: string, side?: string) => {
	const read = (obj: any) => {
		if (!obj || typeof obj !== "object") return undefined
		return side ? obj?.[side]?.value : obj?.unit
	}
	const fromScreen = read(box?.[screen])
	if (fromScreen !== undefined) return fromScreen
	if (screen !== "desktop") {
		const fromDesktop = read(box?.desktop)
		if (fromDesktop !== undefined) return fromDesktop
	}
	return read(box)
}

const containerVarsCss = (() => {
	const properties = props.fieldValue?.container?.properties
	if (!properties || typeof properties !== "object") return null

	const prefix = `--ib${props.indexBlock}`
	const rulesByScreen: Record<string, string[]> = { mobile: [], tablet: [], desktop: [] }
	let hasResponsiveValues = false

	for (const boxName of ["padding", "margin"]) {
		const box = properties[boxName]
		if (!box || typeof box !== "object") continue

		if (box.__irisCssVars) {
			for (const screen of ["mobile", "tablet", "desktop"]) {
				rulesByScreen[screen].push(...box.__irisCssVars[screen])
			}
			hasResponsiveValues = true
			continue
		}
		if (!box.mobile && !box.tablet && !box.desktop) continue

		const perScreen: Record<string, Record<string, string>> = {}
		let valid = true
		for (const screen of ["mobile", "tablet", "desktop"]) {
			perScreen[screen] = {}
			for (const side of ["top", "right", "bottom", "left"]) {
				const value = resolveBoxProp(box, screen, side)
				const unit = resolveBoxProp(box, screen)
				if (value === null || value === undefined || !unit || (typeof value === "string" && value.startsWith("var("))) {
					valid = false
					break
				}
				perScreen[screen][side] = `${value}${unit}`
			}
			if (!valid) break
		}
		if (!valid) continue

		const boxRules: Record<string, string[]> = { mobile: [], tablet: [], desktop: [] }
		for (const screen of ["mobile", "tablet", "desktop"]) {
			for (const side of ["top", "right", "bottom", "left"]) {
				boxRules[screen].push(`${prefix}-${boxName[0]}${side[0]}:${perScreen[screen][side]}`)
			}
			rulesByScreen[screen].push(...boxRules[screen])
		}

		for (const side of ["top", "right", "bottom", "left"]) {
			for (const target of [box, box.mobile, box.tablet, box.desktop]) {
				if (target?.[side]) {
					target[side].value = `var(${prefix}-${boxName[0]}${side[0]})`
				}
			}
		}
		Object.defineProperty(box, "__irisCssVars", { value: boxRules, enumerable: false })
		hasResponsiveValues = true
	}

	if (!hasResponsiveValues || !rulesByScreen.mobile.length) return null

	return `:root{${rulesByScreen.mobile.join(";")}}`
		+ `@media(min-width:640px){:root{${rulesByScreen.tablet.join(";")}}}`
		+ `@media(min-width:1024px){:root{${rulesByScreen.desktop.join(";")}}}`
})()
</script>

<template>
	<component :is="'style'" v-if="containerVarsCss">{{ containerVarsCss }}</component>
	<component
		:is="blockComponent"
		:screenType="effectiveScreenType"
		:code="code"
		:fieldValue="fieldValue"
		:indexBlock="indexBlock"
	/>
</template>
