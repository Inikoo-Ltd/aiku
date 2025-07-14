/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 May 2025 10:01:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import { ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components'
import { CheckoutComFlow } from "@/types/CheckoutComFlow"
import { CheckoutTranslations } from "@/Composables/Unique/CheckoutFlowTranslation"
import { set } from "lodash"

export function useCheckoutCom(checkoutComData: CheckoutComFlow, otherOptions?: {
  isChangeLabelToSaved?: boolean
}) {
  const isLoading = ref(false)

  const selectedTranslation = {...CheckoutTranslations}
  if (otherOptions?.isChangeLabelToSaved) {
    set(selectedTranslation, ['en', 'pay_button.pay'], 'Save')
    set(selectedTranslation, ['cz', 'pay_button.pay'], 'Uložit')
    set(selectedTranslation, ['pl', 'pay_button.pay'], 'Zapisz')
    set(selectedTranslation, ['ro', 'pay_button.pay'], 'Salvează')
    set(selectedTranslation, ['hr', 'pay_button.pay'], 'Spremi')
    set(selectedTranslation, ['hu', 'pay_button.pay'], 'Mentés')
    set(selectedTranslation, ['bg', 'pay_button.pay'], 'Запази (Zapazi)')
  }

  const initializeCheckout = async (containerId: string = 'flow-container') => {
    isLoading.value = true

    try {
      const checkout = await loadCheckoutWebComponents({
        paymentSession: checkoutComData.data,
        publicKey: checkoutComData.public_key,
        environment: checkoutComData.environment,
        locale: checkoutComData.locale ?? 'en',
        translations: selectedTranslation,
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
        appearance: {
          // colorPrimary: '#ff0000',
          // colorSecondary: '#ffff00',
          colorAction: 'rgb(15, 22, 38)',
        }
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
