<script setup lang="ts">
import { routeType } from '@/types/route'
import { inject, ref, watch, computed } from 'vue'
import EditTradeUnit from './EditTradeUnit.vue'
import { TradeUnit } from '@/types/trade-unit'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import PureInput from '../Pure/PureInput.vue'
import PureTextarea from '../Pure/PureTextarea.vue'
import Button from '../Elements/Buttons/Button.vue'
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    data: {
        tradeUnit: TradeUnit
        brand: {}
        brand_routes: {
            index_brand: routeType
            store_brand: routeType
            update_brand: routeType
            delete_brand: routeType
            attach_brand: routeType
            detach_brand: routeType
        }
        tag_routes: {
            index_tag: routeType
            store_tag: routeType
            update_tag: routeType
            delete_tag: routeType
            attach_tag: routeType
            detach_tag: routeType
        }
        tags: {}[]
        tags_selected_id: number[]
    }
}>()

// Inject locale structure
const locale = inject('locale', aikuLocaleStructure)
const langOptions = Object.values(locale.languageOptions)

// Selected language state
const selectedLangCode = ref(langOptions[0]?.code || 'en')

// Translations state
const needToTranslate = ref(
    langOptions.reduce((acc, lang) => {
        const code = lang.code
        acc[code] = {
            name: props.data.tradeUnit.name_i8n?.[code] || '',
            description: props.data.tradeUnit.description_i8n?.[code] || '',
            description_title: props.data.tradeUnit.description_title_i8n?.[code] || '',
            description_extra: props.data.tradeUnit.description_extra_i8n?.[code] || ''
        }
        return acc
    }, {} as Record<string, {
        name: string
        description: string
        description_title: string
        description_extra: string
    }>)
)

const selectedLang = computed(() => langOptions.find(opt => opt.code === selectedLangCode.value))

// Local reactive bindings
const translatedTitle = ref('')
const translatedDescription = ref('')
const translatedDescriptionTitle = ref('')
const translatedDescriptionExtra = ref('')

// Update local bindings when language changes
const updateTranslation = () => {
    const lang = selectedLangCode.value
    const current = needToTranslate.value[lang]
    translatedTitle.value = current?.name || ''
    translatedDescription.value = current?.description || ''
    translatedDescriptionTitle.value = current?.description_title || ''
    translatedDescriptionExtra.value = current?.description_extra || ''
}
watch(selectedLangCode, updateTranslation, { immediate: true })

// Sync edits to translation map
watch(
    [translatedTitle, translatedDescription, translatedDescriptionTitle, translatedDescriptionExtra],
    () => {
        const lang = selectedLangCode.value
        if (!needToTranslate.value[lang]) {
            needToTranslate.value[lang] = {
                name: '',
                description: '',
                description_title: '',
                description_extra: ''
            }
        }
        needToTranslate.value[lang] = {
            name: translatedTitle.value,
            description: translatedDescription.value,
            description_title: translatedDescriptionTitle.value,
            description_extra: translatedDescriptionExtra.value
        }
    }
)


const saveTranslation = () => {
    const translations = needToTranslate.value
    console.log('Saving translations:', translations)
}
</script>

<template>
    <div class="px-8 grid grid-cols-2 gap-8">
        <!-- Edit Component -->
        <EditTradeUnit :tags_selected_id="props.data.tags_selected_id" :brand="props.data.brand"
            :brand_routes="props.data.brand_routes" :tags="props.data.tags" :tag_routes="props.data.tag_routes" />

        <!-- Translation Section Wrapped -->
        <div class="col-span-2 mt-6">
            <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                    {{ trans('🌍 Multi-language Translations')}}
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Editable EN content -->
                    <div class="bg-gray-50 border border-gray-300 rounded-md p-4 shadow-sm">
                        <h3 class="text-base font-semibold flex items-center gap-2 mb-3">
                            {{ trans('Master') }}
                        </h3>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Title</label>
                            <PureInput v-model="needToTranslate.en.name" type="text" class="text-sm"
                                placeholder="Enter title" />
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Description Title</label>
                            <PureInput v-model="needToTranslate.en.description_title" type="text" class="text-sm"
                                placeholder="Enter description title" />
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Description</label>
                            <PureTextarea v-model="needToTranslate.en.description" rows="3" class="text-sm"
                                placeholder="Enter description" />
                        </div>

                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Description Extra</label>
                            <PureTextarea v-model="needToTranslate.en.description_extra" rows="3" class="text-sm"
                                placeholder="Enter extra description" />
                        </div>
                    </div>

                    <!-- Translated content -->
                    <div class="bg-white border border-gray-300 rounded-md p-4 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-base font-semibold flex items-center gap-2">
                                🌐 Translation ({{ selectedLangCode?.toUpperCase() || '—' }})
                            </h3>

                            <select v-model="selectedLangCode" class="text-xs border rounded px-2 py-1 bg-gray-100">
                                <option v-for="opt in langOptions" :key="opt.code" :value="opt.code">
                                    {{ opt.name }}
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Title</label>
                            <PureInput v-model="translatedTitle" type="text" class="text-sm"
                                placeholder="Enter translated title" />
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Description Title</label>
                            <PureInput v-model="translatedDescriptionTitle" type="text" class="text-sm"
                                placeholder="Enter translated description title" />
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-700 mb-1">Description</label>
                            <PureTextarea v-model="translatedDescription" rows="3" class="text-sm"
                                placeholder="Enter translated description" />
                        </div>

                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Description Extra</label>
                            <PureTextarea v-model="translatedDescriptionExtra" rows="3" class="text-sm"
                                placeholder="Enter translated description extra" />
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex justify-end mt-6">
                   <Button :type="'save'" @click="saveTranslation"/>
                </div>
            </div>
        </div>

    </div>
</template>
