import SwiftUI

struct SystemConflictResolveView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store = SystemSyncStore.shared
    let reference: String

    var body: some View {
        if let conflict = store.conflict(reference: reference) {
            content(conflict)
        } else {
            AppScreen(title: "Resolve conflict", kicker: "Offline & sync") {
                EmptyState(icon: .checkDouble, title: "Nothing to resolve", message: "There are no conflicts on this phone.")
            }
        }
    }

    private func content(_ conflict: SystemConflict) -> some View {
        AppScreen(
            title: conflict.headerTitle,
            kicker: "Conflict \(store.position(of: conflict.reference)) of \(store.conflicts.count) · \(conflict.kind)"
        ) {
            if conflict.isResolved {
                InlineAlert(tone: .success, title: "Resolved", message: "\(conflict.choiceSummary). Uploaded to Aiku.")
            } else if conflict.isDiscarded {
                InlineAlert(tone: .info, title: "Discarded", message: "Your version stays in the sync history.")
            } else {
                HStack(spacing: 8) {
                    AikuButton("Use mine for all", kind: .secondary) {
                        store.chooseAll(.mine, in: conflict.reference)
                    }
                    .accessibilityIdentifier("system.useMineForAll")
                    AikuButton("Use Aiku for all", kind: .tertiary) {
                        store.chooseAll(.aiku, in: conflict.reference)
                    }
                    .accessibilityIdentifier("system.useAikuForAll")
                }
            }
            SectionHeading("Lines that differ", detail: "\(conflict.lines.count) of \(conflict.totalLines) lines")
            ForEach(conflict.lines) { line in
                SystemConflictLineCard(line: line, isEditable: conflict.isOpen) { choice in
                    store.choose(choice, line: line.id, in: conflict.reference)
                }
            }
            if !conflict.sameLines.isEmpty {
                SectionHeading("Same on both sides")
                CardList(conflict.sameLines) { line in
                    ListRow(line.sko, subtitle: "No conflict", chips: [line.chip]) {
                        ProductThumb(size: 48, label: "Product photo of \(line.sko)")
                    } trailing: {
                        EmptyView()
                    }
                }
            }
            HelperText("Each differing line offers Mine or Aiku; quantities can also take Both (added up, never above what the line needs). Your choice is shown filled.")
        } footer: {
            if conflict.isOpen {
                AikuButton(applyTitle(conflict), icon: conflict.canApply ? .check : nil) {
                    apply(conflict)
                }
                .disabled(!conflict.canApply)
                .accessibilityIdentifier("system.applyConflict")
            }
        }
    }

    private func applyTitle(_ conflict: SystemConflict) -> String {
        if conflict.canApply {
            return "Apply · \(conflict.choiceSummary)"
        }
        return conflict.undecidedCount == 1 ? "Choose for 1 more line" : "Choose for \(conflict.undecidedCount) more lines"
    }

    private func apply(_ conflict: SystemConflict) {
        guard store.apply(conflict.reference, at: SystemSyncStore.clockTime()) else { return }
        toasts.show("\(conflict.headerTitle) resolved · \(conflict.choiceSummary)")
        router.back()
    }
}

struct SystemConflictLineCard: View {
    let line: SystemConflictLine
    var isEditable = true
    let onChoose: (SystemConflictChoice) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                ProductThumb(size: 44, label: "Product photo of \(line.sko)")
                VStack(alignment: .leading, spacing: 2) {
                    Text(line.sko)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(line.subtitle)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            HStack(alignment: .top, spacing: 8) {
                side(title: "Yours", lines: [line.mineSummary], meta: nil, foreground: AikuColor.ink, background: AikuColor.soft)
                side(title: "Now in Aiku", lines: [line.aikuSummary], meta: line.aikuBy, foreground: AikuColor.text2, background: AikuColor.bg)
            }
            HStack(spacing: 6) {
                ForEach(line.choices, id: \.self) { choice in
                    choiceButton(choice)
                }
            }
            .accessibilityElement(children: .contain)
            .accessibilityLabel("Choose for \(line.sko)")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(line.choice == nil ? AikuColor.warnBd : AikuColor.border))
    }

    private func side(title: String, lines: [String], meta: String?, foreground: Color, background: Color) -> some View {
        VStack(alignment: .leading, spacing: 1) {
            Text(title).font(.aiku(12, .bold))
            ForEach(lines, id: \.self) { text in
                Text(text).font(.aiku(12))
            }
            if let meta {
                Text(meta)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
        }
        .foregroundStyle(foreground)
        .padding(.horizontal, 10)
        .padding(.vertical, 8)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(background, in: RoundedRectangle(cornerRadius: 8))
    }

    private func choiceButton(_ choice: SystemConflictChoice) -> some View {
        let isSelected = line.choice == choice
        return Button {
            onChoose(choice)
        } label: {
            VStack(spacing: 1) {
                Text(choice.title)
                    .font(.aiku(11, .semibold))
                    .opacity(0.85)
                Text(line.label(for: choice))
                    .font(.aiku(15, .bold))
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .foregroundStyle(isSelected ? AikuColor.onPrimary : AikuColor.text2)
            .padding(.horizontal, 4)
            .frame(maxWidth: .infinity, minHeight: 48)
            .background(isSelected ? AikuColor.primary : AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(isSelected ? Color.clear : AikuColor.borderStrong))
            .contentShape(RoundedRectangle(cornerRadius: 8))
        }
        .buttonStyle(.plain)
        .disabled(!isEditable)
        .accessibilityLabel("\(choice.title): \(line.label(for: choice))")
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityIdentifier("system.choice.\(line.sko).\(choice.rawValue)")
    }
}
