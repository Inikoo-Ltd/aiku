<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { isArray } from "lodash-es"
import { getProductRenderDropshippingComponentWorkshop } from "@/Composables/getWorkshopComponents"

library.add(faCube, faLink, faFileDownload)

type TemplateType = 'webpage' | 'template'

const props = withDefaults(defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: object
    templateEdit?: TemplateType
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
    code: string
    currency: {
        code: string
        name: string
    }
}>(), {
    templateEdit: 'webpage'
})

const cancelToken = ref<Function | null>(null)
const debounceTimer = ref(null)

const onDescriptionUpdate = (key: string, val: string) => {
    clearTimeout(debounceTimer.value)
    debounceTimer.value = setTimeout(() => {
        saveDescriptions(key, val)
    }, 5000)
}

const saveDescriptions = (key: string, val: string) => {
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

const imagesSetup = ref(isArray(props.modelValue.product.images) ? props.modelValue.product.images :
    props.modelValue.product.images
        .filter(item => item.type == "image")
        .map(item => ({
            label: item.label,
            column: item.column_in_db,
            images: item.images,
        }))
)

const videoSetup = ref(
    props.modelValue.product.images.find(item => item.type === "video") || null
)

const validImages = computed(() => {
    if (!imagesSetup.value) return []

    const hasType = imagesSetup.value.some(item => "type" in item)

    if (hasType) {
        return imagesSetup.value
            .filter(item => item.images)
            .flatMap(item => {
                const images = Array.isArray(item.images) ? item.images : [item.images]
                return images.map(img => ({
                    source: img,
                    thumbnail: img
                }))
            })
    }

    // berarti array of string/url
    return imagesSetup.value
})

</script>

<template>
    <component 
        :is="getProductRenderDropshippingComponentWorkshop(code)" 
        :modelValue 
        :webpageData 
        :blockData
        :templateEdit 
        :indexBlock 
        :screenType 
        :code 
        :currency 
        :validImages
        :videoSetup
        @onDescriptionUpdate="onDescriptionUpdate"
    />
</template>
