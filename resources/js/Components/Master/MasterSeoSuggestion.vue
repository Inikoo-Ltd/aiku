<script setup lang="ts">
import { computed, inject, toRef } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faExternalLink, faTimes } from '@fal'
import Image from '@common/Components/Image.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useMasterShopsContent } from '@/Composables/useMasterShopsContent'
import ShopContentTabs from './ShopContentTabs.vue'

const props = withDefaults(defineProps<{
    data: {
        id?: number
    }
    isMaster?: boolean
}>(), {
    isMaster: false,
})

const layout = inject('layout', layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])

const { shops, isLoading, error, selectedShopId, selectedShop, fetchShops } = useMasterShopsContent(
    toRef(() => props.data?.id)
)

const webpage = computed(() => selectedShop.value?.webpage ?? null)

const stateColors: Record<string, string> = {
    live: 'bg-emerald-100 text-emerald-700',
    ready: 'bg-sky-100 text-sky-700',
    in_process: 'bg-amber-100 text-amber-700',
    closed: 'bg-red-100 text-red-700',
}

const stateColor = computed(() => stateColors[webpage.value?.state ?? ''] ?? 'bg-gray-100 text-gray-500')

const textFields = computed(() => [
    {
        label: trans('Code'),
        value: webpage.value?.code,
        information: trans('Use for internal use'),
    },
    {
        label: trans('Breadcrumb label'),
        value: webpage.value?.breadcrumb_label,
        information: trans('To be used for the breadcrumbs, will use Meta Title if missing'),
    },
    {
        label: trans('Meta Title'),
        value: webpage.value?.title,
        information: trans('This will be used as the title displayed in the browser, meta title for SEO, and the search feature'),
    },
    {
        label: trans('Meta Description'),
        value: webpage.value?.description,
        information: trans('This will be used for the meta description'),
    },
    {
        label: trans('Title Prefix'),
        value: webpage.value?.title_prefix,
        information: trans('Would add the set prefix to all of the webpages title'),
    },
    {
        label: trans('Title Suffix'),
        value: webpage.value?.title_suffix,
        information: trans('Would add the set suffix to all of the webpages title'),
    },
])

const toggleFields = computed(() => [
    {
        label: trans('Show Price on Webpage'),
        value: webpage.value?.show_price,
    },
    {
        label: trans('Index Page'),
        value: webpage.value?.index_page,
    },
    {
        label: trans('Follow Link'),
        value: webpage.value?.follow_link,
    },
])
</script>

<template>
    <div class="p-6">
        <ShopContentTabs
            class="mb-4"
            :shops="shops"
            :isLoading="isLoading"
            :error="error"
            :modelValue="selectedShopId"
            @update:modelValue="selectedShopId = $event"
            @retry="fetchShops"
        />

        <div v-if="selectedShop">
            <header class="mb-4 flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2">
                <span class="truncate text-sm font-semibold text-sky-700">{{ selectedShop.shop_name }}</span>
                <span
                    v-if="webpage"
                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="stateColor"
                >
                    {{ webpage.state }}
                </span>
                <a
                    v-if="webpage"
                    :href="webpage.full_url"
                    target="_blank"
                    rel="noopener"
                    class="ml-auto flex min-w-0 items-center gap-1 text-xs text-gray-400 hover:text-gray-600"
                >
                    <span class="truncate">{{ webpage.canonical_url || webpage.full_url }}</span>
                    <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
                </a>
            </header>

            <p v-if="!webpage" class="text-sm text-gray-400">
                {{ trans('This shop has no webpage') }}
            </p>

            <div v-else class="space-y-4">
                <div class="rounded-lg border-l-2 border-sky-400 bg-sky-50/60 px-3 py-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-sky-600">{{ trans('URL') }}</h3>
                    <p class="break-all text-sm text-gray-700">
                        <span class="text-gray-400">{{ webpage.url_prefix }}</span><span class="font-semibold">{{ webpage.url }}</span>
                    </p>
                </div>

                <div
                    v-for="textField in textFields"
                    :key="textField.label"
                    class="border-t border-gray-200 pt-3"
                >
                    <h3 class="text-xs font-semibold uppercase tracking-wide" :style="{ color: primaryColor }">{{ textField.label }}</h3>
                    <p class="whitespace-pre-line text-sm" :class="textField.value ? 'text-gray-600' : 'text-gray-400'">
                        {{ textField.value || '—' }}
                    </p>
                    <p class="mt-0.5 text-[11px] italic text-gray-400">{{ textField.information }}</p>
                </div>

                <div class="border-t border-gray-200 pt-3">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide" :style="{ color: primaryColor }">{{ trans('Share image') }}</h3>
                    <div v-if="webpage.seo_image" class="flex items-start gap-3">
                        <Image :src="webpage.seo_image" :alt="webpage.seo_image_alt" class="h-20 w-20 shrink-0 rounded border border-gray-200 object-cover" />
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase tracking-wide text-gray-400">{{ trans('Share image alt text') }}</div>
                            <p class="text-sm" :class="webpage.seo_image_alt ? 'text-gray-600' : 'text-gray-400'">
                                {{ webpage.seo_image_alt || '—' }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">—</p>
                </div>

                <div class="grid grid-cols-1 gap-2 border-t border-gray-200 pt-3 sm:grid-cols-3">
                    <div
                        v-for="toggleField in toggleFields"
                        :key="toggleField.label"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5"
                        :class="toggleField.value ? 'bg-emerald-50' : 'bg-gray-50'"
                    >
                        <FontAwesomeIcon
                            :icon="toggleField.value ? faCheck : faTimes"
                            :class="toggleField.value ? 'text-emerald-500' : 'text-red-400'"
                            class="text-xs"
                            fixed-width
                            aria-hidden="true"
                        />
                        <span class="text-sm" :class="toggleField.value ? 'text-emerald-800' : 'text-gray-500'">
                            {{ toggleField.label }}
                        </span>
                    </div>
                </div>

                <div v-if="webpage.structured_data" class="border-t border-gray-200 pt-3">
                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide" :style="{ color: primaryColor }">{{ trans('Structured data') }}</h3>
                    <pre class="max-h-48 overflow-auto rounded-lg border border-indigo-100 bg-indigo-50/60 p-2 text-[11px] text-indigo-900">{{ webpage.structured_data }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
