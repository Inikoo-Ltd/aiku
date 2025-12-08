#!/bin/bash
git pull
git fetch . main:staging
git push origin staging
git fetch . main:production
git push origin production
