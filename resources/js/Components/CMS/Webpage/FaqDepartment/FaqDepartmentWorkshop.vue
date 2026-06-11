<script setup lang="ts">
import { inject, computed  } from 'vue'
import FaqWorkshop from '@/Components/CMS/Webpage/Family2ExtraDescription/FaqWorkshop.vue';
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: object
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()


const layout: any = inject("layout", {})

const containerStyle = computed(() => ({
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
    ...getStyles(props.modelValue?.faq?.container?.properties),
    width: "auto",
}))

</script>

<template>
	 <div class="mx-auto w-full max-w-[1700px]  px-4 py-4 sm:px-8 xl:px-14 2xl:max-w-[1800px] 2xl:px-14"
        :style="containerStyle" :id="modelValue?.id ? modelValue?.id : 'faq' + indexBlock" component="faq-department">
		<FaqWorkshop :fieldValue="modelValue" :screen-type="screenType" :faqs="modelValue.faqs" />
	</div>
</template>
