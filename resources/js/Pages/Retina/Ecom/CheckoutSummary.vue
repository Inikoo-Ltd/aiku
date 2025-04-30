<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faPencil } from "@fal"
</script>

<template>
    <div class="py-4 grid grid-cols-3 px-4">
        <div>
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faClipboard" class="" fixed-width aria-hidden="true" />
                Delivery Address
                <FontAwesomeIcon :icon="faPencil" class="text-gray-400 hover:text-gray-600 text-sm" fixed-width aria-hidden="true" />
            </div>
            <div class="pl-6 pr-3">
                <span class="recipient fn hide">Katka Buchy</span>
                <span class="address-line1 street-address">Affinity Park</span><br>
                <span class="address-line2 extended-address">Europa Drive</span><br>
                <span class="locality">Sheffield</span><br>
                <span class="postal-code">S9 1XT</span><br>
                <span class="country country-name">United Kingdom</span>
            </div>
        </div>

        <div>
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
                Invoice Address
                <FontAwesomeIcon :icon="faPencil" class="text-gray-400 hover:text-gray-600 text-sm" fixed-width aria-hidden="true" />
            </div>
            <div class="pl-6 pr-3">
                <span class="recipient fn hide">Katka Buchy</span>
                <span class="address-line1 street-address">Affinity Park</span><br>
                <span class="address-line2 extended-address">Europa Drive</span><br>
                <span class="locality">Sheffield</span><br>
                <span class="postal-code">S9 1XT</span><br>
                <span class="country country-name">United Kingdom</span>
            </div>
        </div>

        <div class="text-right">
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Item gross</div>
                <div>103.61</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Discounts</div>
                <div>-31.83</div>
            </div>
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Item Net</div>
                <div>71.78</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Charges</div>
                <div>0.00</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Shipping</div>
                <div>7.50</div>
            </div>
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Net</div>
                <div>79.28</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>
                    <div>Tax</div>
                    <div class="text-xs text-gray-400">GB-SR VAT 20%</div>
                </div>
                <div>15.86</div>
            </div>
            <div class="font-semibold py-1 border-t border-b border-gray-400 grid grid-cols-2 ">
                <div class="">Total</div>
                <div>79.28</div>
            </div>
        </div>
    </div>
</template>