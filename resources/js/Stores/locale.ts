/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 23 Oct 2022 09:30:38 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import type { Language } from "@/types/Locale"
import { ref } from "vue"



export const useLocaleStore = defineStore("locale", () => {
	const language = ref<Language>({
		id: 68,
		code: "en",
		name: "English",
	})
	const languageOptions = ref<Language[]>([language.value])
	const currencyInertia = ref({})

	const number = (number: number) => {
		return new Intl.NumberFormat(language.value.code).format(number)
	}

	const currencyFormat = (currencyCode: string, amount: number):string => {
		if (!currencyCode) {
			if (currencyInertia.value?.code) {
				return new Intl.NumberFormat(language.value.code, {
					style: "currency",
					currency: currencyInertia.value?.code,
				}).format(amount || 0)
			}

			return new Intl.NumberFormat(language.value.code).format(amount || 0)
		}

		return new Intl.NumberFormat(language.value.code, {
			style: "currency",
			currency: currencyInertia.value?.code || currencyCode,
		}).format(amount || 0)
	}

	const currencySymbol = (currencyCode: string) => {
		if(!currencyCode) return '-'
		
		return new Intl.NumberFormat('en', {
			style: 'currency',
			currency: currencyCode,
			currencyDisplay: 'symbol'
		}).formatToParts(123).find(part => part.type === 'currency')?.value ?? '';
	}

	const CurrencyShort = (currencyCode: string, number: number) => {

			let formattedNumber = new Intl.NumberFormat("en", {
				notation: "compact",
				compactDisplay: "short",
				style: "currency",
				currency: currencyCode,
			}).format(number);
	
			formattedNumber = formattedNumber.replace(/(\d)([KMGTPE])/g, (match, p1, p2) => {
				return `${p1} ${p2.toLowerCase()}`;
			});
	
			return formattedNumber;

	}

	return { language, languageOptions, number, currencyFormat, CurrencyShort, currencySymbol }
})
