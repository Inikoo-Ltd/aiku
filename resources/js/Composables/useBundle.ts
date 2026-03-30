import { ref, computed, watch } from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

const open = ref(false)
const step = ref(1)
const title = ref('')
const description = ref('')

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

    const addProduct = (product: any) => {
        const exist = products.value.find(p => p.id === product.id)

        if (!exist) {
            products.value.push({
                ...product,
                quantity: 1
            })
        }
        open.value = true
    }

    const removeProduct = (id: number) => {
        products.value = products.value.filter(p => p.id !== id)
        calculateBundle()
    }

    const increaseQty = (id: number) => {
        const item = products.value.find(p => p.id === id)
        if (item) {
            item.quantity++
            calculateBundle()
        }
    }

    const decreaseQty = (id: number) => {
        const item = products.value.find(p => p.id === id)
        if (item && item.quantity > 1) {
            item.quantity--
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
                products: products.value.map(p => ({
                    product_id: p.id,
                    quantity: p.quantity || 1
                }))
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
    }, { deep: true })

    const generateAITitle = async () => {
        if (!productIds.value.length) return

        try {
            isGeneratingAI.value = true

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
            console.log("des", description.value)
            console.log("des data", data)
            notify({
                title: trans('Success'),
                text: trans('Success generate AI'),
                type: 'success'
            })
        } catch (e) {
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

    const storeBundle = async () => {
        try {
            isStoringBundle.value = true

            const payload = {
                name: title.value,
                code: '',
                description: '',
                price: summary.value.total_bundle_price || 0,
                rrp: summary.value.total_rrp || 0,
                products: products.value.map(p => ({
                    product_id: p.id,
                    quantity: p.quantity || 1
                }))
            }

            const routeName = bundleRoutes.store.name

            if (!routeName) {
                console.warn('[useBundle] generate_title route not defined')
                return
            }

            const params =
                typeof bundleRoutes.store.getParameters === 'function'
                    ? bundleRoutes.store.getParameters()
                    : bundleRoutes.store.parameters || {}

            if (!params?.customerSalesChannel) {
                console.warn('[useBundle] customerSalesChannel belum dipilih')
                return
            }
            const { data } = await axios.post(
                route(
                    routeName,
                    params
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
        summary.value = {
            total_price: 0,
            total_bundle_price: 0,
            total_rrp: 0,
            profit: 0,
            profit_percentage: 0
        }
    }
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
        resetBundle,

        addProduct,
        removeProduct,
        increaseQty,
        decreaseQty,
        close,
        calculateBundle,
        generateAITitle,
        generateAIDescription,

        storeBundle
    }
}