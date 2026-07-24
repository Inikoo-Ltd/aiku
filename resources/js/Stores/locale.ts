/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 23 Oct 2022 09:30:38 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import type { Language } from "@/types/Locale"
import { ref } from "vue"


// Pinia Store
export const useLocaleStore = defineStore("locale", () => {
	const locale_iso = ref<string | null>(null)   // 'en-GB'
	const language = ref<Language>({
		id: 68,
		code: "en",
		name: "English",
	})
	const languageOptions = ref<Language[]>([language.value])
	const languageAssetsOptions = ref<Language[]>([language.value])
	const currencyInertia = ref({})

	const number = (number: number) => {
		return new Intl.NumberFormat(locale_iso.value || language.value.code).format(number)
	}

	// Derive the locale from the currency's home country so the amount is laid out
	// the way that country writes it (e.g. RON -> ro-RO -> "80,09 lei", not "lei 80.09").
	// The first two letters of an ISO 4217 currency code are the ISO 3166 country code.
	const localeForCurrency = (currencyCode: string): string | undefined => {
		if (!currencyCode) {
			return locale_iso.value || language.value.code
		}
		try {
			const region = currencyCode.slice(0, 2).toUpperCase()
			const maximized = new Intl.Locale("und", { region }).maximize()
			return `${maximized.language}-${region}`
		} catch {
			return locale_iso.value || language.value.code
		}
	}

	const currencyFormat = (currencyCode: string, amount: number | string): string | number => {
		// if (typeof amount === "undefined" || amount === null) return 0
		const getAmount = amount ?? 0
		if (!currencyCode) {
			return getAmount || 0
		}

		const num = typeof getAmount === "string" ? parseFloat(getAmount) : (getAmount || 0)
		const resolvedCurrency = currencyInertia.value?.code || currencyCode || ''

		const formatter = new Intl.NumberFormat(localeForCurrency(resolvedCurrency), {
			style: resolvedCurrency ? "currency" : "decimal",
			currency: resolvedCurrency,
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
			currencyDisplay: "narrowSymbol",  // to make UAH -> ₴, USD -> $, etc.
		})

		return formatter.format(num);
	};

	const currencySymbolNarrow = (currencyCode: string) => {
		const resolvedCurrency = currencyInertia.value?.code || currencyCode || ''

		const formatter = new Intl.NumberFormat(localeForCurrency(resolvedCurrency), {
			style: resolvedCurrency ? "currency" : "decimal",
			currency: resolvedCurrency,
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
			currencyDisplay: "narrowSymbol",  // to make UAH -> ₴, USD -> $, etc.
		})

		return formatter.formatToParts(1).find(part => part.type === 'currency')?.value ?? ''
	}

	const numberShort = (number: number) => {
		return new Intl.NumberFormat(locale_iso.value || language.value.code, {
			notation: "compact",
			compactDisplay: "short",
			maximumFractionDigits: 1,
		}).format(number)
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
				return `${p1}${p2}`;
			});

			return formattedNumber;

	}

	return { language, locale_iso, languageOptions, number, numberShort, currencySymbolNarrow, currencyFormat, CurrencyShort, currencySymbol, languageAssetsOptions  }
})
