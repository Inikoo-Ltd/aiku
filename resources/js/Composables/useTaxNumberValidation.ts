export type TaxNumberStatus = "empty" | "country_missing" | "valid" | "invalid"

export const taxNumberStatus = (
	number: string | null | undefined,
	countryCode: string | null | undefined,
	isValidVatNumber: (prefixedNumber: string) => boolean,
): TaxNumberStatus => {
	if (!number) {
		return "empty"
	}

	if (!countryCode) {
		return "country_missing"
	}

	return isValidVatNumber(countryCode + number) ? "valid" : "invalid"
}
