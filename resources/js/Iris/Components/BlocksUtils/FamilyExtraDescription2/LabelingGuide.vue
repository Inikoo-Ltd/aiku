<script setup lang="ts">
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileDownload, faDownload } from "@fal"
import { faCheckCircle } from "@fas"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faFileDownload, faDownload, faCheckCircle)

const richTextClass =
	"[&_p]:mb-2 [&_p:last-child]:mb-0 [&_a]:text-[#C0899B] [&_a]:underline [&_a]:underline-offset-2"

const hasText = (value: unknown) => String(value ?? "").trim() !== ""

const labelingGuide = computed(() => props.fieldValue?.labeling_guide ?? {})

const containerStyle = computed(() => getStyles(labelingGuide.value?.container?.properties))

const title = computed(() => labelingGuide.value?.title ?? "")

const description = computed(() => labelingGuide.value?.description ?? "")

const cardTitle = computed(() => labelingGuide.value?.card?.title ?? "")

const cardDescription = computed(() => labelingGuide.value?.card?.description ?? "")

const buttonLabel = computed(() => labelingGuide.value?.card?.button?.text ?? "")

const resolveRoute = inject<((name: string, params?: object) => string) | null>("route", null)

const downloadRoute = computed(() => props.fieldValue?.family?.labeling_guide?.route)

const downloadUrl = computed(() => {
	const route = downloadRoute.value

	if (!route?.name || !resolveRoute) {
		return ""
	}

	try {
		return resolveRoute(route.name, route.parameters)
	} catch {
		return ""
	}
})

const buttonUrl = computed(
	() => downloadUrl.value || String(labelingGuide.value?.card?.button?.url ?? "").trim()
)

const hasButton = computed(() => hasText(buttonLabel.value) && hasText(buttonUrl.value))

const hasCard = computed(
	() => hasText(cardTitle.value) || hasText(cardDescription.value) || hasButton.value
)

const includesTitle = computed(() => labelingGuide.value?.includes?.title ?? "")

const includes = computed(() => {
	const source = labelingGuide.value?.includes?.items

	if (!Array.isArray(source)) {
		return []
	}

	return source
		.map((item: any) => (typeof item === "string" ? { text: item } : item))
		.filter((item: any) => hasText(item?.text))
})

const note = computed(() => labelingGuide.value?.note ?? "")

const hasSide = computed(
	() => hasText(includesTitle.value) || includes.value.length > 0 || hasText(note.value)
)

console.log('aaa',props)
</script>

<template>
	<div class="w-full py-6 md:py-8 lg:py-10" :style="containerStyle">
		<h2
			v-if="hasText(title)"
			class="!m-0 !text-[24px] !font-semibold !leading-[1.25] !text-black md:!text-[28px] lg:!text-[30px] xl:!text-[32px] 2xl:!text-[36px]">
			{{ title }}
		</h2>

		<div
			v-if="hasText(description)"
			class="mt-2 max-w-[640px] text-[12px] leading-[1.8] md:text-[13px] xl:max-w-[700px] xl:text-[14px] 2xl:max-w-[780px] 2xl:text-[15px]"
			:class="richTextClass"
			v-html="description" />

		<div
			class="mt-6 grid grid-cols-1 gap-8"
			:class="
				hasCard && hasSide
					? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] lg:gap-10 2xl:gap-12'
					: ''
			">
			<div
				v-if="hasCard"
				class="rounded-md border border-[#747474] bg-[#dcdcdcdc] p-5 md:p-6 xl:p-[26px] 2xl:p-7">
				<div class="flex items-start gap-[18px]">
					<div class="relative flex-shrink-0 leading-none text-black">
						<FontAwesomeIcon
							icon="fal fa-file-download"
							class="!text-[46px] !leading-none !text-black md:!text-[52px] xl:!text-[56px] 2xl:!text-[60px]" />

						<span
							class="absolute bottom-[2px] left-[-2px] rounded-[2px] bg-black px-[3px] py-px text-[8px] font-bold leading-[1.2] tracking-[0.02em] text-white xl:text-[9px] 2xl:text-[10px]">
							PDF
						</span>
					</div>

					<div class="min-w-0">
						<h3
							v-if="hasText(cardTitle)"
							class="!m-0 !text-[17px] !font-semibold !leading-[1.35] !text-black md:!text-[18px] xl:!text-[19px] 2xl:!text-[20px]">
							{{ cardTitle }}
						</h3>

						<div
							v-if="hasText(cardDescription)"
							class="mt-2 text-[12px] leading-[1.7] md:text-[13px] xl:text-[14px] 2xl:text-[15px]"
							:class="richTextClass"
							v-html="cardDescription" />

						<a
							v-if="hasButton"
							:href="buttonUrl"
							target="_blank"
							class="inline-block !no-underline">
							<button
								class="mt-[18px] inline-flex cursor-pointer items-center gap-6 rounded-md border-0 bg-black px-[18px] py-[10px] text-[13px] leading-[1.2] text-white transition-colors duration-200 hover:bg-neutral-800 md:text-[14px] xl:px-5 xl:py-[11px] xl:text-[15px] 2xl:px-[22px] 2xl:py-3 2xl:text-[16px]"
								:style="
									getStyles(labelingGuide?.card?.button?.container?.properties)
								">
								<span>{{ buttonLabel }}</span>

								<FontAwesomeIcon
									icon="fal fa-download"
									class="!text-[14px] !text-white xl:!text-[15px] 2xl:!text-[16px]" />
							</button>
						</a>
					</div>
				</div>
			</div>

			<div v-if="hasSide" class="min-w-0">
				<h3
					v-if="hasText(includesTitle)"
					class="!m-0 !text-sm !font-semibold !leading-[1.4] !text-black">
					{{ includesTitle }}
				</h3>

				<ul
					v-if="includes.length"
					class="!mx-0 !mb-0 !mt-[14px] flex !list-none flex-col gap-[10px] !p-0">
					<li
						v-for="(item, index) in includes"
						:key="index"
						class="!m-0 flex items-start gap-[10px] text-[12px] leading-[1.6] md:text-[13px] xl:text-[14px] 2xl:text-[15px]">
						<FontAwesomeIcon
							icon="fas fa-check-circle"
							class="!mt-0.5 !flex-shrink-0 !bg-transparent !text-[12px] !leading-none !text-[#C0899B] md:!text-[13px] xl:!text-[14px] 2xl:!text-[15px]" />

						<span>{{ item.text }}</span>
					</li>
				</ul>

				<div
					v-if="hasText(note)"
					class="mt-7 rounded-md border font-semibold border-[#C0899B] bg-[#C0899B]/10 px-[14px] py-[10px] text-[12px] leading-[1.6] md:text-[13px] xl:text-[14px] 2xl:text-[15px]"
					:class="richTextClass"
					v-html="note" />
			</div>
		</div>
	</div>
</template>
