import { computed, onMounted, ref, watch, type Ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'

export interface WebpageContent {
    id: number
    slug: string
    state: string
    code: string
    url: string
    url_prefix: string
    full_url: string
    canonical_url?: string
    breadcrumb_label?: string
    title?: string
    description?: string
    title_prefix?: string
    title_suffix?: string
    show_price: boolean
    index_page: boolean
    follow_link: boolean
    seo_image?: Record<string, any> | null
    seo_image_alt?: string
    structured_data?: string
}

export interface ShopContent {
    id: number
    slug: string
    code: string
    name?: string
    description?: string
    description_title?: string
    description_extra?: string
    follow_master: boolean
    shop_slug: string
    shop_code: string
    shop_name: string
    webpage: WebpageContent | null
}

export const useMasterShopsContent = (masterProductCategoryId: Ref<number | undefined>) => {
    const shops = ref<ShopContent[]>([])
    const isLoading = ref(false)
    const error = ref<string | null>(null)
    const selectedShopId = ref<number | null>(null)

    const selectedShop = computed(() => shops.value.find(shop => shop.id === selectedShopId.value) ?? null)

    const fetchShops = async () => {
        if (!masterProductCategoryId.value) {
            shops.value = []
            selectedShopId.value = null
            return
        }

        isLoading.value = true
        error.value = null

        try {
            const response = await axios.get(
                route('grp.json.master_product_category.shops_content.index', {
                    masterProductCategory: masterProductCategoryId.value,
                })
            )
            shops.value = response.data?.data ?? []
        } catch (exception: any) {
            error.value = exception?.response?.data?.message || trans('Failed to load the shops content')
            shops.value = []
        } finally {
            selectedShopId.value = shops.value[0]?.id ?? null
            isLoading.value = false
        }
    }

    onMounted(() => fetchShops())
    watch(masterProductCategoryId, () => fetchShops())

    return { shops, isLoading, error, selectedShopId, selectedShop, fetchShops }
}
