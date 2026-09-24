import { ref, type Ref } from "vue"

type ProductLike = Record<string, any>

interface SelectedProductDetailOptions {
    initialProduct: ProductLike
    fetchDetail: (slug: string) => Promise<Record<string, any>>
    onSelect?: (product: ProductLike) => void
}

interface SelectedProductDetail {
    selectedProduct: Ref<ProductLike>
    detailData: Ref<Record<string, any>>
    loadDetail: (product?: ProductLike) => Promise<void>
    selectProduct: (product: ProductLike) => void
}

/**
 * Keeps the product shown on a product page paired with its uncached detail.
 *
 * The block payload and the variant list carry a trimmed product: no step discount,
 * specifications or tags. Those only exist on the detail endpoint, so every selection
 * reloads the detail instead of assuming a previous load still applies. Reloading on
 * a slug change alone loses the detail whenever a selection resolves to the product
 * already on screen, which is what reselecting the variant leader from the URL does.
 *
 * Reselecting the product already on screen keeps the detail in place and refreshes it
 * in the background, so the step discount never blinks out and back. Only a move to a
 * different product drops it, where holding it would price the new product from the
 * old one's offer.
 */
export const useSelectedProductDetail = (options: SelectedProductDetailOptions): SelectedProductDetail => {
    const selectedProduct = ref<ProductLike>(options.initialProduct)
    const detailData = ref<Record<string, any>>({})
    const pendingSlugs = new Set<string>()

    const loadDetail = async (product: ProductLike = selectedProduct.value): Promise<void> => {
        const slug = product?.slug

        if (!slug || pendingSlugs.has(slug)) {
            return
        }

        pendingSlugs.add(slug)

        try {
            const detail = await options.fetchDetail(slug)

            if (selectedProduct.value?.slug !== slug) {
                return
            }

            detailData.value = detail
            selectedProduct.value = { ...selectedProduct.value, ...detail }
        } catch (error) {
            console.error("Failed to load uncached product data", error)
        } finally {
            pendingSlugs.delete(slug)
        }
    }

    const selectProduct = (product: ProductLike): void => {
        if (selectedProduct.value?.id !== product.id) {
            detailData.value = {}
        }

        selectedProduct.value = { ...product, ...detailData.value }
        options.onSelect?.(product)
        loadDetail(product)
    }

    return { selectedProduct, detailData, loadDetail, selectProduct }
}
