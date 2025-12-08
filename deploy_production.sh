#!/bin/bash
git pull
git fetch . main:production
git push origin production
