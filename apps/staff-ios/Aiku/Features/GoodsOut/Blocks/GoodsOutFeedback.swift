import SwiftUI

@MainActor
enum GoodsOutFeedback {
    static func done(_ text: String, tone: Tone = .success, previous: GoodsOutDeliveryNote?, toasts: ToastCenter, offersUndo: Bool = true) {
        guard let previous else {
            toasts.show("That isn’t possible for this delivery note now", tone: .warn)
            return
        }
        if offersUndo {
            toasts.show(text, tone: tone, actionTitle: "Undo", sticky: false) {
                GoodsOutStore.shared.restore(previous)
            }
        } else {
            toasts.show(text, tone: tone, sticky: false)
        }
    }
}
