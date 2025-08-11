<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 11 Jul 2025 14:22:02 British Summer Time, Sheffield, UK
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<template>
  <div class="fraction-display">
    <!-- If there's a quotient and a fraction -->

    <template v-if="quotient !== 0 && remainingDividend !== 0">
      <span class="quotient">{{ quotient }}</span>
      <span class="fraction">
        <span class="numerator">{{ remainingDividend }}</span>
        <svg class="fraction-slash" viewBox="0 0 12 12" width="0.6em" height="0.8em" preserveAspectRatio="none">
          <line x1="1" y1="11" x2="11" y2="1" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <span class="denominator">{{ remainingDivisor }}</span>
      </span>
    </template>

    <!-- If there's only a quotient (no remainder) -->
    <template v-else-if="quotient !== 0 && remainingDividend === 0">
      <span class="quotient">{{ quotient }}</span>
    </template>

    <!-- If there's only a fraction (quotient is 0) -->
    <template v-else-if="quotient === 0 && remainingDividend !== 0">
      <span class="fraction">
        <span class="numerator">{{ remainingDividend }}</span>
        <svg class="fraction-slash" viewBox="0 0 12 12" width="0.6em" height="0.8em" preserveAspectRatio="none">
          <line x1="1" y1="11" x2="11" y2="1" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <span class="denominator">{{ remainingDivisor }}</span>
      </span>
    </template>

    <!-- If both quotient and fraction are 0 -->
    <template v-else>
      <span class="quotient">0</span>
    </template>
  </div>
</template>

<script>
export default {
  name: 'FractionDisplay',

  props: {
    /**
     * The fraction data in the format [quotient, [remaining_dividend, remaining_divisor]]
     */
    fractionData: {
      type: Array,
      required: true,
      validator: (value) => {
        // Validate the structure of the fractionData
        return (
          Array.isArray(value) && 
          value.length === 2 && 
          typeof value[0] === 'number' && 
          Array.isArray(value[1]) && 
          value[1].length === 2 && 
          typeof value[1][0] === 'number' && 
          typeof value[1][1] === 'number'
        );
      }
    },

    /**
     * Whether to simplify the fraction (reduce to the lowest terms)
     */
    simplify: {
      type: Boolean,
      default: false
    }
  },

  computed: {
    /**
     * Get the quotient part of the fraction
     */
    quotient() {
      return this.fractionData[0];
    },

    /**
     * Get the remaining dividend (numerator) of the fraction
     */
    remainingDividend() {
      if (this.simplify) {
        const gcd = this.findGCD(Math.abs(this.fractionData[1][0]), Math.abs(this.fractionData[1][1]));
        return this.fractionData[1][0] / gcd;
      }
      return this.fractionData[1][0];
    },

    /**
     * Get the remaining divisor (denominator) of the fraction
     */
    remainingDivisor() {
      if (this.simplify) {
        const gcd = this.findGCD(Math.abs(this.fractionData[1][0]), Math.abs(this.fractionData[1][1]));
        return this.fractionData[1][1] / gcd;
      }
      return this.fractionData[1][1];
    }
  },

  methods: {
    /**
     * Find the greatest common divisor (GCD) of two numbers
     */
    findGCD(a, b) {
      if (b === 0) {
        return a;
      }
      return this.findGCD(b, a % b);
    }
  }
}
</script>

<style scoped>
.fraction-display {
  display: inline-flex;
  align-items: center;
  font-family: sans-serif;
  height: 1em;
  line-height: 1;
}

.quotient {
  margin-right: 0.2em;
}

.fraction {
  display: inline-flex;
  align-items: center;
  position: relative;
  height: 1em;
}

.numerator {
  position: relative;
  top: -0.25em;
  left: 0.05em;
  font-size: 0.8em;
}

.denominator {
  position: relative;
  bottom: -0.25em;
  right: 0.05em;
  font-size: 0.8em;
}

.fraction-slash {
  margin: 0 0.03em;
  position: relative;
  transform: scale(1, 1.2);
}

/* Adjust spacing for mixed numbers */
.quotient + .fraction {
  margin-left: 0.1em;
}
</style>
