<script setup lang="ts">
import { computed, ref, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGalaxy, faTimesCircle } from "@fas"
import { get, isPlainObject } from "lodash-es"
import { getStyles } from "@/Composables/styles"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import {
  faBaby,
  faCactus,
  faObjectGroup,
  faUser,
  faHouse,
  faTruck,
  faTag,
  faPhone,
  faInfoCircle,
} from "@fal"
import {
  faBackpack,
  faTruckLoading,
  faTruckMoving,
  faTruckContainer,
  faUser as faUserRegular,
  faWarehouse,
  faWarehouseAlt,
  faShippingFast,
  faInventory,
  faDollyFlatbedAlt,
  faBoxes,
  faShoppingCart,
  faBadgePercent,
  faChevronRight,
  faCaretRight,
  faPhoneAlt,
  faGlobe,
  faPercent,
  faPoundSign,
  faClock,
} from "@far"
import { faLambda } from "@fad"

library.add(
  faTimesCircle,
  faUser,
  faCactus,
  faBaby,
  faObjectGroup,
  faGalaxy,
  faLambda,
  faBackpack,
  faHouse,
  faTruck,
  faTag,
  faPhone,
  faInfoCircle,
  faTruckLoading,
  faTruckMoving,
  faTruckContainer,
  faUserRegular,
  faWarehouse,
  faWarehouseAlt,
  faShippingFast,
  faInventory,
  faDollyFlatbedAlt,
  faBoxes,
  faShoppingCart,
  faBadgePercent,
  faChevronRight,
  faCaretRight,
  faPhoneAlt,
  faGlobe,
  faPercent,
  faPoundSign,
  faClock
)

interface ResponsiveText {
  desktop?: string
  tablet?: string
  mobile?: string
  use_responsive?: boolean
}

interface ImageGallery {
  gallery?: string
}

interface WebImages {
  main?: ImageGallery
}

interface SubDepartmentItem {
  code?: string
  url?: string
  name?: string
  icon?: unknown
  web_images?: WebImages
  image?: string
}

interface FieldValue {
  id?: string
  collections?: SubDepartmentItem[]
  sub_departments?: SubDepartmentItem[]
  container?: {
    properties?: Record<string, unknown>
  }
  card?: {
    container?: {
      properties?: Record<string, unknown>
    }
  }
  settings?: {
    per_row?: Partial<Record<"desktop" | "tablet" | "mobile", number>>
  }
  text?: {
    value?: string | ResponsiveText
    visible?: boolean | null
  }
}

interface LayoutContext {
  rightbasket?: {
    show?: boolean
  }
}

const layout = inject<LayoutContext>("layout", {})

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: Record<string, unknown>
  blockData?: Record<string, unknown>
  screenType: ScreenType
  indexBlock?: number | string
}>()

const fallbackPerRow: Record<ScreenType, number> = {
  desktop: 4,
  tablet: 3,
  mobile: 2,
}

const hasRightBasket = computed(() => layout.rightbasket?.show === true)

const perRow = computed<number>(() => {
  const base =
    props.fieldValue.settings?.per_row?.[props.screenType] ??
    fallbackPerRow[props.screenType]

  if (hasRightBasket.value && props.screenType !== "mobile") {
    return 3
  }

  return base
})

const gridColsClass = computed<string>(() => `grid-cols-${perRow.value}`)

const screenClass = computed<string>(() => {
  switch (props.screenType) {
    case "mobile":
      return "px-4 py-6 text-sm"
    case "tablet":
      return "px-6 py-8 text-base"
    case "desktop":
    default:
      return "px-12 py-12 text-base"
  }
})

const mergedItems = computed<SubDepartmentItem[]>(() => {
  const subs = props.fieldValue.sub_departments ?? []
  const collections = props.fieldValue.collections ?? []

  return [...subs, ...collections]
})

const idxSlideLoading = ref<number | null>(null)

const containerStyle = computed(() =>
  getStyles(props.fieldValue.container?.properties, props.screenType) ?? undefined
)

const cardStyle = computed(() =>
  getStyles(props.fieldValue.card?.container?.properties, props.screenType) ?? undefined
)

const title = computed<string>(() => {
  const rawVal = get(
    props.fieldValue,
    ["text", "value"]
  ) as string | ResponsiveText | undefined

  if (typeof rawVal === "string") {
    return rawVal
  }

  if (rawVal && isPlainObject(rawVal)) {
    if (!rawVal.use_responsive) {
      return rawVal.desktop ?? ""
    }

    return rawVal[props.screenType] ?? rawVal.desktop ?? ""
  }

  return ""
})

const textVisible = computed<boolean>(() => {
  const visible = props.fieldValue.text?.visible
  return visible === null || visible === undefined ? true : visible
})
</script>

<template>
  <div v-if="mergedItems.length" class="mx-auto" :class="screenClass"
    :id="fieldValue?.id ? fieldValue?.id : 'sub-department-1' + indexBlock" component="sub-department-1"
    :style="containerStyle">
    <div v-if="textVisible" v-html="title"></div>
    <div>
      <div class="grid gap-4" :class="gridColsClass">
        <LinkIris v-for="(item, index) in mergedItems" :key="item?.code || index" :href="`${item?.url}`"
          class="relative flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full"
          :aria-label="`Go to ${item?.name}`" type="internal" @start="() => idxSlideLoading = index"
          @finish="() => idxSlideLoading = null"
          :style="cardStyle">
          <template #default>
            <div v-if="item?.icon || item?.web_images?.main?.gallery || item?.image"
              class="flex items-center justify-center min-w-5 min-h-5 w-5 h-5 shrink-0">
              <FontAwesomeIcon v-if="item?.icon" :icon="item?.icon" class="text-xl w-5 h-5" />

              <Image
                v-else
                :src="(item?.web_images?.main?.gallery || item?.image) as any"
                class="max-w-full max-h-full object-contain"
                :alt="item?.name"
              />
            </div>

            <span class="flex-1 text-center">{{ item?.name }}</span>

            <div v-if="idxSlideLoading == index"
              class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-2xl">
              <LoadingIcon />
            </div>
          </template>
        </LinkIris>
      </div>
    </div>
  </div>


<div v-else></div>
</template>
