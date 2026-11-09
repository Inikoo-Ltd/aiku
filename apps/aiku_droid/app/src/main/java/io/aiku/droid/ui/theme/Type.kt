package io.aiku.droid.ui.theme

import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import io.aiku.droid.R

private fun variableWeight(resourceId: Int, weight: FontWeight) = Font(
    resId = resourceId,
    weight = weight,
    variationSettings = FontVariation.Settings(FontVariation.weight(weight.weight)),
)

val Inter = FontFamily(
    variableWeight(R.font.inter, FontWeight.Normal),
    variableWeight(R.font.inter, FontWeight.Medium),
    variableWeight(R.font.inter, FontWeight.SemiBold),
    variableWeight(R.font.inter, FontWeight.Bold),
)

val Bitter = FontFamily(variableWeight(R.font.bitter, FontWeight.Bold))

val Caveat = FontFamily(variableWeight(R.font.caveat, FontWeight.Medium))
