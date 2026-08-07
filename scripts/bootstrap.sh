#!/usr/bin/env bash

set -e

MODULES=(
Shared
Identity
Customer
Account
Transaction
Notification
Audit
Reporting
Administration
)

for module in "${MODULES[@]}"
do
    mkdir -p src/$module/API
    mkdir -p src/$module/Application
    mkdir -p src/$module/Domain
    mkdir -p src/$module/Infrastructure
    mkdir -p src/$module/Tests
done

mkdir -p docs
mkdir -p docker
mkdir -p scripts

echo "Project structure created successfully."
