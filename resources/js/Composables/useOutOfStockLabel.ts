import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

export const useOutOfStockLabel = (product?: { expected_back_in_stock_at?: string | null }) => {
	const expectedAt = product?.expected_back_in_stock_at

	if (!expectedAt) {
		return ctrans("Out Of Stock")
	}

	return ctrans("Out Of Stock, expected back around :date", {
		date: useFormatTime(expectedAt, { formatTime: "mdy" }),
	})
}
