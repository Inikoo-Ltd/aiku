<script setup lang="ts">
import { computed, inject, onMounted, provide, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { cloneDeep, debounce } from 'lodash-es'
import axios from 'axios'
import Drawer from 'primevue/drawer'
import ToggleSwitch from 'primevue/toggleswitch'

import Button from '@/Components/Elements/Buttons/Button.vue'
import ScreenView from '@/Components/ScreenView.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { getComponent } from '@/Composables/getWorkshopComponents'
import { setColorStyleRootByEl } from '@/Composables/useApp'
import { WORKSHOP_TABS, type WorkshopContext, type WorkshopTabConfig } from '@/Components/CMS/Website/Workshop/workshopTabs'

import { faInfoCircle, faDotCircle } from '@fas'
import { faCompressWide, faExpand } from '@far'
import { faSpinnerThird } from '@fal'

import '@/../css/Iris/editor.css'

library.add(faInfoCircle, faDotCircle, faCompressWide, faExpand, faSpinnerThird)

const props = defineProps<{
  tab: string
  data?: Record<string, any>
  currency?: Record<string, any>
  layout_theme?: Record<string, any>
}>()

const emit = defineEmits<{
  'update:layout': [layout: any]
}>()

const config: WorkshopTabConfig = WORKSHOP_TABS[props.tab] ?? { sidebar: null, layoutShape: 'none' }

const rootRef = ref<HTMLElement | null>(null)
const currentView = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
const sidebarOpen = ref(true)
const sidebarTab = ref(props.data?.layout?.data ? 1 : 0)
const selectedBlock = ref<any>(null)
const visibleDrawer = ref(false)
const isSaving = ref(false)
const themeColor4 = props.layout_theme?.color?.[4] || '#fcd34d'

provide('currentView', currentView)

const irisLayout = config.irisPreview ? reactive(cloneDeep(inject<any>('layout'))) : null

if (irisLayout) {
  irisLayout.app.theme = props.layout_theme?.color
  irisLayout.iris = { ...irisLayout.iris, is_logged_in: true }
  provide('layout', irisLayout)
}

const initialLayoutState = () => {
  const layout = props.data?.layout

  if (config.layoutShape === 'keyed') {
    return cloneDeep(Object.values(layout ?? {})[0] ?? null)
  }

  if (config.layoutShape === 'single' || config.layoutShape === 'blocks') {
    return cloneDeep(layout ?? null)
  }

  return null
}

const layoutState = ref<any>(initialLayoutState())

const picker = config.picker
const pickerList = computed(() => props.data?.[picker?.listKey ?? '']?.data ?? [])
const picked = ref<Record<string, any>>({
  [picker?.selectionKey ?? 'selection']: null,
  [picker?.resultKey ?? 'results']: []
})
const isPicking = ref(Boolean(picker?.routeParam))

const updateRoute = computed(() =>
  props.data?.update_route ??
  props.data?.update_department_route ??
  props.data?.update_family_route ??
  props.data?.update_sub_department_route ??
  props.data?.updateRoute
)

const context = computed<WorkshopContext>(() => ({
  data: props.data,
  currency: props.currency,
  layoutTheme: props.layout_theme,
  layoutState: layoutState.value,
  picked: picked.value,
  sidebarTab: sidebarTab.value,
  selectedBlock: selectedBlock.value,
  updateRoute: updateRoute.value
}))

const blocks = computed(() => {
  const state = layoutState.value

  if (!state) {
    return []
  }

  if (config.layoutShape === 'blocks') {
    return Object.entries(state).map(([code, block]: [string, any]) => ({
      code,
      fieldValue: block?.fieldValue
    }))
  }

  return state.code ? [{ code: state.code, fieldValue: state.data?.fieldValue }] : []
})

const hasPreview = computed(() => {
  if (!blocks.value.length) {
    return false
  }

  if (config.requiredFieldValue && !blocks.value.some((block) => block.fieldValue?.[config.requiredFieldValue as string])) {
    return false
  }

  return !picker || Boolean(picked.value[picker.selectionKey])
})

const iframeClass = computed(() => {
  switch (currentView.value) {
    case 'mobile':
      return 'w-[375px] h-[667px] mx-auto'
    case 'tablet':
      return 'w-[768px] h-[1024px] mx-auto'
    default:
      return 'w-full h-full'
  }
})

const previewLabel = computed(() => picked.value[picker?.selectionKey ?? '']?.name)

const stripTransient = (fieldValue: Record<string, any> | undefined) => {
  if (!fieldValue) {
    return
  }

  const transient = [
    ...(config.transient ?? []),
    picker?.selectionKey,
    picker?.resultKey
  ].filter(Boolean) as string[]

  transient.forEach((key) => delete fieldValue[key])
}

const autosavePayload = () => {
  const snapshot = cloneDeep(layoutState.value)

  if (!snapshot) {
    return null
  }

  if (config.layoutShape === 'blocks') {
    Object.values(snapshot ?? {}).forEach((block: any) => stripTransient(block?.fieldValue))

    return snapshot
  }

  stripTransient(snapshot?.data?.fieldValue)

  return config.layoutShape === 'keyed' ? { [snapshot.code]: snapshot } : snapshot
}

const autosaveRoute = computed(() => props.data?.autosaveRoute ?? props.data?.auto_save_route)

const autosave = () => {
  const payload = autosavePayload()

  if (!autosaveRoute.value || !payload) {
    return
  }

  router.patch(
    route(autosaveRoute.value.name, autosaveRoute.value.parameters),
    { layout: payload },
    {
      preserveScroll: true,
      onStart: () => { isSaving.value = true },
      onFinish: () => { isSaving.value = false },
      onSuccess: () => emit('update:layout', payload),
      onError: (errors: any) => {
        notify({
          title: trans('Autosave Failed'),
          text: errors?.message || trans('Unknown error occurred'),
          type: 'error'
        })
      }
    }
  )
}

const debouncedAutosave = debounce(autosave, 800)

const withContainerDefaults = (template: any) => cloneDeep({
  ...template,
  data: {
    ...template.data,
    fieldValue: {
      container: { properties: null },
      ...(template.data?.fieldValue || {})
    }
  }
})

const onPickTemplate = async (template: any) => {
  if (config.templateRouteName) {
    try {
      const { data } = await axios.get(
        route(config.templateRouteName, {
          website: route().params['website'],
          webBlockType: template.code
        })
      )

      if (data) {
        layoutState.value = data
        emit('update:layout', data)
        debouncedAutosave()
      }
    } catch (error) {
      notify({
        title: trans('Error'),
        text: trans('Failed to load the template'),
        type: 'error'
      })
    }

    return
  }

  layoutState.value = withContainerDefaults(template)
  autosave()
}

const selectPreviewSource = async (item: any) => {
  if (!picker) {
    return
  }

  if (!picker.routeParam || !props.data?.route_get_list) {
    picked.value[picker.selectionKey] = item
    visibleDrawer.value = false

    return
  }

  isPicking.value = true

  try {
    const { data } = await axios.get(
      route(props.data.route_get_list.name, {
        ...props.data.route_get_list.parameters,
        [picker.routeParam]: item.slug
      })
    )

    picked.value[picker.selectionKey] = item
    picked.value[picker.resultKey ?? 'results'] = data?.data ?? []
    visibleDrawer.value = false
  } catch (error) {
    picked.value[picker.selectionKey] = null
    picked.value[picker.resultKey ?? 'results'] = []

    notify({
      title: trans('Error'),
      text: trans('Failed to fetch the preview data. Please try again.'),
      type: 'error'
    })
  } finally {
    isPicking.value = false
  }
}

const pickedModel = computed(() => {
  if (!picker) {
    return {}
  }

  const model: Record<string, any> = { [picker.selectionKey]: picked.value[picker.selectionKey] }

  if (picker.resultKey) {
    model[picker.resultKey] = picked.value[picker.resultKey]
  }

  return model
})

const previewModel = (block: { code: string; fieldValue: any }) => {
  const fieldValue = { ...(block.fieldValue ?? {}) }

  if (config.previewModel) {
    return config.previewModel(fieldValue, context.value)
  }

  return { ...fieldValue, ...pickedModel.value }
}

const sidebarProps = computed(() => config.sidebarProps?.(context.value) ?? {})
const previewProps = computed(() => config.previewProps?.(context.value) ?? {})
const blockProps = computed(() => config.blockProps?.(context.value) ?? {})

onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }

  if (picker && pickerList.value.length) {
    selectPreviewSource(pickerList.value[0])
  } else {
    isPicking.value = false
  }
})
</script>

