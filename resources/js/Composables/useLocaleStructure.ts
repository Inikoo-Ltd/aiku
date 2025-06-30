/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 23-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

import { Language } from '@/types/Locale'
import language from "@/Components/Forms/Fields/Language.vue";

export const aikuLocaleStructure = {
    language: {
        id: 68,
        code: 'en',
        name: 'English',
    } as Language,
    languageOptions: [
        {
            id: 68,
            code: 'en',
            name: 'English',
        }
    ] as Language[],
    number: (number: number) => {
        return new Intl.NumberFormat('en').format(number)
    },
    currencySymbol: (currencyCode: string) => {
		if(!currencyCode) return '-'
		
		return new Intl.NumberFormat('en', {
			style: 'currency',
			currency: currencyCode,
			currencyDisplay: 'symbol'
		}).formatToParts(123).find(part => part.type === 'currency')?.value ?? '';
	},
    currencyFormat: (currencyCode: string, amount: number):string => {
      return new Intl.NumberFormat(language.value.code, {
        style: "currency",
        // currency: currencyCode || "usd",
        currency: currencyCode,
      }).format(amount || 0)
    }
}