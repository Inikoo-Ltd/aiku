#!/bin/bash
git pull
git fetch . main:deploy_pages
git push origin deploy_pages
