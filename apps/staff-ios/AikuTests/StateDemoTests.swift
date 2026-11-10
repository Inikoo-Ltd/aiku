import Testing
@testable import Aiku

struct StateDemoTests {
    @Test func coversEveryStateFromTheDesignInOrder() {
        let codes = StateDemo.allCases.map { String($0.title.prefix(3)) }

        #expect(codes == (1...11).map { String(format: "S%02d", $0) })
    }

    @Test @MainActor func stateDemoRoutesOpenOnThePath() {
        let router = AppRouter()

        router.open(.stateDemo(.editConflict))

        #expect(router.path == [.stateDemo(.editConflict)])
    }
}
