<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { computed, inject } from "vue"
import { get, set, cloneDeep } from "lodash-es"
import { faLock } from "@fas"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getComponent } from "@/Composables/getBannerFields"

library.add(faLock, faTimes)

const emits = defineEmits<{
  (e: "update:modelValue", value: any): void
  (e: "clear", value: any): void
}>()

const props = defineProps<{
  modelValue: any
  fieldData: any
}>()

const toPlainObject = (value: any) =>
  value && typeof value === "object" && !Array.isArray(value) ? value : {}

const section = computed({
  get: () => {
    const corner = toPlainObject(props.modelValue)

    return {
      ...corner,
      data: toPlainObject(corner.data),
      temporaryData: toPlainObject(corner.temporaryData),
    }
  },
  set: (val) => {
    emits("update:modelValue", cloneDeep(val))
  },
})

const screenView = inject<any>("screenView")

const optionType = [
  {
    label: "Corner text",
    value: "cornerText",
    fields: [
      { name: ["data", "title"], type: "input", label: trans("Title"), placeholder: "Holiday Sales!" },
      { name: ["data", "subtitle"], type: "input", label: trans("Subtitle"), placeholder: "Holiday sales up to 80% all items." },
      { name: ["data", "linkOfText"], type: "input", label: trans("Link"), defaultValue: "https://", placeholder: "https://www.example.com" },
      { name: ["data", "width"], type: "number", label: trans("Width"), placeholder: "100", suffix: "%" },
      { name: ["data", "color"], type: "colorpicker", label: trans("Text color") },
      {
        name: ["data", "fontSize"],
        type: "radio",
        label: trans("Font Size"),
        defaultValue: { fontTitle: "text-[25px] lg:text-[44px]", fontSubtitle: "text-[12px] lg:text-[20px]" },
        options: [
          { label: "Extra Small", value: { fontTitle: "text-[13px] lg:text-[21px]", fontSubtitle: "text-[8px] lg:text-[12px]" } },
          { label: "Small", value: { fontTitle: "text-[18px] lg:text-[32px]", fontSubtitle: "text-[10px] lg:text-[15px]" } },
          { label: "Normal", value: { fontTitle: "text-[25px] lg:text-[44px]", fontSubtitle: "text-[12px] lg:text-[20px]" } },
          { label: "Large", value: { fontTitle: "text-[30px] lg:text-[60px]", fontSubtitle: "text-[15px] lg:text-[25px]" } },
          { label: "Extra Large", value: { fontTitle: "text-[40px] lg:text-[70px]", fontSubtitle: "text-[20px] lg:text-[30px]" } },
        ],
      },
    ],
  },
  {
    label: "Link button",
    value: "linkButton",
    fields: [
      { name: ["data", "text"], type: "input", label: trans("Title"), placeholder: "Buy Now!" },
      { name: ["data", "target"], type: "input", label: trans("Link"), defaultValue: "https://", placeholder: "https://www.example.com" },
      { name: ["data", "button_color"], type: "colorpicker", label: trans("Button color"), defaultValue: "rgb(244, 63, 94)" },
      { name: ["data", "text_color"], type: "colorpicker", label: trans("Text color"), defaultValue: "rgb(255,255,255)" },
    ],
  },
  {
    label: "Ribbon",
    value: "ribbon",
    fields: [
      { name: ["data", "text"], type: "input", label: trans("Text"), placeholder: "Holiday Sales!" },
      { name: ["data", "ribbon_color"], type: "colorpicker", label: trans("Ribbon color") },
      { name: ["data", "text_color"], type: "colorpicker", label: trans("Text color") },
    ],
  },
]

const filterType = () => {
  let FinalData = [...optionType]

  if (props.fieldData?.optionType) {
    FinalData = optionType.filter((item) =>
      props.fieldData.optionType.includes(item.value)
    )
  }

  if (section.value?.id === "topMiddle" || section.value?.id === "bottomMiddle") {
    const index = FinalData.findIndex((item) => item.value === "ribbon")
    if (index !== -1) FinalData.splice(index, 1)
  }

  return FinalData
}

const Type = computed(() => filterType())

const activeType = computed(
  () => Type.value.find((item) => item.value === section.value.type) ?? null
)

const clickTypeCorner = (value: any) => {
  const current = cloneDeep(section.value)
  const tempStore = current.temporaryData

  if (current.type) {
    tempStore[current.type] = current.data
  }

  section.value = {
    ...current,
    type: value.value,
    data: toPlainObject(cloneDeep(tempStore[value.value])),
    temporaryData: tempStore,
  }
}

const onClear = () => {
  emits("clear", section.value)
}

const isResponsive = (fieldData: any) =>
  Boolean(screenView?.value) && Array.isArray(fieldData.useIn) && fieldData.useIn.length > 0

const getValue = (fieldData: any) => {
  const rawVal = get(section.value, fieldData.name)

  if (!isResponsive(fieldData)) return rawVal ?? null

  return rawVal?.[screenView.value] ?? rawVal?.desktop ?? null
}

const setValue = (fieldData: any, value: any) => {
  const cloned = cloneDeep(section.value)

  if (isResponsive(fieldData)) {
    set(cloned, fieldData.name, {
      ...toPlainObject(get(cloned, fieldData.name)),
      [screenView.value]: value,
    })
  } else {
    set(cloned, fieldData.name, value)
  }

  section.value = cloned
}
</script>

<template>
  <div class="h-full flex flex-col">

    <!-- TYPE SELECTOR -->
    <div class="w-full">
      <div class="flex items-center gap-2 flex-wrap">

        <div class="flex bg-gray-100 p-1 rounded-lg border border-gray-200">
          <button
            v-for="item in Type"
            :key="item.value"
            type="button"
            @click="clickTypeCorner(item)"
            :class="[
              'px-4 py-2 text-sm rounded-md transition-all duration-150',
              get(activeType, 'value') === item.value
                ? 'bg-white shadow-sm text-gray-900 border border-gray-300'
                : 'text-gray-500 hover:text-gray-800 hover:bg-gray-200/70'
            ]"
          >
            {{ item.label }}
          </button>
        </div>

        <!-- CLEAR -->
        <button
          v-if="get(activeType, 'value')"
          @click="onClear"
          class="ml-1 flex items-center gap-1 text-xs text-red-500 hover:text-red-600 transition"
        >
          <FontAwesomeIcon icon="fal fa-times" />
          <span>{{ trans('Clear') }}</span>
        </button>

      </div>
    </div>

    <!-- FIELDS -->
    <div v-if="activeType" class="mt-6 space-y-5">

      <div
        v-for="field in activeType.fields"
        :key="activeType.value + '.' + field.name.join('.')"
        class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:shadow transition"
      >
        <label class="block text-sm font-medium text-gray-700 mb-2">
          {{ field.label }}
        </label>

        <div class="w-full">
          <component
            :is="getComponent(field['type'])"
            :model-value="getValue(field)"
            @update:modelValue="setValue(field, $event)"
            :fieldData="field"
            :key="field.type + field.label"
          />
        </div>
      </div>

    </div>

  </div>
</template>
