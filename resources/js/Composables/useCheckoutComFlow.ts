/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 May 2025 10:01:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import { ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components'
import { CheckoutComFlow } from "@/types/CheckoutComFlow"

export function useCheckoutCom(checkoutComData: CheckoutComFlow) {
  const isLoading = ref(false)

  const initializeCheckout = async (containerId: string = 'flow-container') => {
    isLoading.value = true

    try {
      const checkout = await loadCheckoutWebComponents({
        paymentSession: checkoutComData.data,
        publicKey: checkoutComData.public_key,
        environment: checkoutComData.environment,
        locale: checkoutComData.locale,
        onReady: () => {
          console.log("onReady")
        },
        onPaymentCompleted: (_component, paymentResponse) => {
          console.log("Create Payment with PaymentId: ", paymentResponse.id)
        },
        onChange: (component) => {
          console.log(`onChange() -> isValid: "${component.isValid()}" for "${component.type}"`)
        },
        onError: (component, error) => {
          console.log("onError", error, "Component", component.type)
        },
      })

      const flowComponent = checkout.create('flow')
      flowComponent.mount(`#${containerId}`)
    } catch (error) {
      console.error("Error initializing Checkout.com:", error)
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    initializeCheckout
  }
}
