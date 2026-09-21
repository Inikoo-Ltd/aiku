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
    applyBlockProduct: (product: ProductLike) => void
}

/**
 * Keeps the product shown on a product page paired with its uncached detail.
 *
 * The block payload and the variant list carry a trimmed product: no step discount,
 * specifications or tags. Those only exist on the detail endpoint, so every selection
 * reloads the detail instead of assuming a previous load still applies. Reloading on
 * a slug change alone loses the detail whenever a selection resolves to the product
 * already on screen, which is what reselecting the variant leader from the URL does.
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
        detailData.value = {}
        selectedProduct.value = { ...product }
        options.onSelect?.(product)
        loadDetail(product)
    }

    const applyBlockProduct = (product: ProductLike): void => {
        if (selectedProduct.value?.id !== product.id) {
            detailData.value = {}
        }

        selectedProduct.value = { ...product, ...detailData.value }
        options.onSelect?.(product)
        loadDetail(product)
    }

    return { selectedProduct, detailData, loadDetail, selectProduct, applyBlockProduct }
}
