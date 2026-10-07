/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import { library } from "@fortawesome/fontawesome-svg-core"
import { faBug, faLightbulb, faLevelUp, faTasks, faVial, faBooks, faDatabase, faSearch, faQuestionCircle, faHistory } from "@fal"

library.add(faBug, faLightbulb, faLevelUp, faTasks, faVial, faBooks, faDatabase, faSearch, faQuestionCircle, faHistory)

export const ticketKindIcons: Record<string, string> = {
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    escalation: "fal fa-level-up",
    task: "fal fa-tasks",
    qa: "fal fa-vial",
    documentation: "fal fa-books",
    data_integrity: "fal fa-database",
    support: "fal fa-search",
    aurora: "fal fa-history",
}

export const ticketKindIcon = (kind: string | null | undefined) => (kind && ticketKindIcons[kind]) || "fal fa-question-circle"
