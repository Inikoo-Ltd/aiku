#!/bin/bash
git pull
git fetch . main:staging
git push origin staging
