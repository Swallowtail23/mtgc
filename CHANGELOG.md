# Changelog

All notable changes to this project will be documented in this file.

## [v0.1.4-dev] - Unreleased

- Hardened the Scryfall bulk import workflow with stricter argument handling, clearer CLI output, and single-pass
  refresh logic for the all/default datasets.
- Added run-start logging to setup/bulk shell helpers and mirrored key progress messages to CLI output across bulk
  scripts for easier monitoring in cron/containers.
- Profile: disable local currency selection whenever FX is turned off globally to avoid invalid choices.
- Security hardening: tightened escaping for GET parameters and rendered titles, and normalised `htmlspecialchars`
  usage to specify charset/flags explicitly.
- Maintenance: introduced/updated Dependabot configuration and cleaned minor formatting issues.
- Fixed: corrected the revised `downloadBulk` call in bulk scripts to avoid misfires.
- Fixed: corrected an inaccurate log message in bulk scripts to reflect the correct workflow step.
- Fixed: set the JSON quarantine rename result before logging to avoid incorrect error reporting.
- Infrastructure: documented changelog update requirements in `AGENTS.md`.
- Changed: added NOTICE-level progress logging every 1,000 records during Scryfall bulk imports.
- Infrastructure: batch Scryfall bulk imports in 5,000-row transactions and log batch commits.
- Changed: bulk import uses content/price hashes with conditional updates to skip full writes when card content is unchanged.
- Infrastructure: added content_hash/price_hash columns in `setup/mtg_new.sql` and on-demand schema checks in bulk import.

## [v0.1.3] - 2025-12-16

- Maintenance release tagged 0.1.3 (no changelog was recorded at the time of release).

## [v0.1.0-pre] - 2025-12-03

- Initial Docker/Podman packaging with bootstrap scripts (`docker-init.sh/.bat`).
- Added cron templates, backup helper, logrotate config, and systemd unit.
- Documented upgrade playbook, security hardening, and email configuration.
- Includes full Scryfall bulk workflow, composer automation, and configurable dev bind-mounts.

> This is a pre-release tag used during testing; final tagging will occur after QA completes.
