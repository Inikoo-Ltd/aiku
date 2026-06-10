<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, onMounted, onBeforeUnmount, computed, provide } from "vue"
import { faCheck, faPlus, faMinus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import LayoutIris from "@/Layouts/Iris.vue"
import { getIrisComponent } from "@/Iris/Composables/getIrisComponents"
import { usePage } from "@inertiajs/vue3"
import ReviewByStore from "@/Components/CMS/Reviews/ReviewByStore.vue"

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
}>()

defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)

const layout: any = inject("layout", {})
const review = ref(usePage().props?.iris?.website?.reviews_settings)
const screenType = ref<"mobile" | "tablet" | "desktop">("desktop")
const currentUrl = ref("")

provide('webpage_data', props.webpage_data)

const checkScreenType = () => {
    const width = window.innerWidth
    if (width < 640) screenType.value = "mobile"
    else if (width >= 640 && width < 1024) screenType.value = "tablet"
    else screenType.value = "desktop"
}

const robotsContent = computed(() => {
    const index = props.index_page ? "index" : "noindex"
    const follow = props.follow_link ? "follow" : "nofollow"
    return `${index}, ${follow}`
})

const PRODUCT_BLOCK_TYPES = ['products-1', 'products-2']

const generateProductsStructureFromProductsList = (webBlocks: any[]): any[] => {
    const variants: any[] = []
    const categoryName = (props.webpage_data as any)?.title ?? null  // "Bath & Body > Bath Salts"

    const webBlocksArray = Array.isArray(webBlocks) ? webBlocks : Object.values(webBlocks ?? {}).flat()

    for (const block of webBlocksArray) {
        if (!PRODUCT_BLOCK_TYPES.includes(block.type)) continue

        const fieldValue = block?.web_block?.layout?.data?.fieldValue ?? block?.structure
        const products: any[] = fieldValue?.products?.data ?? []

        for (const product of products) {
            if (!product.url) continue

            const variant: Record<string, any> = {
                '@type': 'Product',
                '@id': product.url,
                'name': product.name,
                'sku': product.code,
                'url': product.url,
            }

            if (product.description) {
                variant['description'] = product.description
            }

            const brandName = product.brand_name || undefined
            if (brandName) {
                variant['brand'] = { '@type': 'Brand', 'name': brandName }
            }

            if (categoryName) {
                variant['category'] = categoryName
            }

            const imageUrl = product.web_images?.main?.original?.original
                ?? product.web_images?.main?.gallery?.original
                ?? product.image?.source?.original

            if (imageUrl) {
                variant['image'] = [imageUrl]
            }

            if (product.rating && product.rating_count) {
                variant['aggregateRating'] = {
                    '@type': 'AggregateRating',
                    'ratingValue': product.rating,
                    'reviewCount': product.rating_count,
                    'bestRating': 5,
                    'worstRating': 1,
                }
            }

            if (product.price) {
                variant['offers'] = {
                    '@type': 'Offer',
                    'price': product.price,
                    'priceCurrency': layout.iris?.currency?.code,
                    'availability': product.stock > 0
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url': product.url,
                }
            }

            variants.push(variant)
        }
    }

    return variants
}

const blablabla = ref("")

const parseStructuredData = (raw: any): Record<string, any> | null => {
    if (!raw) return null
    if (typeof raw === 'object') return raw
    if (typeof raw !== 'string') return null
    try {
        return JSON.parse(raw)
    } catch {
        return null
    }
}

const buildFamilyProductNode = (): Record<string, any> => {
    const node: Record<string, any> = {
        '@type': 'ProductGroup',
        'name': props.webpage_data.title,
    }

    if (props.webpage_data.description) {
        node['description'] = props.webpage_data.description
    }

    const websiteName = layout?.iris?.website?.name

    node['aggregateRating'] = {
        '@type': 'AggregateRating',
        'ratingValue': 4.8,
        'reviewCount': 524,
    }

    if (websiteName) {
        node['review'] = {
            '@type': 'Review',
            'reviewRating': {
                '@type': 'Rating',
                'ratingValue': 5,
                'bestRating': 5,
            },
            'author': {
                '@type': 'Organization',
                'name': websiteName,
            },
        }
    }

    return node
}

const findOrCreateProductGroupNode = (data: Record<string, any>): Record<string, any> => {
    console.log('aaa')
    console.log('aa1', data['@graph'])
    if (Array.isArray(data['@graph'])) {
        console.log('aa2', data['@graph'])
        const existing = data['@graph'].find((node: any) => node['@type'] === 'ProductGroup') ?? null
        if (existing) return existing

        const newNode = buildFamilyProductNode()
        data['@graph'].push(newNode)
        return newNode
    }

    if (data['@type'] === 'ProductGroup') {
        return data
    }

    const newNode = buildFamilyProductNode()
    data['@context'] = 'https://schema.org'
    data['@graph'] = [newNode]
    return newNode
}

const mergeAutoVariants = (productNode: Record<string, any>, autoVariants: any[]): void => {
    const variantMap = new Map<string, any>(
        (productNode.hasVariant ?? []).map((v: any) => [v['@id'], v])
    )

    for (const variant of autoVariants) {
        if (!variantMap.has(variant['@id'])) {
            variantMap.set(variant['@id'], variant)
        }
    }

    productNode.hasVariant = Array.from(variantMap.values())
}

const injectStructuredDataScript = (data: Record<string, any>): void => {
    try {
        const script = document.createElement('script')
        script.type = 'application/ld+json'
        script.textContent = JSON.stringify(data)
        document.head.appendChild(script)
    } catch (e) {
        console.error('Failed to inject structured data:', e)
    }
}

onMounted(() => {
    currentUrl.value = window.location.href

    if (props.webpage_data.model_type === 'ProductCategory' && props.webpage_data.sub_type === 'family') {
        let structuredData = parseStructuredData((props.webpage_data?.seo_data as any)?.structured_data)

        const autoVariants = generateProductsStructureFromProductsList(props.web_blocks)
        console.log('autoVariants', autoVariants)

        if (autoVariants.length) {
            if (!structuredData || typeof structuredData !== 'object') {
                structuredData = { '@context': 'https://schema.org' }
            }

            const productNode = findOrCreateProductGroupNode(structuredData)
            mergeAutoVariants(productNode, autoVariants)
        }

        if (structuredData) {
            blablabla.value = structuredData
            injectStructuredDataScript(structuredData)
        }
    }

    checkScreenType()
    window.addEventListener('resize', checkScreenType)
    window.listWebBlocks = props.web_blocks
    layout.recordWebsiteHit()
})


onBeforeUnmount(() => {
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
        <div class="mx-auto w-full max-w-screen-3xl">
            <div
                v-for="(web_block_data, index) in props.web_blocks"
                :key="'block-' + web_block_data.id"
                class="w-full"
                :id="`v-${web_block_data.type}-${index}`"
            >
                <component
                    :screenType="screenType"
                    :code="web_block_data.type"
                    :is="getIrisComponent(web_block_data.type, { shop_type: layout.retina.type })"
                    :fieldValue="web_block_data?.web_block?.layout?.data?.fieldValue || web_block_data.structure"
                    :indexBlock="Number(index)"
                />
            </div>

            <!-- REVIEW -->
            <div 
                v-if="(webpage_data.type == 'storefront' || webpage_data.model_type == 'ProductCategory') && (review?.enabled ?? true)"
                class="my-10 2xl:my-16">
                <div>
                    <ReviewByStore :code="'review-by-store'" />
                </div>
            </div>

        </div>
    </div>
</template>
