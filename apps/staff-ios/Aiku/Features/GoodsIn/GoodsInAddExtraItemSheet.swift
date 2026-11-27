import SwiftUI

struct GoodsInAddExtraItemSheet: View {
    static let firstQuantity: Double = 1

    let delivery: MobileStockDelivery
    let scanned: GoodsInScannedExtra?
    let addable: GoodsInAddableChoices
    let isWorking: Bool
    let problem: String?
    let onSearch: (_ search: String?, _ orgStockID: Int?) async -> Void
    let onAdd: (_ itemID: Int, _ skoQuantity: Double) -> Void
    let onDismiss: () -> Void
    @State private var search = ""
    @State private var chosenID: Int?
    @State private var quantity = GoodsInAddExtraItemSheet.firstQuantity

    private var chosen: MobileAddableItem? {
        if let picked = addable.items.first(where: { $0.id == chosenID }) { return picked }
        guard scanned != nil, addable.items.count == 1 else { return nil }
        return addable.items.first
    }

    var body: some View {
        SheetScaffold(
            title: scanned == nil ? "Add extra item" : "Add it as an extra item?",
            subtitle: scanned.map { L10n.format("%@ is not on %@", $0.code, delivery.reference) } ?? delivery.reference
        ) {
            if scanned == nil {
                SearchField(text: $search, placeholder: "Search the supplier’s products")
                    .accessibilityIdentifier("goodsIn.addExtra.search")
            }
            choices
            if chosen != nil {
                SheetSectionLabel("How many SKOs came")
                GoodsInQuantityStepper(value: quantity, minimum: Self.firstQuantity) { quantity = $0 }
                GoodsInHelper("Added as received and counted. Place it like the other items.")
            }
        } footer: {
            GoodsInSheetProblem(problem: problem)
            if let chosen {
                AikuButton(L10n.format("Add %lld SKOs of %@", Int(quantity.rounded()), chosen.skoCode), icon: .plus, isLoading: isWorking) {
                    onAdd(chosen.id, quantity)
                }
                .accessibilityIdentifier("goodsIn.addExtra.add")
            }
            AikuButton("Cancel", kind: .tertiary, action: onDismiss)
        }
        .task(id: search) {
            if scanned == nil, !search.isEmpty {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await load()
        }
    }

    @ViewBuilder
    private var choices: some View {
        if let message = addable.problem {
            GoodsOutLoadProblem(title: "Couldn’t load the products you can add", message: message) {
                Task { await load() }
            }
        } else if addable.isLoading, addable.items.isEmpty {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 44)
                .accessibilityLabel(L10n.text("Loading"))
        } else if addable.items.isEmpty {
            GoodsInHelper(scanned == nil ? "No product to add. Try another search." : "This SKO is not a product of this supplier, so it can’t be added here.")
        } else {
            VStack(spacing: 8) {
                ForEach(addable.items) { option in
                    GoodsInChoiceRow(
                        title: option.codes.joined(separator: GoodsInText.separator),
                        subtitle: option.displayName,
                        isSelected: option.id == chosen?.id,
                        identifier: "goodsIn.addExtra.option.\(option.id)"
                    ) {
                        chosenID = option.id
                    }
                }
            }
        }
    }

    private func load() async {
        await onSearch(scanned == nil ? search : nil, scanned?.skoID)
    }
}
