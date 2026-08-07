#!/usr/bin/env bash

set -e

MODULE=$1

mkdir -p src/$MODULE/API
mkdir -p src/$MODULE/Application
mkdir -p src/$MODULE/Domain
mkdir -p src/$MODULE/Infrastructure
mkdir -p src/$MODULE/Tests

echo "$MODULE created."
