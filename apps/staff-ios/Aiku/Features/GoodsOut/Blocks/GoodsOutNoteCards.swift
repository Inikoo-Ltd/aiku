import SwiftUI

struct GoodsOutNotesSection: View {
    let note: GoodsOutDeliveryNote

    var body: some View {
        if note.channel.hasCustomerShippingLabel {
            NoteCard(title: "Shipping label (from customer)", icon: .note, kind: .shippingLabel, text: note.shippingLabelNote)
        }
        if let instructions = note.customerInstructions {
            NoteCard(title: "Customer instructions", icon: .lock, kind: .customer, text: instructions)
        }
        if let warehouseNote = note.warehouseNote {
            NoteCard(title: "Warehouse note (private)", icon: .note, kind: .warehouse, text: warehouseNote)
        }
    }
}
