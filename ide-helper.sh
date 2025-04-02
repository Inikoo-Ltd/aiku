#!/bin/bash
php artisan ide-helper:generate
php artisan ide-helper:models -W -R
php artisan ide-helper:meta
vendor/bin/pint
