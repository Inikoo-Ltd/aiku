---
paths:
  - 'app/Actions/Goods/ProductDevelopment/**'
---

# Product Development

## A trial run is made on its stage's artefact: use workingArtefact(), never item->artefact
HELP-4800 (18 Nov 2026): a formulation stage typed base_blend or intermediate has its own artefact (code SKU-S{position}, made lazily by ProductDevelopmentRecipes::stageArtefact or CreateProductDevelopmentFormulationArtefact); only the finished_product stage uses the SKU's artefact. Each sample stores the artefact it was made on (product_development_samples.artefact_id); read the recipe, job lines and trial run form through $sample->workingArtefact(), never $sample->item->artefact. The final formulation is the approved run of the finished_product stage (finalOf/finishedFormulation), never the last by position; at most one finished stage, and it is always the last (new blends are inserted before it). An approved blend becomes an intermediate raw material via SetArtefactAsMix.
