<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from "laravel-vue-i18n"
import Family1Render from "@/Iris/Components/Families1Render.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight
)

type ScreenType = "mobile" | "tablet" | "desktop"

interface ImageSource {
  source?: string
}

interface FamilyOrCollectionItem {
  url?: string
  name?: string
  description?: string
  images?: ImageSource[]
}

interface ResponsiveProperty<T> {
  desktop?: T
  tablet?: T
  mobile?: T
}

interface StyleProperty {
  properties?: Record<string, unknown>
}

interface ButtonConfig {
  view_more?: StyleProperty
}

interface ContainerConfig extends StyleProperty {}

interface FieldValue {
  id?: string
  families?: FamilyOrCollectionItem[]
  collections?: FamilyOrCollectionItem[]
  container?: StyleProperty
  button?: ButtonConfig
  settings?: {
    per_row?: Partial<Record<ScreenType, number>>
  }
  show_overview_button?: boolean
  webpage_data?: {
    overview_url?: string
  }
}

interface LayoutContext {
  app?: {
    webpage_layout?: {
      container?: StyleProperty
    }
  }
}

const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: Record<string, unknown>
  blockData?: Record<string, unknown>
  screenType: ScreenType
  indexBlock: number
}>()

const layout = inject<LayoutContext>("layout", {})

const idxSlideLoading = ref<string | null>(null)

const fallbackPerRow: Record<ScreenType, number> = {
  desktop: 4,
  tablet: 4,
  mobile: 2,
}

const responsiveGridClass = computed<string>(() => {
  const perRow = props.fieldValue.settings?.per_row ?? {}

  const columnCount: Record<ScreenType, number> = {
    desktop: perRow.desktop ?? fallbackPerRow.desktop,
    tablet: perRow.tablet ?? fallbackPerRow.tablet,
    mobile: perRow.mobile ?? fallbackPerRow.mobile,
  }

  const count = columnCount[props.screenType]
  return `grid-cols-${count}`
})

const containerStyles = computed(() => ({
  ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
  ...getStyles(props.fieldValue.container?.properties, props.screenType),
}))

const buttonStyles = computed(() =>
  getStyles(props.fieldValue.button?.view_more?.properties, props.screenType)
)

const hasFamilies = computed(() => (props.fieldValue.families?.length ?? 0) > 0)

const showOverviewButton = computed(
  () => props.fieldValue.show_overview_button === true
)

const overviewUrl = computed(() => props.fieldValue.webpage_data?.overview_url ?? "")

const families = computed<FamilyOrCollectionItem[]>(
  () => props.fieldValue.families ?? []
)
</script>

<template>
    <div :id="fieldValue.id ?? `families-1${indexBlock}`" component="families-1">
        <div v-if="hasFamilies" class="px-4 py-10 mx-[30px]" :style="containerStyles">
            <h2 class="text-2xl font-bold mb-6">{{ trans("Browse By Product Lines:") }}</h2>
            <div :class="['grid gap-8', responsiveGridClass]">

                <template v-if="showOverviewButton">
                    <LinkIris :href="overviewUrl" type="internal" class="block">
                        <div class="relative w-full bg-white rounded-md shadow-md overflow-hidden">
                            <div :style="buttonStyles" class="aspect-[1/1] flex items-center justify-center bg-gray-50 hover:bg-gray-100">
                                <span class="text-base font-semibold text-center">
                                    {{ trans("View All") }}
                                </span>
                            </div>
                        </div>
                    </LinkIris>
                </template>

                <!-- LOOP ITEMS -->
                <LinkIris
                    v-for="(item, index) in families"
                    :key="`family-${index}`"
                    :href="item.url"
                    type="internal"
                    @start="() => idxSlideLoading = `family${index}`"
                    @finish="() => idxSlideLoading = null"
                    class="relative"
                >
                    <template #default>
                        <Family1Render :data="item" />

                        <div
                            v-if="idxSlideLoading === `family${index}`"
                            class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl rounded"
                        >
                            <LoadingIcon />
                        </div>
                    </template>
                </LinkIris>

            </div>
        </div>
    </div>
</template>
