<script setup lang="ts">
import { reactive, computed, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'

import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import RadioButton from 'primevue/radiobutton'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'

// FontAwesome
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt } from '@fas'
import { faHeart } from '@far'

library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt)

//
// 🔹 Interfaces
//
export interface LinkModel {
  type: 'internal' | 'external'
  href: string | null
  workshop: string | null
  id: string | null
  target: '_self' | '_blank'
  code : string | null
  canonical_url : string | null
}

export interface NavigationModel {
  label: string
  link: LinkModel
}

export interface OptionItem<T = string> {
  label: string
  value: T
}

export interface RouteConfig {
  name: string
  parameters: Record<string, string | number | null>
}

//
// 🔹 Props & Emits
//
const props = defineProps<{
  modelValue: Partial<NavigationModel>
}>()

const emit = defineEmits<{
  (e: 'onSave', value: NavigationModel): void
}>()

//
// 🔹 Reactive Local State
//
const localModel = reactive<NavigationModel>({
  label: props.modelValue.label ?? '',
  link: {
    type: props.modelValue.link?.type ?? 'internal',
    href: props.modelValue.link?.href ?? null,
    workshop: props.modelValue.link?.workshop ?? null,
    canonical_url : props.modelValue.link?.canonical_url ?? null,
	  code : props.modelValue.link?.code ?? null,
    id: props.modelValue.link?.id ?? null,
    target: props.modelValue.link?.target ?? '_self',
  },
})

const link = computed({
  get: () => localModel.link,
  set: (value: Partial<LinkModel>) => Object.assign(localModel.link, value),
})

//
// 🔹 Options
//
const linkTypes: OptionItem<'internal' | 'external'>[] = [
  { label: 'Internal', value: 'internal' },
  { label: 'External', value: 'external' },
]

const linkTargets: OptionItem<'_self' | '_blank'>[] = [
  { label: 'In this Page', value: '_self' },
  { label: 'New Page', value: '_blank' },
]

//
// 🔹 Route Helper
//
function getRoute(): RouteConfig {
  const current = route().current()
  const params = route().params as Record<string, any>

  if (current.includes('fulfilments')) {
    return {
      name: 'grp.org.fulfilments.show.web.webpages.index',
      parameters: {
        organisation: params.organisation,
        fulfilment: params.fulfilment,
        website: params.website,
      },
    }
  }

  return {
    name: 'grp.org.shops.show.web.webpages.index',
    parameters: {
      organisation: params.organisation,
      shop: params.shop,
      website: params.website,
    },
  }
}

//
// 🔹 Handlers
//
function selectQueryOnChange(e: { href: string; workshop?: string; id?: string, code?:any  }) {
  console.log(e)
  link.value = {
	type : 'internal',
	code : e?.code ?? null,
  href: e.href,
  workshop: e.workshop ?? null,
  id: e.id ?? null,
  canonical_url : e.canonical_url ?? null,
  }
}

// Optional: watch for automatic updates to parent
/* watch(localModel, (val) => emit('update:modelValue', val), { deep: true }) */

</script>

<template>
  <div>
    <!-- Target -->
    <div v-if="link.target">
      <div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans('Target') }}</div>
      <div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
        <div class="flex flex-wrap gap-4">
          <div
            v-for="(option, index) in linkTargets"
            :key="option.value"
            class="flex items-center gap-2"
          >
            <RadioButton
              :modelValue="link.target"
              @update:modelValue="val => link.target = val"
              :inputId="`${option.value}${index}`"
              name="target"
              size="small"
              :value="option.value"
            />
            <label
              :for="`${option.value}${index}`"
              class="cursor-pointer"
              @click="link.target = option.value"
            >
              {{ option.label }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Type -->
    <div v-if="link.type">
      <div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans('Type') }}</div>
      <div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
        <div class="flex flex-wrap gap-4">
          <div
            v-for="(option, index) in linkTypes"
            :key="option.value"
            class="flex items-center gap-2"
          >
            <RadioButton
              :modelValue="link.type"
              @update:modelValue="val => link.type = val"
              :inputId="`${option.value}${index}`"
              name="type"
              size="small"
              :value="option.value"
            />
            <label
              :for="`${option.value}${index}`"
              class="cursor-pointer"
              @click="link.type = option.value"
            >
              {{ option.label }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Label -->
    <div>
      <div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans('Label') }}</div>
      <PureInput v-model="localModel.label" />
    </div>

    <!-- Destination -->
    <div>
      <div class="mb-2 mt-4 text-gray-500 text-xs tracking-wide">{{ trans('Destination') }}</div>

      <PureInput v-if="link.type === 'external'" v-model="link.href" />

      <PureMultiselectInfiniteScroll
        v-else
        v-model="link"
        :fetchRoute="getRoute()"
        :placeholder="trans('Select url')"
        :object="true"
        @update:modelValue="selectQueryOnChange"
      >
        <template #option="{ option, isSelected }">
          <div>
            {{ option.code }}
            <span class="text-sm" :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">
              ({{ option.path || option.href  }})
            </span>
          </div>
        </template>

        <template #singlelabel="{ value }">
          <div v-if="value.code" class="w-full text-left pl-4">
            {{ value.code }}
            <span class="text-sm text-gray-400">({{ value.canonical_url ||  value.href}})</span>
          </div>
          <div v-else class="w-full text-left pl-4">
            <span>{{ value.canonical_url ||  value.href}}</span>
          </div>
        </template>
      </PureMultiselectInfiniteScroll>
    </div>

    <!-- Save -->
    <div class="flex justify-end mt-3">
      <Button type="save" @click="emit('onSave', localModel)" />
    </div>
  </div>
</template>
