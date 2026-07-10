<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, onMounted, onBeforeUnmount, computed, provide } from "vue"
import { faCheck, faPlus, faMinus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head, usePage } from "@inertiajs/vue3"
import LayoutIris from "@/Layouts/Iris.vue"
import IrisBlockRenderer from "@/Iris/Components/IrisBlockRenderer.vue"
import { useStructuredData } from "@/Iris/Composables/useStructuredData"
import ReviewsIris from "@/Iris/Components/IrisBlocks/ReviewsIris.vue"
library.add(faCheck, faPlus, faMinus)

const props = defineProps<{
    webpage_data: {  // ShowIrisWebpage
        seo_data: {}
        title: string
        description: string
        canonical_url: string
        type: string  // 'catalogue'
        sub_type: string  // 'department' | 'sub_department' | 'family' | 'product'
        model_type: string  // 'ProductCategory' | 'Product'
    }
    web_blocks: any,
    webpage_img: any,
    index_page: boolean,
    follow_link: boolean
    reviews : any
    allow_review_reaction : boolean
    allow_review_reply_reaction : boolean
    minimum_reviews_to_show : number
    webpage_slug : string
    show_staff_who_reply : boolean
    webpage_id : number
}>()

defineOptions({ layout: LayoutIris })

const layout: any = inject("layout", {})
const review = ref(usePage().props?.iris?.website?.reviews_settings)
const getScreenType = (): "mobile" | "tablet" | "desktop" => {
    if (typeof window === "undefined") return "desktop"
    if (window.innerWidth < 640) return "mobile"
    if (window.innerWidth < 1024) return "tablet"
    return "desktop"
}
const screenType = ref<"mobile" | "tablet" | "desktop">(getScreenType())
const currentUrl = ref("")
const structuredDataScript = ref<HTMLScriptElement | null>(null)
const { mountStructuredData, removeStructuredDataScript } = useStructuredData()

provide('webpage_data', props.webpage_data)
provide('webpage_id', props.webpage_id)
provide('minimum_reviews_to_show', props.minimum_reviews_to_show)
provide('allow_review_reaction', props.allow_review_reaction)
provide('allow_review_reply_reaction', props.allow_review_reply_reaction)
provide('allow_review_reply_reaction', props.allow_review_reply_reaction)

const checkScreenType = () => {
    screenType.value = getScreenType()
}

const robotsContent = computed(() => {
    const index = props.index_page ? "index" : "noindex"
    const follow = props.follow_link ? "follow" : "nofollow"
    return `${index}, ${follow}`
})

onMounted(() => {
    currentUrl.value = window.location.href

    // Structure data (Family)
    // Breadcrumbs structured data is mounted independently in BreadcrumbsIris.vue
    // Product structured data is mounted independently in the product components (product-1 / product-2)
    // Department structured data is mounted independently in SubDepartmentsIris.vue
    structuredDataScript.value = mountStructuredData({
        webpageData: props.webpage_data,
        webBlocks: props.web_blocks,
        currencyCode: layout.iris?.currency?.code,
        websiteName: layout.iris?.website?.name,
    })

    checkScreenType()
    window.addEventListener('resize', checkScreenType)
    window.listWebBlocks = props.web_blocks
    layout.recordWebsiteHit()
})


onBeforeUnmount(() => {
    removeStructuredDataScript(structuredDataScript.value)
    window.removeEventListener("resize", checkScreenType)
})
</script>

<template>
    <Head>
        <title>{{ webpage_data.title }}</title>
        <meta name="description" :content="webpage_data.description || ''" />
        <meta name="robots" :content="robotsContent" />
        <link rel="canonical" :href="webpage_data.canonical_url || currentUrl" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="webpage_data.title || ''" />
        <meta property="og:description" :content="webpage_data.description || ''" />
        <meta property="og:url" :content="webpage_data.canonical_url || currentUrl" />
        <meta property="og:image" :content="webpage_img?.png || webpage_img?.url || ''" />
        <meta property="og:image:alt" :content="webpage_data.title || ''" />
        <meta property="og:locale" content="en_US" />
        <meta property="og:site_name" :content="usePage().props?.iris?.website?.name || webpage_data.title" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="webpage_data.title || ''" />
        <meta name="twitter:description" :content="webpage_data.description || ''" />
        <meta name="twitter:image" :content="webpage_img?.png || webpage_img?.url || ''" />
    </Head>
    
    <!-- <pre>{{ blablabla }}</pre> -->
    <div class="bg-white">
        <div class="mx-auto w-full">
            <div
                v-for="(web_block_data, index) in props.web_blocks"
                :key="'block-' + web_block_data.id"
                class="w-full"
                :id="`v-${web_block_data.type}-${index}`"
            >
                <IrisBlockRenderer
                    :type="web_block_data.type"
                    :shopType="layout.retina.type"
                    :screenType="screenType"
                    :code="web_block_data.type"
                    :fieldValue="web_block_data?.web_block?.layout?.data?.fieldValue || web_block_data.structure"
                    :indexBlock="Number(index)"
                />
            </div>

            <!-- REVIEW -->
            <div 
                v-if="(webpage_data.type == 'storefront' || webpage_data.model_type == 'ProductCategory') && (review?.enabled ?? true)">
                <div>
                 <!--    <ReviewByStore :code="'review-by-store'" /> -->
                     <ReviewsIris :webpage_id="webpage_id" />
                </div>
            </div>

        </div>
    </div>
</template>
