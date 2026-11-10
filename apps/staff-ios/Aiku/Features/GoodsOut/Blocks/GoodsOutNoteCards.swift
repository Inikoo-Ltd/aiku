import SwiftUI

struct GoodsOutNoteCard: View {
    let title: String
    let icon: AppIcon
    var tone: Tone = .info
    var text: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(spacing: 8) {
                Text(title)
                    .font(.aiku(13, .bold))
                    .frame(maxWidth: .infinity, alignment: .leading)
                icon.image
                    .font(.system(size: 14))
                    .accessibilityHidden(true)
            }
            .foregroundStyle(tone == .warn ? AikuColor.warn : AikuColor.ink)
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(tone == .warn ? AikuColor.warnSoft : AikuColor.soft)
            Group {
                if let text, !text.isEmpty {
                    Text(text)
                        .foregroundStyle(AikuColor.text2)
                } else {
                    Text("No note added")
                        .italic()
                        .foregroundStyle(AikuColor.faint)
                }
            }
            .font(.aiku(14))
            .fixedSize(horizontal: false, vertical: true)
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .cardSurface()
    }
}

struct GoodsOutNotesSection: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        if note.channel.hasCustomerShippingLabel {
            GoodsOutNoteCard(title: "Shipping label (from customer)", icon: .note, text: note.shippingLabelNote)
        }
        if let instructions = note.customerInstructions {
            GoodsOutNoteCard(title: "Customer instructions", icon: .lock, text: instructions)
        }
        if let warehouseNote = note.warehouseNote {
            GoodsOutNoteCard(title: "Warehouse note (private)", icon: .note, tone: .warn, text: warehouseNote)
        }
    }
}
