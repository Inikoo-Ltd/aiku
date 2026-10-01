"""One forecast call for every TimesFM version: TIMESFM_VERSION=3 (default, production use granted by Google) or 2.5 (Apache-2.0 fallback)."""

import os
import platform

import numpy as np

VERSION = os.environ.get("TIMESFM_VERSION", "3")


class Forecaster:
    def __init__(self):
        if os.environ.get("TIMESFM_THREADS"):
            import torch

            torch.set_num_threads(int(os.environ["TIMESFM_THREADS"]))
        if VERSION == "3":
            if platform.system() == "Darwin" and platform.machine() == "arm64" and os.environ.get("TIMESFM_BACKEND") != "torch":
                from timesfm3.mlx import TimesFM3Forecaster

                self.model = TimesFM3Forecaster.from_pretrained("google/timesfm-3.0-pytorch")
            else:
                from timesfm import TimesFM3Forecaster

                self.model = TimesFM3Forecaster.from_pretrained("google/timesfm-3.0-pytorch", device="cpu", per_core_batch_size=256)
        elif VERSION == "2.5":
            from timesfm import ForecastConfig, TimesFM_2p5_200M_torch

            self.model = TimesFM_2p5_200M_torch.from_pretrained("google/timesfm-2.5-200m-pytorch")
            self.config = ForecastConfig
            self.compiled_for = None
        else:
            raise ValueError(f"TIMESFM_VERSION must be 3 or 2.5, got {VERSION}")

    def deciles(self, contexts, horizon, past_only_covariates=None, past_future_covariates=None):
        """Returns (series, horizon, 9): the 10th..90th percentiles, median at index 4.

        Covariates are per series, shaped (channels, context) for past-only and
        (channels, context + horizon) for past-future. TimesFM 2.5 takes past-future only.
        """
        if VERSION == "3":
            outputs = self.model.predict_batch(
                contexts,
                horizon=horizon,
                past_only_covariates=past_only_covariates,
                past_future_covariates=past_future_covariates,
                return_quantiles=True,
            )
            return np.stack([output.quantiles for output in outputs])

        if past_only_covariates is not None:
            raise ValueError("TimesFM 2.5 has no past-only covariates")
        self.compile_for(max(len(context) for context in contexts), horizon)
        if past_future_covariates is None:
            _, quantiles = self.model.forecast(horizon=horizon, inputs=list(contexts))
            return quantiles[:, -horizon:, 1:]

        channels = len(past_future_covariates[0])
        _, quantiles = self.model.forecast_with_covariates(
            inputs=list(contexts),
            dynamic_numerical_covariates={
                f"covariate_{channel}": [covariates[channel][:len(context) + horizon] for context, covariates in zip(contexts, past_future_covariates)]
                for channel in range(channels)
            },
        )
        return np.stack([np.asarray(series)[:horizon, 1:] for series in quantiles])

    def compile_for(self, longest_context, horizon):
        """2.5 pads every series to max_context, so size it to the data: 156 weeks padded to 4096 ran ~25x slower."""
        shape = (-(-longest_context // 32) * 32, -(-horizon // 128) * 128)
        if shape == self.compiled_for:
            return
        self.model.compile(self.config(
            max_context=shape[0],
            max_horizon=shape[1],
            per_core_batch_size=256,
            normalize_inputs=True,
            use_continuous_quantile_head=True,
            fix_quantile_crossing=True,
            return_backcast=True,
        ))
        self.compiled_for = shape


if __name__ == "__main__":
    full = np.sin(np.arange(576) * 40 / 511).astype(np.float32)
    forecaster = Forecaster()
    median = forecaster.deciles([full[:512]], horizon=64)[0, :, 4]
    error = float(np.abs(median - full[512:]).mean())
    assert error < 0.05, error
    with_covariate = forecaster.deciles([full[:512]], horizon=64, past_future_covariates=[np.ones((1, 576), dtype=np.float32)])
    assert with_covariate.shape == (1, 64, 9), with_covariate.shape
    print(f"TimesFM {VERSION} ok, sine MAE {error:.4f}")
