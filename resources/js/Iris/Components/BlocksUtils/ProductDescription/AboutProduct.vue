<script setup lang="ts">
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMapMarkerAlt } from "@fas"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { getStyles } from "@/Composables/styles"
import { hasRichTextContent } from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/tabVisibility"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const layout = inject("layout", {}) as any

const containerStyle = computed(() => getStyles(props.fieldValue?.about?.container?.properties))

const product = computed(() => props.fieldValue?.product ?? {})

const withoutHeadingOne = (html: unknown) =>
	String(html ?? "").replace(/<h1[^>]*>.*?<\/h1>/gis, "")

const description = computed(() => withoutHeadingOne(props.fieldValue?.tabs?.description))

const descriptionExtra = computed(() => withoutHeadingOne(product.value?.description_extra))

const hasDescription = computed(() => hasRichTextContent(description.value))

const hasDescriptionExtra = computed(() => hasRichTextContent(descriptionExtra.value))

const appointment = computed(() => props.fieldValue?.appointment_data ?? {})

const isLoggedIn = computed(() => layout?.iris?.is_logged_in ?? true)

const hasAppointment = computed(() =>
	Boolean(
		isLoggedIn.value &&
			props.fieldValue?.setting?.appointment &&
			appointment.value?.link?.href
	)
)

const image = computed(() => {
	const webImages = product.value?.web_images ?? {}
	const [firstImage] = Array.isArray(webImages.all) ? webImages.all : []

	return (
		webImages.showcase?.gallery ??
		webImages.secondary?.gallery ??
		firstImage?.gallery ??
		webImages.main?.gallery ??
		null
	)
})

const hasSideColumn = computed(() => hasDescriptionExtra.value || hasAppointment.value)

const hasExtraSection = computed(() => hasSideColumn.value || Boolean(image.value))

const richTextClass = "text-[13px] md:text-[14px] 2xl:text-[16px] leading-[1.8] text-[#334155]"
</script>

<template>
	<div class="py-5 md:py-6 lg:py-8" :style="containerStyle">
		<div v-if="hasDescription" :class="richTextClass" v-html="description" />

		<div
			v-if="hasExtraSection"
			class="mt-6 grid grid-cols-1 items-start gap-6 md:mt-10 lg:grid-cols-[38%_1fr] lg:gap-10">
			<div
				v-if="image"
				class="h-[240px] overflow-hidden rounded-[8px] md:h-[320px] lg:h-[380px]">
				<Image
					:src="image"
					:alt="product?.name"
					:image-cover="true"
					sizes="(min-width: 1024px) 38vw, 95vw"
					class="h-full w-full object-cover" />
			</div>

			<div v-if="hasSideColumn">
				<div v-if="hasDescriptionExtra" :class="richTextClass" v-html="descriptionExtra" />

				<LinkIris
					v-if="hasAppointment"
					:href="appointment?.link?.href"
					:type="appointment?.link?.type">
					<div
						class="group mt-6 flex w-fit items-center gap-3 rounded-lg border bg-[#F4F4F4] px-4 py-2 transition hover:border-gray-300 hover:bg-gray-100">
						<FontAwesomeIcon
							:icon="faMapMarkerAlt"
							class="shrink-0 text-gray-600 transition group-hover:text-gray-800" />
						<div
							class="text-sm font-medium text-gray-800 underline [&_p]:!mb-0"
							v-html="appointment?.text" />
					</div>
				</LinkIris>
			</div>
		</div>
	</div>
</template>
