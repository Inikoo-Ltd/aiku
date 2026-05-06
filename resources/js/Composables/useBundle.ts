import { ref, computed, watch } from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

const open = ref(false)
const step = ref(1)
const title = ref('')
const description = ref('')

const STORAGE_KEY = 'iris_bundle_products'
const MAX_BUNDLE_PRODUCTS = 10

const products = ref<any[]>([])
const summary = ref({
    total_price: 0,
    total_bundle_price: 0,
    total_rrp: 0,
    profit: 0,
    profit_percentage: 0
})

let bundleRoutes: any = null
export function useBundle(routes?: any) {
    if (routes) {
        bundleRoutes = routes
    }
    const isGeneratingAI = ref(false)

    const productIds = computed(() => {
        return products.value.map(p => p.id)
    })

    const isSummaryLoading = ref(false)

    const aiTitleError = ref<string | null>(null)
    const aiDescError = ref<string | null>(null)

    const notifyMaxBundleProducts = () => {
        notify({
            title: trans('Information'),
            text: trans('Only a maximum of 10 selected products can be selected'),
            type: 'warn'
        })
    }

    const dedupeProducts = (items: any[] = []) => {
        const map = new Map()

        items.forEach((item) => {
            if (!item?.id) return
            const current = map.get(item.id)
            map.set(item.id, {
                ...(current || item),
                ...item,
                quantity: item.quantity ?? item.quantity_selected ?? current?.quantity ?? current?.quantity_selected ?? 1,
                quantity_selected: item.quantity_selected ?? item.quantity ?? current?.quantity_selected ?? current?.quantity ?? 1
            })
        })

        return Array.from(map.values())
    }

    const saveProductsToStorage = () => {
        if (typeof localStorage === 'undefined') return
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(products.value))
        } catch (e) {
            console.warn('[useBundle] failed saving products to storage', e)
        }
    }

    const loadProductsFromStorage = () => {
        if (typeof localStorage === 'undefined') return
        try {
            const raw = localStorage.getItem(STORAGE_KEY)
            if (!raw) return
            const parsed = JSON.parse(raw)
            if (Array.isArray(parsed)) {
                products.value = parsed
            }
        } catch (e) {
            console.warn('[useBundle] failed loading products from storage', e)
        }
    }

    const addProduct = (product: any) => {
        if (Array.isArray(product)) {
            products.value = product
            saveProductsToStorage()
            open.value = true
            return
        }

        const exist = products.value.find(p => p.id === product.id)

        if (!exist) {
            if (products.value.length >= MAX_BUNDLE_PRODUCTS) {
                notifyMaxBundleProducts()
                return
            }

            products.value.push({
                ...product,
                quantity: product?.quantity ?? product?.quantity_selected ?? 1,
                quantity_selected: product?.quantity_selected ?? product?.quantity ?? 1
            })
            saveProductsToStorage()
        }

        open.value = true
    }

    const removeProduct = (id: number) => {
        products.value = products.value.filter(p => p.id !== id)
        saveProductsToStorage()
        calculateBundle()
    }

    const increaseQty = (id: number) => {
        const item = products.value.find(p => p.id === id)
        if (item) {
            item.quantity++
            saveProductsToStorage()
            calculateBundle()
        }
    }

    const decreaseQty = (id: number) => {
        const item = products.value.find(p => p.id === id)
        if (item && item.quantity > 1) {
            item.quantity--
            saveProductsToStorage()
            calculateBundle()
        }
    }

    const close = () => {
        open.value = false
    }

    const calculateBundle = async () => {
        if (!products.value.length) {
            summary.value = {
                total_price: 0,
                total_bundle_price: 0,
                total_rrp: 0,
                profit: 0,
                profit_percentage: 0
            }
            return
        }

        if (!bundleRoutes?.calculate?.name) {
            console.warn('[useBundle] bundleRoutes not found')
            return
        }

        try {
            isSummaryLoading.value = true

            const payload = {
                products: dedupeProducts(products.value).map(p => ({
                    product_id: p.id,
                    quantity: p.quantity || 1
                })),
            }

            const params =
                typeof bundleRoutes.calculate.getParameters === 'function'
                    ? bundleRoutes.calculate.getParameters()
                    : bundleRoutes.calculate.parameters || {}

            if (!params?.customerSalesChannel) {
                console.warn('[useBundle] customerSalesChannel belum dipilih')
                return
            }

            const { data } = await axios.post(
                route(
                    bundleRoutes.calculate.name,
                    params
                ),
                payload
            )
            summary.value = data

        } catch (e) {
            console.error('[useBundle] calculateBundle failed', e)
        } finally {
            isSummaryLoading.value = false
        }
    }

    const debouncedCalculate = debounce(calculateBundle, 400)

    watch(products, () => {
        debouncedCalculate()
        saveProductsToStorage()
    }, { deep: true })

    const generateAITitle = async () => {
        if (!productIds.value.length) return

        try {
            isGeneratingAI.value = true
            aiTitleError.value = null
            const routeName = bundleRoutes?.ai?.generate_title?.name

            if (!routeName) {
                console.warn('[useBundle] generate_title route not defined')
                return
            }

            const { data } = await axios.post(
                route(routeName),
                {
                    products: productIds.value
                }
            )

            title.value = data
            notify({
                title: trans('Success'),
                text: trans('Success generate AI'),
                type: 'success'
            })
        } catch (e) {
            aiTitleError.value =
            'The OpenAI service is currently unreachable, please try again later.'

            console.error('[useBundle] generateAITitle failed', e)
            notify({
                title: trans('Error'),
                text: trans('Failed to generate AI'),
                type: 'error'
            })
        } finally {
            isGeneratingAI.value = false
        }
    }

    const generateAIDescription = async () => {
        try {
            isGeneratingAI.value = true
            aiDescError.value = null
            const routeName = bundleRoutes?.ai?.generate_description?.name

            if (!routeName) {
                console.warn('[useBundle] generate_title route not defined')
                return
            }

            const { data } = await axios.post(
                route(
                    routeName
                ),
                {
                    products: productIds.value
                }
            )
            description.value = data || ''
            notify({
                title: trans('Success'),
                text: trans('Success generate AI'),
                type: 'success'
            })
        } catch (e) {
            console.error('[useBundle] generateAIDescription failed', e)
            aiDescError.value = 'The OpenAI service is currently unreachable, please try again later.'
            notify({
                title: trans('Error'),
                text: trans('Failed to generate AI'),
                type: 'error'
            })
        } finally {
            isGeneratingAI.value = false
        }
    }

    const isStoringBundle = ref(false)
    const bundle_id = ref('')
    const product_id = ref('')
    const loadBundle = async ({
        routeConfig,
        bundleId,
        bundleParamOverride,
        onProductsLoaded
    }: {
        routeConfig: any,
        bundleId: any,
        bundleParamOverride?: Record<string, any>,
        onProductsLoaded?: (products: any[]) => void
    }) => {
        if (!routeConfig?.name || !bundleId) {
            console.warn('[useBundle] loadBundle missing route or bundleId')
            return
        }

        const baseParams =
            typeof routeConfig.getParameters === 'function'
                ? routeConfig.getParameters()
                : routeConfig.parameters || {}

        const routeParams = {
            ...baseParams,
            ...(bundleParamOverride ?? { bundle: bundleId })
        }

        const { data } = await axios.get(route(routeConfig.name, routeParams))
        const payload = data?.data || data
        if (!payload) return

        title.value = payload.name || ''

        const mappedProducts = dedupeProducts((payload.items || []).map((it: any) => ({
            id: it.item?.id,
            name: it.item?.name,
            code: it.item?.code,
            image: it.item?.image_thumbnail?.original || it.item?.images?.[0]?.thumbnail?.original,
            price_per_unit: Number(it.item?.price_per_unit || it.item?.price || 0),
            quantity: it.quantity || 1,
            quantity_selected: it.quantity || 1
        })))

        products.value = mappedProducts
        onProductsLoaded?.(mappedProducts)

        await calculateBundle()

        if (!summary.value.total_bundle_price && payload.price) {
            summary.value.total_bundle_price = Number(payload.price) || 0
        }
        if (!summary.value.total_rrp && payload.rrp) {
            summary.value.total_rrp = Number(payload.rrp) || 0
        }

        bundle_id.value = payload.id
        product_id.value = payload.bundleable_id
    }

    const storeBundle = async () => {
        try {
            isStoringBundle.value = true

            const payload = {
                name: title.value,
                code: '',
                description: '',
                price: summary.value.total_bundle_price || 0,
                rrp: summary.value.total_rrp || 0,
                products: dedupeProducts(products.value).map(p => ({
                    product_id: p.id,
                    quantity: p.quantity || 1
                })),
                id: bundle_id.value || null,
            }

            const routeName = bundleRoutes.store.name

            if (!routeName) {
                console.warn('[useBundle] generate_title route not defined')
                return
            }

            const baseParams =
                typeof bundleRoutes.store.getParameters === 'function'
                    ? bundleRoutes.store.getParameters()
                    : bundleRoutes.store.parameters || {}

            if (!baseParams?.customerSalesChannel) {
                console.warn('[useBundle] customerSalesChannel belum dipilih')
                return
            }
        
            const { data } = await axios.post(
                route(
                    routeName,
                    baseParams
                ),
                payload
            )

            product_id.value = data.bundleable_id
            bundle_id.value = data.id
            return data
        } catch (e) {
            console.error('[useBundle] storeBundle failed', e)
            throw e
        } finally {
            isStoringBundle.value = false
        }
    }

    const resetBundle = () => {
        title.value = ''
        description.value = ''

        products.value = []
        saveProductsToStorage()
        summary.value = {
            total_price: 0,
            total_bundle_price: 0,
            total_rrp: 0,
            profit: 0,
            profit_percentage: 0
        }
    }

    loadProductsFromStorage()

    return {
        products,
        title,
        description,
        step,
        open,
        summary,
        isSummaryLoading,
        isGeneratingAI,
        product_id,
        bundle_id,
        productIds,
        bundleRoutes,
        MAX_BUNDLE_PRODUCTS,
        resetBundle,
        isStoringBundle,
        aiTitleError,
        aiDescError,
        
        addProduct,
        removeProduct,
        increaseQty,
        decreaseQty,
        close,
        calculateBundle,
        generateAITitle,
        generateAIDescription,

        storeBundle,
        loadBundle
    }
}
