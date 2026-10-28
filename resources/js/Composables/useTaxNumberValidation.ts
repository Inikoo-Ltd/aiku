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

export type TaxNumberCheckResult = "valid" | "invalid" | "pending"

export const taxNumberCheckResult = (status: string | null | undefined, valid: boolean | null | undefined): TaxNumberCheckResult => {
	if (status === "invalid") {
		return "invalid"
	}

	if (status === "valid" || valid) {
		return "valid"
	}

	return status === "unknown" ? "pending" : "invalid"
}

export const taxNumberCheckDisplay: Record<TaxNumberCheckResult, { icon: string; colorClass: string; label: string }> = {
	valid: { icon: "fas fa-check-circle", colorClass: "text-green-600", label: "Valid" },
	invalid: { icon: "fas fa-exclamation-circle", colorClass: "text-red-600", label: "Invalid" },
	pending: { icon: "fad fa-spinner-third", colorClass: "text-yellow-600", label: "Pending" },
}
