<script setup lang="ts">
import { faCube, faLink, faSeedling, faHeart } from "@fal"
import { faBox, faPlus, faVial } from "@far"
import { faCircle, faStar, faDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref , inject, useAttrs, onMounted} from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useLocaleStore } from '@/Stores/locale'
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import ProductContents from "./ProductContents.vue"
import InformationSideProduct from "./InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"

library.add(faCube, faLink)

type TemplateType = 'webpage' | 'template'

const props = withDefaults(defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: object
  templateEdit?: TemplateType
}>(), {
  templateEdit: 'webpage'
})
const layout = inject('layout',{})
const locale = useLocaleStore()
const isFavorite = ref(false)
const cancelToken = ref<Function | null>(null)
const contentRef = ref(null)
const expanded = ref(false)
const showButton = ref(false)

const toggleFavorite = () => {
    isFavorite.value = !isFavorite.value
}
const debounceTimer = ref(null)
const onDescriptionUpdate = (key : string, val : string) => {
    clearTimeout(debounceTimer.value)
    debounceTimer.value = setTimeout(() => {
        saveDescriptions(key, val)
    }, 5000)
}

const saveDescriptions = (key : string, val : string) => {
    if (cancelToken.value) cancelToken.value()
    router.patch(
        route("grp.models.product.update", { product: props.modelValue.product.id }),
        { [key]: val },
        {
            preserveScroll: false,
            onCancelToken: (token) => {
                cancelToken.value = token.cancel
            },
            onFinish: () => {
                cancelToken.value = null
            },
            onSuccess: () => { },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}

function formatNumber(value : Number) {
  return Number.parseFloat(value).toString();
}

defineOptions({
  inheritAttrs: false,
})

const attrs = useAttrs()


onMounted(() => {
  // Tunggu render selesai
  requestAnimationFrame(() => {
    if (contentRef?.value?.scrollHeight > 100) {
      showButton.value = true
    }
  })
})

const toggleExpanded = () => {
  expanded.value = !expanded.value
}
console.log(props)

</script>

<template>
    <div id="product-1" v-bind="attrs"
        class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :images="modelValue.product.images" />
                </div>
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="(tag,index) in modelValue.product.tags"
                        :key="index">
                        <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                        <div v-else class="aspect-square w-full h-[15px]">
                            <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`"
                                class="w-full h-full object-cover" />
                        </div>
                        <span>{{ tag.name }}</span>
                    </div>
                </div>
            </div>
            <div class="col-span-5 self-start">
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-gray-900">{{ modelValue.product.name }}</h1>
                        <div class="flex flex-wrap gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>Product code: {{ modelValue.product.code }}</div>
                            <div class="flex items-center gap-[1px]">
                                <!--   <FontAwesomeIcon :icon="faStar" class="text-[10px] text-yellow-400" v-for="n in 5"
                                    :key="n" />
                                <span class="ml-1 text-xs text-gray-500">41</span> -->
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="modelValue.product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                            <span>
                                {{
                                modelValue.product.stock > 0
                                ? `In Stock (${modelValue.product.stock})`
                                : 'Out Of Stock'
                                }}
                            </span>
                        </div>
                    </div>
                    <div class="h-full flex items-start">
                        <!-- Favorite Icon -->
                        <template v-if="layout.iris?.is_logged_in">
                            <div v-if="isLoadingFavourite" class="xabsolute top-2 right-2 text-gray-500 text-2xl">
                                <LoadingIcon />
                            </div>
                            <div v-else
                                @click="() => modelValue.product.is_favourite ? onUnselectFavourite(modelValue.product) : onAddFavourite(modelValue.product)"
                                class="cursor-pointer xabsolute top-2 right-2 group text-2xl ">
                                <FontAwesomeIcon v-if="modelValue.product.is_favourite" :icon="fasHeart" fixed-width
                                    class="text-pink-500" />
                                <FontAwesomeIcon v-else :icon="faHeart" fixed-width
                                    class="text-gray-400 group-hover:text-pink-400" />
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex items-end pb-3 mb-3">
                    <div class="text-gray-900 font-semibold text-3xl capitalize leading-none flex-grow min-w-0">
                        {{ locale.currencyFormat(currency?.code, modelValue.product.price || 0) }}
                        <span class="text-sm text-gray-900 ml-2 whitespace-nowrap">({{
                            formatNumber(modelValue.product.units) }}/{{
                            modelValue.product.unit }})</span>
                    </div>
                    <div v-if="modelValue.product.rrp"
                        class="text-sm text-gray-800 font-semibold text-right whitespace-nowrap pl-4">
                        <span>RRP: {{ locale.currencyFormat(currency?.code, modelValue.product.rrp || 0) }}</span>
                        <span>/{{ modelValue.product.unit }}</span>
                    </div>
                </div>
                <div class="flex gap-2 mb-6">
                    <ButtonAddPortfolio :product="modelValue.product" />
                </div>
                <div class="flex items-center text-sm text-medium text-gray-500 mb-6">
                    <FontAwesomeIcon :icon="faBox" class="mr-3 text-xl" />
                    <span>{{`order ${formatNumber(modelValue?.product?.units)} for full pack`}}</span>
                </div>
                <div class="space-y-1">
                    <!-- Description Title -->
                    <div class="text-sm font-medium text-gray-800">
                        <input v-if="templateEdit === 'webpage'" placeholder="Description title"
                            v-model="modelValue.product.description_title"
                            @update:model-value="(e) => onDescriptionUpdate('description_title', e)"
                            class="w-full bg-transparent text-sm border-0 px-0 py-0 focus:outline-none focus:ring-0 transition  font-medium text-gray-800 placeholder-gray-400" />
                        <div v-else>{{ modelValue.product.description_title }}</div>
                    </div>

                    <!-- Description Body -->
                    <div class="text-xs font-normal text-gray-700">
                        <EditorV2 v-if="templateEdit === 'webpage'" v-model="modelValue.product.description"
                            placeholder="Write product description..."
                            @update:model-value="(e) => onDescriptionUpdate('description', e)"
                            class="text-xs text-gray-800" />
                        <div v-else class="prose prose-sm text-gray-700 max-w-none"
                            v-html="modelValue.product.description"></div>
                    </div>
                </div>

                <div v-if="modelValue.setting?.information" class="my-4 space-y-2">
                    <InformationSideProduct v-if="modelValue?.information?.length > 0"
                        :informations="modelValue?.information" />
                    <div v-if="modelValue?.paymentData?.length > 0"
                        class="items-center gap-3 border-gray-400 font-bold text-gray-800 py-2">
                        Secure Payments:
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in modelValue?.paymentData" :key="logo.code" v-tooltip="logo.code"
                                :src="logo.image" :alt="logo.code" class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-xs font-normal text-gray-700 py-2">
            <EditorV2 v-if="templateEdit === 'webpage'" v-model="modelValue.product.description_extra"
                placeholder="Write product description extra ..."
                @update:model-value="(e) => onDescriptionUpdate('description_extra', e)" class="text-xs text-gray-800" />
            <div v-else   ref="contentRef" class="prose prose-sm text-gray-700 max-w-none" v-html="modelValue.product.description_extra"></div>

             <button v-if="showButton" @click="toggleExpanded"
                class="mt-1 text-gray-900 text-xs underline focus:outline-none">
                {{ expanded ? 'Show Less' : 'Read More' }}
            </button>
        </div>


        <ProductContents v-if="templateEdit == 'webpage'" :product="props.modelValue.product"
            :setting="modelValue.setting" />
    </div>

    <!-- Mobile Layout -->
    <div class="block sm:hidden px-4 py-6 text-gray-800">
        <h2 class="text-xl font-bold mb-2">{{ modelValue.product.name }}</h2>
        <ImageProducts :images="modelValue.product.images" />
        <div class="flex justify-between items-start gap-4 mt-4">
            <!-- Price + Unit Info -->
            <div>
                <div class="text-lg font-semibold">
                    {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.price || 0) }}
                    <span class="text-xs text-gray-500 ml-1">
                        ({{ formatNumber(modelValue.product.units) }}/{{ modelValue.product.unit }})
                    </span>
                </div>
                <div class="text-xs text-gray-400 font-semibold mt-1">
                    RRP: {{ locale.currencyFormat(modelValue.product.currency_code, modelValue.product.rrp || 0) }}
                </div>
            </div>

            <!-- Favorite Icon -->
            <div class="mt-1">
                <FontAwesomeIcon :icon="faHeart" class="text-xl cursor-pointer transition-colors duration-300"
                    :class="{ 'text-red-500': isFavorite, 'text-gray-400 hover:text-red-500': !isFavorite }"
                    @click="toggleFavorite" />
            </div>
        </div>


        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in modelValue.product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <div v-else class="aspect-square w-full h-[15px]">
                    <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`" class="w-full h-full object-cover" />
                </div>
                <span>{{ tag.name }}</span>
            </div>

        </div>
        <div class="mt-6 flex flex-col gap-2">
            <ButtonAddPortfolio :product="modelValue.product" />
        </div>
        <div class="text-xs font-medium py-3">
            <EditorV2 v-model="modelValue.product.description" @update:model-value="(e) => onDescriptionUpdate(e)" />
        </div>
        <div class="mt-4">
               <InformationSideProduct v-if="modelValue?.information?.length > 0" :informations="modelValue?.information" />
            <div class="text-sm font-semibold mb-2">Secure Payments:</div>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in modelValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>

        </div>

        <div class="text-xs font-normal text-gray-700 my-6">
            <EditorV2 v-if="templateEdit === 'webpage'" v-model="modelValue.product.description_extra"
                placeholder="Write product description extra ..."
                @update:model-value="(e) => onDescriptionUpdate('description_extra', e)" class="text-xs text-gray-800" />
            <div v-else class="prose prose-sm text-gray-700 max-w-none" v-html="modelValue.product.description_extra"></div>
        </div>

        <ProductContents v-if="templateEdit == 'webpage'" :product="props.modelValue.product"
            :setting="modelValue.setting" />
    </div>

</template>
