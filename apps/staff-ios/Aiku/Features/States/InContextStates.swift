import SwiftUI

struct EmptySearchResults: View {
    let query: String
    let suggestions: [String]
    let onSuggestion: (String) -> Void
    let onScan: () -> Void

    var body: some View {
        VStack(spacing: 20) {
            StateMessage(
                systemImage: "magnifyingglass",
                title: "No results for “\(query)”",
                message: "Try a SKU reference (SKU-00412), a location code (A-03-02) or a shorter word."
            )
            HStack(spacing: 8) {
                ForEach(suggestions, id: \.self) { suggestion in
                    Button {
                        onSuggestion(suggestion)
                    } label: {
                        Text(suggestion)
                            .font(.footnote.weight(.semibold))
                            .foregroundStyle(AikuColor.textBody)
                            .padding(.horizontal, 14)
                            .frame(height: 36)
                            .background(AikuColor.surface, in: Capsule())
                            .overlay(Capsule().stroke(AikuColor.borderStrong))
                    }
                    .accessibilityLabel("Search \(suggestion)")
                }
            }
            ActionButton(title: "Scan instead", systemImage: AppIcon.scan, kind: .secondary, expands: false, action: onScan)
        }
        .padding(.vertical, 40)
    }
}

struct EmptyTasksScreen: View {
    let warehouse: String
    let onPickFromQueue: () -> Void
    let onBack: () -> Void

    var body: some View {
        StateScreen(
            headerTitle: "Goods out",
            headerEyebrow: warehouse,
            systemImage: "checkmark",
            tone: .success,
            title: "All caught up",
            message: "No delivery notes are waiting for you. New ones appear here as soon as they are assigned.",
            actions: [
                StateAction(title: "Pick from the queue", perform: onPickFromQueue),
                StateAction(title: "Back to dashboard", perform: onBack),
            ]
        )
    }
}

struct DashboardLoadingView: View {
    let warehouseName: String

    var body: some View {
        VStack(spacing: 0) {
            VStack(alignment: .leading, spacing: 14) {
                HStack(spacing: 12) {
                    Circle().fill(.white.opacity(0.18)).frame(width: 52, height: 52)
                    VStack(alignment: .leading, spacing: 8) {
                        Capsule().fill(.white.opacity(0.18)).frame(width: 110, height: 10)
                        Capsule().fill(.white.opacity(0.18)).frame(width: 160, height: 16)
                    }
                    Spacer()
                }
                RoundedRectangle(cornerRadius: 10).fill(.white.opacity(0.12)).frame(height: 52)
            }
            .padding(.horizontal, 16)
            .padding(.top, 8)
            .padding(.bottom, 16)
            .background(AikuColor.indigo.ignoresSafeArea(edges: .top))
            .accessibilityHidden(true)

            ScrollView {
                VStack(spacing: 16) {
                    HStack(spacing: 12) {
                        ProgressView()
                        Text("Loading \(warehouseName)…")
                            .font(.subheadline)
                            .foregroundStyle(AikuColor.textSecondary)
                    }
                    .padding(.top, 4)
                    HStack(spacing: 12) {
                        ForEach(0..<4, id: \.self) { _ in
                            VStack(spacing: 8) {
                                SkeletonBlock(width: 52, height: 52, cornerRadius: 12)
                                SkeletonBlock(width: 56, height: 10)
                            }
                            .frame(maxWidth: .infinity)
                        }
                    }
                    .padding(16)
                    .card()
                    .loadingAccessibility()
                    SkeletonStatCard()
                    SkeletonStatCard()
                    SkeletonListCard()
                }
                .padding(16)
            }
        }
        .aikuScreen()
    }
}

struct StockMoveFailure {
    let sku: String
    let fromLocation: String
    let availableInLocation: Int
    let toLocation: String
    let requestedQuantity: Int
}

struct StockMoveFailedSheet: View {
    let failure: StockMoveFailure
    let onMove: (Int) -> Void

    @State private var quantity: Int

    init(failure: StockMoveFailure, onMove: @escaping (Int) -> Void) {
        self.failure = failure
        self.onMove = onMove
        _quantity = State(initialValue: failure.requestedQuantity)
    }

    private var exceedsStock: Bool {
        quantity > failure.availableInLocation
    }

    var body: some View {
        BottomSheet(
            title: "Location transfer",
            systemImage: "arrow.left.arrow.right",
            context: failure.sku,
            primaryTitle: "Move \(min(quantity, failure.availableInLocation)) units",
            primaryAction: { onMove(min(quantity, failure.availableInLocation)) }
        ) {
            InlineAlert(
                kind: .danger,
                title: "Couldn’t move stock",
                message: "\(failure.fromLocation) has only \(failure.availableInLocation) units. Someone picked from it a moment ago."
            )
            HStack(alignment: .top, spacing: 12) {
                locationColumn(label: "From", code: failure.fromLocation, detail: "\(failure.availableInLocation) in location now")
                locationColumn(label: "To", code: failure.toLocation, detail: nil)
            }
            VStack(alignment: .leading, spacing: 6) {
                Text("Quantity")
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(AikuColor.textBody)
                QuantityStepper(value: $quantity, range: 1...9999, label: "quantity")
                if exceedsStock {
                    Label("Max \(failure.availableInLocation) — lower the quantity or choose “Move all”.", systemImage: "exclamationmark.circle")
                        .font(.footnote)
                        .foregroundStyle(AikuColor.danger)
                }
            }
        }
    }

    private func locationColumn(label: String, code: String, detail: String?) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label)
                .font(.caption)
                .foregroundStyle(AikuColor.textMuted)
            Text(code)
                .font(.headline)
                .foregroundStyle(AikuColor.textPrimary)
            if let detail {
                Text(detail)
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12)
        .card(cornerRadius: 8)
    }
}

struct ConflictChange: Identifiable {
    let id = UUID()
    let title: String
    let detail: String
}

struct EditConflictScreen: View {
    let reference: String
    let editor: String
    let summary: String
    let changes: [ConflictChange]
    let onReload: () -> Void
    let onBackToList: () -> Void

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: reference, eyebrow: "Delivery Note", trailing: .none)
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    InlineAlert(
                        kind: .warning,
                        title: "This delivery note changed",
                        message: "\(editor) \(summary) Reload to see the latest version before continuing.",
                        actionTitle: "Reload now",
                        action: onReload
                    )
                    Text("WHAT CHANGED")
                        .font(.caption.weight(.semibold))
                        .tracking(0.6)
                        .foregroundStyle(AikuColor.textMuted)
                        .padding(.horizontal, 4)
                    VStack(spacing: 0) {
                        ForEach(changes) { change in
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(change.title)
                                        .font(.subheadline.weight(.semibold))
                                        .foregroundStyle(AikuColor.textPrimary)
                                    Text(change.detail)
                                        .font(.caption)
                                        .foregroundStyle(AikuColor.textSecondary)
                                }
                                Spacer()
                                StatusBadge(text: "Updated", tone: .warning)
                            }
                            .padding(14)
                            if change.id != changes.last?.id {
                                RowDivider()
                            }
                        }
                    }
                    .card()
                }
                .padding(16)
            }
            VStack(spacing: 10) {
                ActionButton(title: "Reload delivery note", systemImage: "arrow.clockwise", action: onReload)
                ActionButton(title: "Back to list", kind: .outline, action: onBackToList)
            }
            .padding(16)
        }
        .aikuScreen()
    }
}
