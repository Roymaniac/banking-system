# Releasing container images

The release workflow turns a reviewed Git commit into two versioned container images:

- `ghcr.io/roymaniac/banking-system-app`
- `ghcr.io/roymaniac/banking-system-web`

The application image runs PHP-FPM, queue workers, scheduler processes, migrations, and maintenance commands. The web image contains Nginx and only Laravel's public files.

## Release requirements

Before creating a release tag:

1. Merge the intended changes into the protected default branch.
2. Confirm the normal `Quality` workflow is successful.
3. Review pending migrations for forward and backward compatibility.
4. Confirm the database backup and recovery procedure has been tested.
5. Choose the next semantic version, such as `v1.2.3`.

Protect release tags with a repository ruleset so they can only be created by authorized maintainers and cannot be moved or deleted casually. The workflow also rejects a tag whose commit is not part of the default branch.

The tag-triggered workflow independently repeats the migration check, dependency audit, formatting, static analysis, OpenAPI analysis, and complete test suite. An image is not published if any verification step fails.

## Create a release

Create an annotated tag on the exact commit being released:

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

The tag must follow `vMAJOR.MINOR.PATCH`. Pre-release tags such as `v1.1.0-rc.1` are also supported.

For each Docker target, GitHub Actions:

1. Generates semantic-version and commit-SHA tags.
2. Builds Linux AMD64 and ARM64 variants.
3. Publishes the multi-platform image to GitHub Container Registry.
4. Attaches a software bill of materials and detailed build provenance.
5. Publishes a signed GitHub artifact attestation for the image digest.

Stable semantic versions also update the `latest` image tag. Production deployments should use an exact version or, preferably, the immutable digest shown by the workflow rather than `latest`.

## Select released images

Set the published images in the deployment's private `.env.docker` file:

```dotenv
APP_IMAGE=ghcr.io/roymaniac/banking-system-app:1.0.0
WEB_IMAGE=ghcr.io/roymaniac/banking-system-web:1.0.0
```

Digest pinning prevents the selected content from changing even if a registry tag is moved:

```dotenv
APP_IMAGE=ghcr.io/roymaniac/banking-system-app@sha256:APPLICATION_DIGEST
WEB_IMAGE=ghcr.io/roymaniac/banking-system-web@sha256:WEB_DIGEST
```

Private packages require the deployment host to authenticate to GHCR using a token with read access:

```bash
echo "$GHCR_TOKEN" | docker login ghcr.io --username YOUR_GITHUB_USER --password-stdin
```

Do not place the registry token in `.env.docker`, shell history, the repository, or the container image.

## Deploy a release

Pull the selected images before stopping the current application:

```bash
docker compose --env-file=.env.docker pull app web
```

Then follow the migration-first order:

```bash
docker compose --env-file=.env.docker up -d --wait postgres redis
docker compose --env-file=.env.docker stop web worker scheduler app
docker compose --env-file=.env.docker run --rm app php artisan migrate --force
docker compose --env-file=.env.docker run --rm app php artisan banking:production-preflight
docker compose --env-file=.env.docker run --rm app php artisan banking:reconcile-ledger
docker compose --env-file=.env.docker up -d app worker scheduler web
```

The preflight checks production-safe configuration, all required settlement ledgers, and database connectivity. Reconciliation verifies the financial journal and initializes its readiness signal. A reconciliation failure also suspends money movement and requires explicit operator resumption after investigation. Do not start the application-facing services when either command returns a failure.

Verify the deployment:

```bash
docker compose --env-file=.env.docker ps
curl --fail http://localhost:8080/up
```

An authorized operator should also verify `/api/v1/operations/health`, queue processing, scheduler freshness, notification backlog, and ledger reconciliation.

## Rollback

Do not assume application rollback also reverses a migration. Database changes should use expand-and-contract deployment patterns so the previous and new application versions can both operate during rollout.

To roll back compatible application code:

1. Put the previous application and web digests back into `.env.docker`.
2. Pull those images.
3. Stop the current application-facing services.
4. Start the previous app, worker, scheduler, and web images.
5. Repeat health and reconciliation checks.

Never run `migrate:rollback` automatically during an incident. A destructive schema rollback requires a reviewed recovery plan and a verified backup.

## Verify provenance

GitHub CLI can verify that an image digest was produced by this repository's workflow:

```bash
gh attestation verify \
  oci://ghcr.io/roymaniac/banking-system-app@sha256:APPLICATION_DIGEST \
  --repo Roymaniac/banking-system
```

Verification should be performed against the digest selected for deployment, not only against a mutable tag.

