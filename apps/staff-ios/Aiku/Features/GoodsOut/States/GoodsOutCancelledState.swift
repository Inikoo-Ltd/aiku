import SwiftUI

struct GoodsOutCancelledStateView: View {
    @Environment(AppRouter.self) private var router
    let note: GoodsOutDeliveryNote

    var body: some View {
        VStack(spacing: 10) {
            Text("This delivery note was cancelled")
                .font(.aiku(20, .bold))
                .foregroundStyle(AikuColor.dangerInk)
            Text(stopText)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
        }
        .multilineTextAlignment(.center)
        .padding(.horizontal, 16)
        .padding(.vertical, 24)
        .frame(maxWidth: .infinity)
        .background(AikuColor.dangerSoft, in: RoundedRectangle(cornerRadius: 12))
        .accessibilityElement(children: .combine)
        .accessibilityAddTraits(.isHeader)
        let picked = note.pickedLines
        if !picked.isEmpty {
            GoodsOutLinesCard(lines: picked, showsChip: false, detail: { "Picked from \($0.pickLocation)" })
        }
        HelperText("If the order is replaced, these picks can be reused on the new delivery note. Cancelling needs a dispatching supervisor, an org admin, or order/CRM edit rights.")
        AikuButton("Back to delivery notes") {
            router.back()
        }
        .accessibilityIdentifier("goodsOut.backToList")
    }

    private var stopText: String {
        let units = note.pickedUnits
        guard units > 0 else { return "Stop picking. Nothing was picked yet." }
        let skos = note.pickedLines.count
        return "Stop picking. \(units) \(units == 1 ? "unit is" : "units are") already picked (\(skos) \(skos == 1 ? "SKO" : "SKOs"))."
    }
}
