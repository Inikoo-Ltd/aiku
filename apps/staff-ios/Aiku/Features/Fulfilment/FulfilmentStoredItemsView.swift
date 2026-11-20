import SwiftUI

struct FulfilmentStoredItemsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(\.apiClient) private var apiClient
    @State private var feed = StoredItemsFeed()
    @State private var warehouseStore = WarehouseStore.shared
    @State private var opened: MobileStoredItemRow?

    @State private var appearance = 0

    private var warehouseID: Int? { warehouseStore.selected?.id }

    var body: some View {
        @Bindable var feed = feed
        AppScreen(title: "Stored items", kicker: "Inventory · \(session.warehouseLabel)") {
            ModuleTabs(.inventory, current: .storedItems)
        } content: {
            FilterChipBar(options: StoredItemStateFilter.allCases, selection: $feed.state, title: \.title) { state in
                state.count(in: feed.counts)
            }
            SearchField(text: $feed.search, placeholder: "Search reference, barcode or name")
            if let message = feed.loadError {
                InlineAlert(title: "Couldn’t load the stored items", message: message)
            }
            rows
            FulfilmentNote("Stored items are the customer’s own SKOs, counted per pallet. The barcode is usually the manufacturer’s EAN.")
        }
        .onAppear { appearance += 1 }
        .task(id: ListLoadKey(request: warehouseID.map { feed.request(warehouseID: $0) }, appearance: appearance)) {
            guard let warehouseID else { return }
            if !feed.search.isEmpty {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
        .screenRefreshable {
            guard let warehouseID else { return }
            await feed.reload(warehouseID: warehouseID, apiClient: apiClient)
        }
        .aikuSheet(item: $opened) { row in
            if let warehouseID {
                StoredItemSheet(row: row, warehouseID: warehouseID) { pallet in
                    opened = nil
                    router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference))
                } onClose: {
                    opened = nil
                }
            }
        }
    }

    @ViewBuilder
    private var rows: some View {
        let search = feed.search.trimmingCharacters(in: .whitespacesAndNewlines)
        let isCurrent = warehouseID.map { feed.loadedRequest == feed.request(warehouseID: $0) } ?? false
        if !feed.rows.isEmpty, isCurrent {
            CardList(feed.rows) { row in
                ListRow(
                    row.reference,
                    subtitle: PalletDeliveryText.joined([row.customer?.name, row.name, PalletDeliveryText.plural(row.numberPallets, "pallet", "pallets")]),
                    chips: row.stateLabel.map { [Chip($0, FulfilmentStorageText.storedItemTone(row.state))] } ?? [],
                    action: { opened = row }
                ) {
                    EmptyView()
                } trailing: {
                    Text(row.quantity.grouped)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                        .monospacedDigit()
                }
                .accessibilityIdentifier("fulfilment.storedItem.\(row.reference)")
            }
            if feed.hasMorePages {
                LocationsLoadMoreRow(isLoading: feed.isLoadingMore, page: feed.currentPage) {
                    await feed.loadNextPage(apiClient: apiClient)
                }
            }
        } else if isCurrent, feed.loadError == nil {
            EmptyState(icon: .sko, title: search.isEmpty ? "No stored items here." : "No stored item matches “\(search)”.")
        } else if feed.loadError == nil {
            ProgressView()
                .frame(maxWidth: .infinity, minHeight: 88)
                .accessibilityLabel("Loading stored items")
        }
    }
}

private struct StoredItemSheet: View {
    let row: MobileStoredItemRow
    let warehouseID: Int
    let onOpenPallet: (MobileStoredItemRow.PalletLine.Pallet) -> Void
    let onClose: () -> Void
    @Environment(\.apiClient) private var apiClient
    @State private var detail: MobileStoredItemRow?
    @State private var problem: String?

    var body: some View {
        let shown = detail ?? row
        SheetScaffold(title: row.reference, subtitle: shown.name) {
            VStack(alignment: .leading, spacing: 8) {
                KeyValueRow("State", shown.stateLabel ?? shown.state ?? "—")
                if let customer = shown.customer?.name {
                    KeyValueRow("Customer", customer)
                }
                if let barcode = shown.barcode, !barcode.isEmpty {
                    KeyValueRow("Barcode", barcode)
                }
                KeyValueRow("In this warehouse", shown.quantity.grouped)
            }
            if let detail {
                let pallets = detail.pallets ?? []
                SheetSectionLabel("Pallets · \(PalletDeliveryText.plural(pallets.count, "pallet", "pallets"))")
                if pallets.isEmpty {
                    HelperText("No pallet in this warehouse holds it now.")
                } else {
                    CardList(pallets.map(StoredItemPalletItem.init)) { item in
                        ListRow(
                            item.line.pallet.reference,
                            subtitle: PalletDeliveryText.joined([item.line.location?.code ?? "no location", item.line.pallet.customerReference]),
                            chips: item.line.pallet.stateLabel.map { [Chip($0, FulfilmentStorageText.palletStateTone(item.line.pallet.state))] } ?? [],
                            action: { onOpenPallet(item.line.pallet) }
                        ) {
                            EmptyView()
                        } trailing: {
                            Text(item.line.quantity.grouped)
                                .font(.aiku(15, .bold))
                                .foregroundStyle(AikuColor.text)
                                .monospacedDigit()
                        }
                    }
                }
            } else if let problem {
                InlineAlert(title: "Couldn’t load \(row.reference)", message: problem)
            } else {
                SkeletonRow()
                SkeletonRow()
            }
        } footer: {
            AikuButton("Close", kind: .tertiary, action: onClose)
        }
        .task {
            do {
                detail = try await apiClient.fetchStoredItem(warehouseID: warehouseID, id: row.id)
            } catch where error.isCancellation {
                return
            } catch {
                problem = PalletDeliveryText.message(for: error)
            }
        }
    }
}

private struct StoredItemPalletItem: Identifiable {
    let line: MobileStoredItemRow.PalletLine
    var id: Int { line.pallet.id }
}
