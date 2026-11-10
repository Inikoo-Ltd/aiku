import SwiftUI

struct SystemLoadingDashboardView: View {
    @Environment(Session.self) private var session

    var body: some View {
        ScreenScaffold(showsTabBar: false, scrolls: false) {
            EmptyView()
        } content: {
            ScrollView {
                VStack(spacing: 0) {
                    header
                    VStack(spacing: 16) {
                        Card(padding: 16, spacing: 12) {
                            HStack(spacing: 8) {
                                ForEach(0..<4, id: \.self) { _ in
                                    VStack(spacing: 8) {
                                        SkeletonBlock(width: 44, height: 44, radius: 8)
                                        SkeletonBlock(width: 54, height: 10)
                                    }
                                    .padding(.vertical, 10)
                                    .frame(maxWidth: .infinity)
                                }
                            }
                        }
                        Card(padding: 16, spacing: 12) {
                            SkeletonBlock(width: 120, height: 14)
                            StatGrid(columns: 2) {
                                ForEach(0..<4, id: \.self) { _ in
                                    VStack(alignment: .leading, spacing: 8) {
                                        SkeletonBlock(width: 90, height: 10)
                                        SkeletonBlock(width: 52, height: 22)
                                    }
                                    .padding(12)
                                    .frame(maxWidth: .infinity, alignment: .leading)
                                    .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
                                }
                            }
                        }
                        Card(padding: 16, spacing: 12) {
                            ForEach(0..<3, id: \.self) { _ in
                                HStack(spacing: 12) {
                                    SkeletonBlock(width: 40, height: 40, radius: 8)
                                    VStack(alignment: .leading, spacing: 8) {
                                        SkeletonBlock(width: 170, height: 12)
                                        SkeletonBlock(width: 110, height: 10)
                                    }
                                    Spacer(minLength: 0)
                                }
                            }
                        }
                        Text("Loading \(session.profile.warehouseName)…")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                    .padding(.horizontal, 16)
                    .padding(.top, -52)
                    .padding(.bottom, 24)
                }
            }
            .scrollDisabled(true)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Loading \(session.profile.warehouseName)")
        } footer: {
            EmptyView()
        }
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 18) {
            HStack(spacing: 12) {
                Circle()
                    .fill(Color.white.opacity(0.18))
                    .frame(width: 44, height: 44)
                VStack(alignment: .leading, spacing: 6) {
                    RoundedRectangle(cornerRadius: 6).fill(Color.white.opacity(0.18)).frame(width: 90, height: 10)
                    RoundedRectangle(cornerRadius: 6).fill(Color.white.opacity(0.24)).frame(width: 140, height: 14)
                }
                Spacer(minLength: 0)
            }
            RoundedRectangle(cornerRadius: 8)
                .fill(Color.white.opacity(0.14))
                .frame(width: 200, height: 44)
        }
        .padding(.horizontal, 20)
        .padding(.top, 16)
        .padding(.bottom, 76)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.nav.ignoresSafeArea(edges: .top))
    }
}
