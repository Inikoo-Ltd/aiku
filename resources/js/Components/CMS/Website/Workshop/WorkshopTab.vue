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
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { getComponent } from '@/Composables/getWorkshopComponents'
import { setColorStyleRootByEl } from '@/Composables/useApp'
import { WORKSHOP_TABS, type WorkshopContext, type WorkshopTabConfig } from '@/Components/CMS/Website/Workshop/workshopTabs'

import { faInfoCircle, faDotCircle } from '@fas'
import { faChevronDoubleLeft, faChevronDoubleRight } from '@fal'

import '@/../css/Iris/editor.css'

library.add(faInfoCircle, faDotCircle, faChevronDoubleLeft, faChevronDoubleRight)

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
const isPicking = ref(Boolean(picker))

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
      preserveState: true,
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

const selectionRoute = computed(() =>
  picker?.selectionRouteKey ? props.data?.[picker.selectionRouteKey] : null
)

const fetchSelection = async (item: any) => {
  if (!picker?.selectionRouteParam || !selectionRoute.value) {
    return item
  }

  const { data } = await axios.get(
    route(selectionRoute.value.name, {
      ...selectionRoute.value.parameters,
      [picker.selectionRouteParam]: item.slug
    })
  )

  return { ...item, ...(data?.data ?? {}) }
}

const selectPreviewSource = async (item: any) => {
  if (!picker) {
    return
  }

  if (!picker.routeParam || !props.data?.route_get_list) {
    isPicking.value = true

    try {
      picked.value[picker.selectionKey] = await fetchSelection(item)
      visibleDrawer.value = false
    } catch (error) {
      picked.value[picker.selectionKey] = null

      notify({
        title: trans('Error'),
        text: trans('Failed to fetch the preview data. Please try again.'),
        type: 'error'
      })
    } finally {
      isPicking.value = false
    }

    return
  }

  isPicking.value = true

  try {
    const [selection, { data }] = await Promise.all([
      fetchSelection(item),
      axios.get(
        route(props.data.route_get_list.name, {
          ...props.data.route_get_list.parameters,
          [picker.routeParam]: item.slug
        })
      )
    ])

    picked.value[picker.selectionKey] = selection
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
    <div class="h-[85vh]">
      <div class="flex h-full flex-col overflow-hidden border-y border-gray-200 bg-white lg:flex-row">
        <aside
          v-if="config.sidebar"
          class="shrink-0 overflow-hidden border-gray-200 bg-gray-50 transition-all duration-300"
          :class="sidebarOpen
            ? 'h-[45vh] w-full border-b lg:h-full lg:w-[19rem] lg:border-b-0 lg:border-r xl:w-[21rem]'
            : 'h-0 w-full opacity-0 pointer-events-none lg:h-full lg:w-0'"
        >
          <div class="h-full w-full overflow-y-auto lg:w-[19rem] xl:w-[21rem]">
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
          </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col">
          <header
            v-if="config.sidebar || config.screenView || picker || irisLayout"
            class="flex h-10 items-center justify-between gap-2 px-2 bg-gray-50 border-b border-gray-200 shrink-0"
          >
            <div class="flex items-center gap-1.5">
              <button
                v-if="config.sidebar"
                type="button"
                v-tooltip="sidebarOpen ? trans('Hide panel') : trans('Show panel')"
                class="flex h-7 w-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition"
                @click="sidebarOpen = !sidebarOpen"
              >
                <FontAwesomeIcon :icon="sidebarOpen ? faChevronDoubleLeft : faChevronDoubleRight" class="text-xs" fixed-width aria-hidden="true" />
              </button>

              <div v-if="config.screenView" class="hidden lg:flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
                <ScreenView v-model="currentView" />
              </div>
            </div>

            <div class="flex items-center gap-3 min-w-0">
              <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
                <span v-if="isSaving" class="flex items-center gap-1.5 text-xs text-gray-500">
                  <LoadingIcon />
                  <span class="hidden sm:inline">{{ trans('Saving') }}</span>
                </span>
              </Transition>

              <button
                v-if="picker"
                type="button"
                v-tooltip="trans(picker.subtitle)"
                class="flex min-w-0 max-w-[16rem] items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-600 hover:border-gray-300 hover:text-gray-900 transition"
                @click="visibleDrawer = true"
              >
                <FontAwesomeIcon
                  :icon="faDotCircle"
                  class="text-[0.55rem]"
                  :class="previewLabel ? 'text-green-500' : 'text-gray-300'"
                  fixed-width
                  aria-hidden="true"
                />
                <span class="truncate">{{ previewLabel ?? trans('Pick a catalogue') }}</span>
              </button>

              <div v-else-if="irisLayout" class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-600">{{ trans('Login') }}</span>
                <ToggleSwitch v-model="irisLayout.iris.is_logged_in" />
              </div>
            </div>
          </header>

          <div
            class="flex-1 min-h-0 overflow-auto"
            :class="hasPreview && currentView !== 'desktop' ? 'bg-gray-100 p-4' : ''"
          >
            <component
              v-if="config.preview"
              :is="config.preview"
              v-bind="previewProps"
            />

            <div
              v-else-if="hasPreview"
              ref="rootRef"
              class="editor-class overflow-auto bg-white"
              :class="[iframeClass, currentView !== 'desktop' ? 'rounded-xl border border-gray-200 shadow-sm' : '']"
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
              class="flex h-full min-h-[240px] items-center justify-center text-gray-400"
            >
              <LoadingIcon class="text-2xl" />
            </div>

            <div
              v-else-if="picker"
              class="flex h-full min-h-[240px] flex-col items-center justify-center gap-3 px-6 text-center text-gray-500"
            >
              <FontAwesomeIcon :icon="faInfoCircle" class="text-2xl text-gray-300" aria-hidden="true" />
              <div class="space-y-1">
                <h3 class="text-sm font-semibold text-gray-700">{{ trans(picker.emptyTitle) }}</h3>
                <p class="text-xs max-w-xs text-gray-500">{{ trans(picker.emptyDescription) }}</p>
              </div>
              <Button size="xs" :label="trans(picker.emptyAction)" @click="visibleDrawer = true" />
            </div>

            <EmptyState v-else />
          </div>
        </section>
      </div>
    </div>

    <Drawer
      v-if="picker"
      v-model:visible="visibleDrawer"
      position="right"
      :pt="{ root: { style: 'width: min(24rem, 92vw)' } }"
    >
      <template #header>
        <div>
          <h2 class="text-sm font-semibold">{{ trans(picker.title) }}</h2>
          <p class="text-xs text-gray-500">{{ trans(picker.subtitle) }}</p>
        </div>
      </template>

      <ul class="space-y-1">
        <li
          v-for="item in pickerList"
          :key="item.slug"
          @click="() => selectPreviewSource(item)"
          class="flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-2 text-sm transition"
          :class="item.slug === picked[picker.selectionKey]?.slug
            ? 'border-indigo-400 bg-indigo-50 text-indigo-900 font-medium'
            : 'border-transparent text-gray-700 hover:border-gray-200 hover:bg-gray-50'"
        >
          <FontAwesomeIcon
            :icon="faDotCircle"
            class="text-[0.55rem] shrink-0"
            :class="item.slug === picked[picker.selectionKey]?.slug ? 'text-indigo-500' : 'text-gray-300'"
            fixed-width
            aria-hidden="true"
          />
          <span class="truncate">{{ item.name }}</span>
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
