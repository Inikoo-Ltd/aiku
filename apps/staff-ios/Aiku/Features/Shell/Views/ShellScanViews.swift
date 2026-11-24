import SwiftUI

struct ShellScanResultContent: View {
    @Environment(AppRouter.self) private var router
    let target: ShellScanTarget

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 10) {
                AppIcon.check.image
                    .font(.system(size: 18, weight: .semibold))
                    .accessibilityHidden(true)
                Text.emphasising(target.code, in: target.kind.scanned(target.code))
                    .font(.aiku(14))
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .foregroundStyle(AikuColor.success)
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(AikuColor.successSoft, in: RoundedRectangle(cornerRadius: 8))
            .accessibilityElement(children: .combine)
            Card(padding: 16, spacing: 12) {
                HStack(spacing: 12) {
                    IconTile(target.kind.icon, tone: .info, size: 48, radius: 8)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(target.code)
                            .font(.aiku(20, .bold))
                            .foregroundStyle(AikuColor.text)
                            .lineLimit(1)
                            .minimumScaleFactor(0.7)
                        Text(target.detail)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                if let link = target.link {
                    AikuButton(target.kind.openTitle, size: .regular) {
                        router.shellFollow(link)
                    }
                    .accessibilityIdentifier("shell.scan.open")
                }
            }
            .accessibilityElement(children: .contain)
            .accessibilityLabel(target.kind.found)
            if target.kind == .sko {
                SectionHeading("Locations", detail: "\(target.rows.count)")
                if target.rows.isEmpty {
                    EmptyState(icon: .locations, title: "Not in any location", message: "This SKO has no stock in a location of this warehouse.")
                } else {
                    CardList(target.rows) { row in
                        ListRow(row.title, subtitle: row.detail, icon: .locations, iconTone: .info) {
                            router.shellFollow(row.link)
                        }
                    }
                }
            }
        }
    }
}

struct ShellScanCapabilities: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("What a scan can open")
                .font(.aiku(14, .bold))
                .foregroundStyle(AikuColor.text2)
                .padding(.horizontal, 4)
                .accessibilityAddTraits(.isHeader)
            Card {
                ForEach(Array(ShellScanCatalog.capabilities.enumerated()), id: \.offset) { index, capability in
                    if index > 0 {
                        RowDivider()
                    }
                    HStack(spacing: 12) {
                        capability.icon.image
                            .font(.system(size: 17))
                            .foregroundStyle(AikuColor.text2)
                            .accessibilityHidden(true)
                        Text(L10n.text(capability.text))
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text)
                            .frame(maxWidth: .infinity, alignment: .leading)
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
        }
    }
}

struct ShellScanNotFoundCard: View {
    let code: String
    let onTypeCode: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .top, spacing: 10) {
                AppIcon.question.image
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.dangerInk)
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text("Barcode not found")
                        .font(.aiku(15, .bold))
                    Text("\(code) is not linked to a SKO, location or delivery note")
                        .font(.aiku(13))
                }
                .foregroundStyle(AikuColor.dangerInk)
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            AikuButton("Type code", kind: .tertiary, size: .small, fullWidth: false, action: onTypeCode)
                .accessibilityIdentifier("shell.scan.typeCode")
        }
        .padding(14)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.dangerBd))
        .accessibilityElement(children: .contain)
    }
}

struct ShellScanOutcomeContent: View {
    let lookup: ShellScanLookup
    let onTypeCode: () -> Void
    let onRetry: () -> Void
    let onChooseWarehouse: () -> Void
    @Environment(\.signOut) private var signOut

    var body: some View {
        if let code = lookup.lookingUpCode {
            Card(padding: 16) {
                HStack(spacing: 10) {
                    ProgressView()
                    Text("Looking up \(code)…")
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                    Spacer(minLength: 0)
                }
            }
            .accessibilityElement(children: .combine)
        } else {
            switch lookup.outcome {
            case .found(let target):
                ShellScanResultContent(target: target)
            case .notFound(let code):
                ShellScanNotFoundCard(code: code, onTypeCode: onTypeCode)
            case .failed(let code, let message):
                InlineAlert(title: L10n.format("Couldn’t look up %@", code), message: message) {
                    LinkButton("Try again", action: onRetry)
                }
            case .needsWarehouse:
                ShellScanNeedsWarehouse(onChoose: onChooseWarehouse)
            case .sessionExpired:
                InlineAlert(tone: .warn, title: "Your session has ended", message: "Sign in again to keep scanning.") {
                    LinkButton("Sign in again") { signOut() }
                }
            case nil:
                EmptyView()
            }
        }
    }
}

struct ShellScanNeedsWarehouse: View {
    let onChoose: () -> Void
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        InlineAlert(tone: .warn, title: "Choose a warehouse to scan", message: message) {
            LinkButton("Choose warehouse", action: onChoose)
        }
    }

    private var message: String {
        if let loadError = warehouseStore.loadError {
            return L10n.format("A scan looks the code up in your warehouse, and the warehouses didn’t load: %@", loadError)
        }
        return "A scan looks the code up in the warehouse you work in."
    }
}

struct ShellScanResultView: View {
    let code: String
    @State private var lookup = ShellScanLookup.live(.shared)
    @State private var warehouseStore = WarehouseStore.shared
    @State private var isChoosingWarehouse = false
    @Environment(AppRouter.self) private var router

    var body: some View {
        AppScreen(title: "Result", kicker: "Scan", spacing: 16) {
            ShellScanOutcomeContent(lookup: lookup) {
                router.open(ShellRoute.search)
            } onRetry: {
                Task { await lookup.lookUp(code, warehouseID: warehouseStore.selectedID) }
            } onChooseWarehouse: {
                isChoosingWarehouse = true
            }
            ShellScanCapabilities()
        }
        .task(id: warehouseStore.selectedID) {
            await lookup.lookUp(code, warehouseID: warehouseStore.selectedID)
        }
        .shellWarehouseSheet(isPresented: $isChoosingWarehouse)
    }
}
