<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Families1Render.vue'
import { getStyles } from "@/Composables/styles"
import { computed, inject, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

type FamilyOrCollectionType = {
    name: string,
    description: string,
    images: { source: string }[]
}

const props = defineProps<{
    fieldValue: {
        families: FamilyOrCollectionType[]
        collections: FamilyOrCollectionType[]
    }
    webpageData?: any
    blockData?: Record<string, any>
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()


// ✅ Komputasi jumlah kolom berdasarkan user input (fallback: desktop=4, tablet=4, mobile=2)
const responsiveGridClass = computed(() => {
    const perRow = props.fieldValue?.settings?.per_row ?? {}

    const columnCount = {
        desktop: perRow.desktop ?? 4,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }

    const count = columnCount[props.screenType] ?? 1
    return `grid-cols-${count}`
})

const layout: any = inject("layout", {})

const idxSlideLoading = ref<string | null>(null)

</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id : 'families-1'" component="families-1">
        <div v-if="props.fieldValue?.families && props.fieldValue.families.length" class="px-4 py-10 mx-[30px]" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(fieldValue.container?.properties, screenType)
        }">
            <h2 class="text-2xl font-bold mb-6">{{ trans("Browse By Product Lines:") }}</h2>
            <div :class="['grid gap-8', responsiveGridClass]">

                <!-- VIEW ALL CARD -->
                <!-- <div class="flex !w-full" v-if="fieldValue?.webpage_data?.webpage_type == 'department'">
                    <LinkIris :href="fieldValue?.webpage_data?.overview_url" type="internal" class="w-full h-full flex">
                        <div class="family-item w-full h-full cursor-pointer flex flex-col rounded-xl overflow-hidden border bg-white hover:bg-gray-50 transition-all"
                            :style="{
                                ...getStyles(props.fieldValue?.chip?.container?.properties, props.screenType),
                                fontWeight: 600,
                                minHeight: maxHeight ? maxHeight + 'px' : undefined
                            }">
                            
                            <div class="flex-1 flex items-center justify-center bg-gray-100">
                                <span class="text-sm font-semibold">
                                    {{ trans("View All") }}
                                </span>
                            </div>
                        </div>
                    </LinkIris>
                </div> -->
                <template v-if="fieldValue?.show_overview_button">
                    <LinkIris :href="fieldValue?.webpage_data?.overview_url" type="internal" class="block">
                        
                        <div class="relative w-full bg-white rounded-md shadow-md overflow-hidden"
                            :style="getStyles(props.fieldValue?.chip?.container?.properties, props.screenType)"
                        >
                            <div class="aspect-[1/1] flex items-center justify-center bg-gray-50 hover:bg-gray-100">
                                <span class="text-base font-semibold text-center">
                                    {{ trans("View All") }}
                                </span>
                            </div>
                        </div>
                    </LinkIris>
                </template>


                <!-- LOOP ITEMS -->
                <LinkIris v-for="(item, index) in props?.fieldValue?.families || []" :key="index" :href="`${item.url}`"
                    type="internal" @start="() => idxSlideLoading = `family${index}`"
                    @finish="() => idxSlideLoading = null" class="relative">
                    <template #default>
                        <Family1Render :data="item" />

                        <div v-if="idxSlideLoading == `family${index}`"
                            class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl rounded">
                            <LoadingIcon />
                        </div>
                    </template>
                </LinkIris>

            </div>
        </div>
    </div>
</template>
