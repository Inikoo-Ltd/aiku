---
paths:
  - 'app/Actions/Goods/ProductDevelopment/**'
---

# Product Development

## A trial run is made on its stage's artefact: use workingArtefact(), never item->artefact
HELP-4800 (18 Nov 2026): a formulation stage typed base_blend or intermediate has its own artefact (code SKU-S{position}, made lazily by ProductDevelopmentRecipes::stageArtefact or CreateProductDevelopmentFormulationArtefact); only the finished_product stage uses the SKU's artefact. Each sample stores the artefact it was made on (product_development_samples.artefact_id); read the recipe, job lines and trial run form through $sample->workingArtefact(), never $sample->item->artefact. The final formulation is the approved run of the finished_product stage (finalOf/finishedFormulation), never the last by position; at most one finished stage, and it is always the last (new blends are inserted before it). An approved blend becomes an intermediate raw material via SetArtefactAsMix.

## A blend is formulated in shares; one unit of its artefact is 1 kg of blend
HELP-4852 (20 Nov 2026): a trial run made on a blend/intermediate stage's own artefact (FormulationPercentages::isForSample; a run on the SKU artefact stays per piece) records each ingredient's % of a sample in g or ml, kept in recipe.formulation on the sample and job. The artefact recipe holds units per kg of blend = % / kg per unit (kilogram = 1, else Σ net_weight × qty of the SKO's OrgStock trade units, else the raw material's trade unit). Only g samples convert: ml needs a density aiku does not have (liquids kg vs L undecided), and so does a raw material with no weight. Those lines are left off the recipe until units go in through SetProductDevelopmentSampleUnits, and the blend is not offered as an ingredient (ReviewProductDevelopmentSample::offerAsIngredient) while any is pending. Never derive kg from "(KG)" in names and never treat ml as g. A new blend artefact (first one, or after its artefact was cancelled) is seeded only from an earlier run recorded in shares (units per kg); a run recorded per piece on the SKU's artefact is never copied into it (that is how HHHBW-07-S1 got 300 units per kg) and only prefills the next run's % form.
