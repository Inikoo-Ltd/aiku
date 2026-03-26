import { ref, computed } from 'vue'
import axios from 'axios'

const open = ref(true)
const products = ref<any[]>([])
const step = ref(1)
const title = ref('')

const summary = ref({
    total_price: 0,
    total_bundle_price: 0,
    total_rrp: 0,
    profit: 0,
    profit_percentage: 0
})

const isSummaryLoading = ref(false)

export function useBundle(){

   const addProduct = (product:any)=>{
      const exist = products.value.find(p => p.id === product.id)

      if(!exist){
         products.value.push({
            ...product,
            quantity: 1
         })
      }
      console.log('[useBundle] addProduct =>', {
            id: product.id,
            name: product.name,
            price: product.price,
            web_images: product.web_images,
            // allKeys: Object.keys(product),
            fullObject: product
        })
      open.value = true
      calculateBundle()
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

   const close = ()=>{
      open.value = false
   }

    const calculateBundle = async (bundleRoutes?: any) => {
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

        // Guard: kalau routes belum di-set, skip
        if (!bundleRoutes.value?.calculate?.name) {
            console.warn('[useBundle] bundleRoutes belum di-set, skip calculateBundle')
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

            // bundleRoutes di-pass dari component karena composable tidak bisa inject layout
            const { data } = await axios.post(
                route(
                    bundleRoutes?.calculate?.name,
                    bundleRoutes?.calculate?.parameters
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

   const costPrice = computed(()=>{
      return products.value.reduce((t,p)=> t + (p.price * p.quantity),0)
   })

   const bundlePrice = computed(()=>{
      return costPrice.value * 0.9
   })

   return {
      open,
      products,
      step,
      title,
      addProduct,
      removeProduct,
      increaseQty,
      decreaseQty,
      close,
      summary,
      isSummaryLoading,
      calculateBundle,
   }
}