import SwiftUI

struct FilterChipItem<ID: Hashable>: Identifiable {
    let id: ID
    let title: String
    var dot: Color?
}

struct FilterChips<ID: Hashable>: View {
    let items: [FilterChipItem<ID>]
    @Binding var selection: ID

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(items) { item in
                    let isSelected = item.id == selection
                    Button {
                        selection = item.id
                    } label: {
                        HStack(spacing: 6) {
                            if let dot = item.dot {
                                Circle().fill(dot).frame(width: 8, height: 8)
                            }
                            Text(item.title)
                                .font(.footnote.weight(.semibold))
                        }
                        .foregroundStyle(isSelected ? .white : AikuColor.textBody)
                        .padding(.horizontal, 14)
                        .frame(height: 36)
                        .background(isSelected ? AikuColor.indigo : .white, in: Capsule())
                        .overlay(Capsule().stroke(isSelected ? .clear : AikuColor.borderStrong))
                    }
                    .accessibilityAddTraits(isSelected ? .isSelected : [])
                }
            }
            .padding(.horizontal, 16)
        }
    }
}
