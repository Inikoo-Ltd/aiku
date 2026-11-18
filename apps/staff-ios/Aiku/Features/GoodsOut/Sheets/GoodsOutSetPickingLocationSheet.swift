import SwiftUI

struct GoodsOutSetPickingLocationSheet: View {
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void

    var body: some View {
        if note.isRemote {
            GoodsOutRemotePickingLocationSheet(note: note, line: line, present: present)
        } else {
            GoodsOutSamplePickingLocationSheet(note: note, line: line, present: present)
        }
    }
}

private struct GoodsOutPickingLocationIntro: View {
    let sko: String

    var body: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 56, label: "Product photo of \(sko)")
            Text("Pickers are sent to the active picking location of the order’s channel.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
        }
    }
}

private struct GoodsOutRemotePickingLocationSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared
    @State private var locations: GoodsOutPickingLocations?
    @State private var loadProblem: String?
    @State private var saveProblem: String?
    @State private var chosen = GoodsOutPickingDefaults()
    @State private var isSaving = false

    var body: some View {
        SheetScaffold(title: "Picking location · \(locations?.code ?? line.sko)", subtitle: "\(locations?.name ?? line.name) · one per channel") {
            GoodsOutPickingLocationIntro(sko: locations?.code ?? line.sko)
            if let saveProblem {
                InlineAlert(tone: .danger, title: "Picking locations not saved", message: saveProblem)
            }
            choices
            HelperText("Same as Stocks management on the web. Only users who can edit stock can change it.")
        } footer: {
            if let locations, locations.canEdit, !locations.options.isEmpty {
                AikuButton("Save picking locations", icon: .check, isLoading: isSaving) {
                    save(locations)
                }
                .accessibilityIdentifier("goodsOut.savePickingLocations")
            }
            AikuButton("Cancel", kind: .tertiary) {
                present(.pickLocation(line: line.id))
            }
            .disabled(isSaving)
        }
        .task { await load() }
    }

    @ViewBuilder
    private var choices: some View {
        if let locations {
            if locations.options.isEmpty {
                HelperText("This SKO has no location in this warehouse.")
            } else {
                ForEach(GoodsOutPickingChannel.allCases, id: \.self) { channel in
                    channelChoices(channel, in: locations)
                }
            }
        } else if let loadProblem {
            GoodsOutLoadProblem(title: "Couldn’t load the SKO’s locations", message: loadProblem) {
                Task { await load() }
            }
        } else {
            GoodsOutSkeletonCard(rows: 2)
        }
    }

    @ViewBuilder
    private func channelChoices(_ channel: GoodsOutPickingChannel, in locations: GoodsOutPickingLocations) -> some View {
        let selected = locations.selection(for: channel, chosen: chosen)
        SheetSectionLabel(channel.title)
        ForEach(locations.visibleOptions(for: channel, chosen: chosen)) { option in
            RadioCard(option.code, subtitle: option.stockText, isSelected: option.linkID == selected) {
                if locations.canEdit && !isSaving {
                    chosen[channel] = option.linkID
                }
            }
            .accessibilityIdentifier("goodsOut.pickingLocation.\(channel.rawValue).\(option.code)")
        }
    }

    private func load() async {
        loadProblem = nil
        switch await store.pickingLocations(for: line) {
        case .success(let loaded):
            locations = loaded
        case .failure(let problem):
            if !problem.message.isEmpty {
                loadProblem = problem.message
            }
        }
    }

    private func save(_ locations: GoodsOutPickingLocations) {
        guard !isSaving else { return }
        guard !chosen.isEmpty else {
            present(.pickLocation(line: line.id))
            return
        }
        isSaving = true
        saveProblem = nil
        let choice = chosen
        Task {
            let outcome = await store.savePickingLocations(locations, chosen: choice, on: note.reference)
            isSaving = false
            switch outcome {
            case .unchanged:
                present(.pickLocation(line: line.id))
            case .saved:
                toasts.show("Picking locations saved for \(locations.code)")
                present(.pickLocation(line: line.id))
            case .failed(let message):
                if !message.isEmpty {
                    saveProblem = message
                }
            }
        }
    }
}

private struct GoodsOutSamplePickingLocationSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let line: GoodsOutLine
    let present: (GoodsOutNoteSheet) -> Void
    private let store = GoodsOutStore.shared
    @State private var sections: [GoodsOutPickingLocationSection]

    init(note: GoodsOutDeliveryNote, line: GoodsOutLine, present: @escaping (GoodsOutNoteSheet) -> Void) {
        self.note = note
        self.line = line
        self.present = present
        _sections = State(initialValue: GoodsOutSampleData.pickingLocationSections(for: line, channel: note.channel))
    }

    var body: some View {
        SheetScaffold(title: "Picking location · \(line.sko)", subtitle: "\(line.name) · one per channel") {
            GoodsOutPickingLocationIntro(sko: line.sko)
            ForEach($sections) { $section in
                SheetSectionLabel(section.channel.title)
                ForEach(section.options) { option in
                    RadioCard(option.code, subtitle: option.stockText, isSelected: section.selected == option.code) {
                        section.selected = option.code
                    }
                }
            }
            HelperText("Same as Stocks management on the web. Only users who can edit stock can change it.")
        } footer: {
            AikuButton("Save picking locations", icon: .check) {
                if let own = sections.first(where: { $0.channel == note.channel }) {
                    store.setPickingLocation(note.reference, line: line.id, location: own.selected)
                }
                toasts.show("Picking locations saved for \(line.sko)")
                present(.pickLocation(line: line.id))
            }
            .disabled(!store.rights.canEditStock)
            .accessibilityIdentifier("goodsOut.savePickingLocations")
            AikuButton("Cancel", kind: .tertiary) {
                present(.pickLocation(line: line.id))
            }
        }
    }
}
