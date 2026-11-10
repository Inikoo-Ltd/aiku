import SwiftUI

struct GoodsOutDetailRow: Identifiable, Hashable {
    let icon: AppIcon
    let text: String

    var id: String { "\(icon.rawValue)-\(text)" }
}

struct GoodsOutDetailCard: View {
    let title: String
    let rows: [GoodsOutDetailRow]
    var linkTitle: String?
    var onLink: (() -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            ForEach(rows) { row in
                HStack(spacing: 10) {
                    row.icon.image
                        .font(.system(size: 14))
                        .foregroundStyle(AikuColor.text3)
                        .frame(width: 18)
                        .accessibilityHidden(true)
                    Text(row.text)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                        .fixedSize(horizontal: false, vertical: true)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
                .frame(minHeight: 28)
            }
            if let linkTitle, let onLink {
                LinkButton(linkTitle, size: 13, action: onLink)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

struct GoodsOutOrderCards: View {
    let note: GoodsOutDeliveryNote
    var onEditAddress: (() -> Void)?

    var body: some View {
        GoodsOutDetailCard(title: "Order", rows: [
            GoodsOutDetailRow(icon: .order, text: "\(note.reference) · order"),
            GoodsOutDetailRow(icon: .person, text: "\(note.customer) (\(note.customerNumber))"),
            GoodsOutDetailRow(icon: .person, text: note.contactName),
            GoodsOutDetailRow(icon: .email, text: note.email),
            GoodsOutDetailRow(icon: .phone, text: note.phone)
        ])
        GoodsOutDetailCard(
            title: "Shipping",
            rows: [GoodsOutDetailRow(icon: .locations, text: note.address)],
            linkTitle: onEditAddress == nil ? nil : "Edit delivery address",
            onLink: onEditAddress
        )
        GoodsOutDetailCard(title: "Delivery note", rows: [
            GoodsOutDetailRow(icon: .sko, text: note.itemsSummary),
            GoodsOutDetailRow(icon: .history, text: note.estimate),
            GoodsOutDetailRow(icon: .weight, text: note.weightText)
        ])
    }
}
