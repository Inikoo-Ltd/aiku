export const snapToBatch = (
	previous: number,
	next: number,
	quantum: number | null | undefined
): number => {
	const step = Number(quantum) || 1
	if (step <= 1 || next <= 0) {
		return Math.max(0, next)
	}

	return next < previous ? Math.floor(next / step) * step : Math.ceil(next / step) * step
}
