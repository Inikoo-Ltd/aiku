import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

type ProductWithExpectedBackInStock = { expected_back_in_stock_at?: string | null }

export const useExpectedBackInStockDate = (product?: ProductWithExpectedBackInStock): string | null => {
	const expectedAt = product?.expected_back_in_stock_at

	return expectedAt ? useFormatTime(expectedAt, { formatTime: "mdy" }) : null
}

export const useExpectedBackInStockLabel = (product?: ProductWithExpectedBackInStock): string | null => {
	const date = useExpectedBackInStockDate(product)

	return date ? ctrans("Expected back around :date", { date }) : null
}
