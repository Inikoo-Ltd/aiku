import SwiftUI

struct GoodsOutNoteOverview: View {
    let note: GoodsOutDeliveryNote
    var onQuickAction: ((GoodsOutQuickAction) -> Void)?
    var onEditAddress: (() -> Void)?

    var body: some View {
        GoodsOutChannelChips(channel: note.channel, platform: note.platform)
        if let onQuickAction {
            GoodsOutQuickActions(onSelect: onQuickAction)
        }
        GoodsOutNotesSection(note: note)
        GoodsOutTimeline(note: note)
        GoodsOutOrderCards(note: note, onEditAddress: onEditAddress)
    }
}
