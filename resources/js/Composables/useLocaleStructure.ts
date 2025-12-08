/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 23-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

import { Language } from '@/types/Locale'

// Fallback if Pinia Store didn't provided
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
    currencyFormat: (currencyCode: string | null, amount: number): string | number => {
		if (typeof amount === "undefined" || amount === null) return 0

		if (!currencyCode) {
			return amount || 0
		}

        try {
            return new Intl.NumberFormat(aikuLocaleStructure.language.code, {
                style: "currency",
                currency: currencyCode,
            }).format(amount || 0)
        } catch (e) {
            return amount || 0
        }
    }
}