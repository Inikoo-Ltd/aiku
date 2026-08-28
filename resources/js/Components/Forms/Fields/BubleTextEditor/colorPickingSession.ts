export type ColorPickingCloseDecision = 'close' | 'stay-open' | 'wait-for-window-focus'

export interface ColorPickingCloseState {
	isPickingColor: boolean
	documentHasFocus: boolean
	activeElementIsColorInput: boolean
}

/**
 * Safari opens `<input type="color">` in a separate window, so the input blurs and the
 * page loses focus while the panel (and its hex field) is still in use. The session may
 * only end once the page has focus back and the picker input no longer holds it.
 */
export const decideColorPickingClose = ({
	isPickingColor,
	documentHasFocus,
	activeElementIsColorInput,
}: ColorPickingCloseState): ColorPickingCloseDecision => {
	if (!isPickingColor) {
		return 'stay-open'
	}

	if (!documentHasFocus) {
		return 'wait-for-window-focus'
	}

	return activeElementIsColorInput ? 'stay-open' : 'close'
}