<template>
  <div>
    <component
      v-if="!config.sidebar && config.preview"
      :is="config.preview"
      v-bind="previewProps"
    />

    <div
      v-else
      class="h-[85vh] grid grid-cols-12 gap-4 p-3"
    >
      <Transition enter-active-class="transition-all duration-300" leave-active-class="transition-all duration-300">
        <aside
          v-if="sidebarOpen && config.sidebar"
          :class="config.sidebarClass ?? 'col-span-12 lg:col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border max-h-[40vh] lg:max-h-full'"
        >
          <component
            :is="config.sidebar"
            v-bind="sidebarProps"
            @set-up-template="onPickTemplate"
            @auto-save="debouncedAutosave"
            @update:data="(value: any) => layoutState = value"
            @update:selected-block="(value: any) => selectedBlock = value"
            @update:selected-tab="(value: number) => sidebarTab = value"
            @update:layout="(value: any) => emit('update:layout', value)"
          />
        </aside>
      </Transition>

      <section
        :class="config.previewClass ?? (sidebarOpen ? 'col-span-12 lg:col-span-9 bg-white rounded-xl shadow-md flex flex-col border overflow-hidden' : 'col-span-12 bg-white rounded-xl shadow-md flex flex-col border overflow-hidden')"
      >
        <header
          v-if="config.screenView || config.collapsibleSidebar || config.irisPreview"
          class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b shrink-0"
        >
          <div class="flex items-center gap-2">
            <button
              v-if="config.collapsibleSidebar"
              type="button"
              class="w-8 h-8 rounded-lg hover:bg-gray-200 transition"
              @click="sidebarOpen = !sidebarOpen"
            >
              <FontAwesomeIcon :icon="sidebarOpen ? faExpand : faCompressWide" />
            </button>

            <div v-if="config.screenView" class="py-1 px-2 hidden lg:block">
              <ScreenView v-model="currentView" />
            </div>
          </div>

          <div
            v-if="picker"
            class="text-sm text-gray-600 italic mr-3 cursor-pointer truncate"
            @click="visibleDrawer = true"
          >
            <span v-if="previewLabel">
              {{ trans('Preview') }}: <strong>{{ previewLabel }}</strong>
            </span>
            <span v-else>{{ trans('Pick Catalouge') }}</span>
          </div>

          <div v-else-if="irisLayout" class="flex items-center gap-3">
            <span class="text-sm font-medium">{{ trans('Login') }}</span>
            <ToggleSwitch v-model="irisLayout.iris.is_logged_in" />
          </div>
        </header>

        <div class="flex-1 min-h-0 overflow-auto">
          <component
            v-if="config.preview"
            :is="config.preview"
            v-bind="previewProps"
          />

          <div
            v-else-if="hasPreview"
            ref="rootRef"
            class="editor-class h-full overflow-auto"
            :class="iframeClass"
          >
            <div
              v-for="block in blocks"
              :key="block.code"
              :class="block.code === selectedBlock?.code ? 'block-active' : 'border border-transparent'"
            >
              <component
                class="flex-1 overflow-auto active-block"
                :is="getComponent(block.code, { shop_type: data?.type_shop })"
                :code="block.code"
                :screenType="currentView"
                :modelValue="previewModel(block)"
                v-bind="blockProps"
              />
            </div>
          </div>

          <div
            v-else-if="isPicking"
            class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 h-full min-h-[300px]"
          >
            <FontAwesomeIcon :icon="faSpinnerThird" class="text-3xl animate-spin" />
          </div>

          <div
            v-else-if="picker"
            class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 h-full min-h-[300px]"
          >
            <div class="flex flex-col items-center gap-2">
              <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl" />
              <h3 class="text-lg font-semibold">{{ trans(picker.emptyTitle) }}</h3>
              <p class="text-sm max-w-xs">{{ trans(picker.emptyDescription) }}</p>
            </div>
            <Button :label="trans(picker.emptyAction)" @click="visibleDrawer = true" />
          </div>

          <EmptyState v-else />
        </div>
      </section>
    </div>

    <Drawer
      v-if="picker"
      v-model:visible="visibleDrawer"
      position="right"
      :pt="{ root: { style: 'width: 30vw' } }"
    >
      <template #header>
        <div>
          <h2 class="text-base font-semibold">{{ trans(picker.title) }}</h2>
          <p class="text-xs text-gray-500">{{ trans(picker.subtitle) }}</p>
        </div>
      </template>

      <ul class="mx-auto space-y-3">
        <li
          v-for="item in pickerList"
          :key="item.slug"
          @click="() => selectPreviewSource(item)"
          class="rounded-lg shadow-sm transition-shadow"
          :class="item.slug === picked[picker.selectionKey]?.slug
            ? 'border border-blue-500 ring-2 ring-blue-300 shadow-md'
            : 'border border-gray-200 hover:shadow-md hover:border-gray-300'"
        >
          <div class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg">
            <div class="flex items-center gap-3 text-gray-800 font-medium">
              <FontAwesomeIcon
                :icon="faDotCircle"
                class="w-4 h-4"
                :class="item.slug === picked[picker.selectionKey]?.slug ? 'text-blue-500' : 'text-gray-400'"
              />
              <span class="group-hover:underline">{{ item.name }}</span>
            </div>
          </div>
        </li>
      </ul>
    </Drawer>
  </div>
</template>

<style scoped>
.block-active {
  border: 2px solid color-mix(in srgb, v-bind(themeColor4) 80%, black);
}
</style>
