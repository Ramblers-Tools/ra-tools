# Contributing to RA Tools

A step-by-step guide to making changes, publishing a beta for testing, and
cutting a full release.

No local build tools are needed — GitHub Actions builds every zip.

---

## How it works in one picture

```
feature branch ──PR──► beta ──PR──► main ──push tag──► full release
                        │                                   │
                        │ (automatic on every push)          │ (automatic on tag)
                        ▼                                   ▼
              pkg_ra_tools-X.Y.Z-beta.zip          pkg_ra_tools-X.Y.Z.zip
                   (pre-release)                    (Joomla auto-update)
```

| Branch | Purpose |
|--------|---------|
| `beta` | Active development. Every push publishes a testable pre-release zip. |
| `main` | Stable releases only. Protected — changes must arrive via PR. |

---

## Part 1 — Pull the beta and start work

### 1. Get the repo (first time only)

```bash
git clone https://github.com/Ramblers-Tools/ra-tools.git
cd ra-tools
```

### 2. Switch to `beta` and get the latest

Do this **every time** you start a session — other people (and Codex) push here too.

```bash
git checkout beta
git pull origin beta
```

### 3. Cut a feature branch (recommended for anything non-trivial)

```bash
git checkout -b my-feature
```

For small fixes you can work directly on `beta` and skip to Part 2.

### 4. Make your changes, then commit

```bash
git add <the files you changed>
git commit -m "Fix the thing that was broken"
```

Use the imperative mood — *Add*, *Fix*, *Remove*, not *Added*, *Fixed*, *Removed*.
Subject line ≤72 chars, blank line, then an optional body explaining the *why*.

---

## Part 2 — Push a beta for testing

**You do not tag anything for a beta.** Pushing to `beta` is the trigger;
`.github/workflows/beta.yml` builds the zip and manages the
`v<version>-beta` tag for you.

### 1. Bump the version first

> **Do not skip this.** See [Why the version bump matters](#why-the-version-bump-matters)
> below — skipping it is the single most common cause of "my change isn't showing up".

Edit `pkg_ra_tools/pkg_ra_tools.xml` and `com_ra_tools/ra_tools.xml`, raising
`<version>` (e.g. `4.0.12` → `4.0.13`).

Check every manifest agrees before you push:

```bash
grep -o '<version>[^<]*' \
  pkg_ra_tools/pkg_ra_tools.xml \
  com_ra_tools/ra_tools.xml \
  mod_ra_tools/mod_ra_tools.xml \
  plg_ra_tools/ra_tools.xml \
  com_ra_tools/plugins/finder/ra_toolswalks/ra_toolswalks.xml
```

All five should show the same version. (`mod_ra_facebook` versions
independently — leave it alone unless you changed that module.)

### 2. Push

**Working directly on `beta`:**

```bash
git pull --rebase origin beta   # pick up anyone else's work first
git push origin beta
```

**Working on a feature branch:**

```bash
git push -u origin my-feature
```

Then open a PR targeting **`beta`** and merge it. The merge is what triggers the build.

### 3. Watch the build

```bash
gh run list --branch beta --limit 3
```

It takes well under a minute. When it's green, the zip is at:

<https://github.com/Ramblers-Tools/ra-tools/releases>

### 4. Install and test

Download `pkg_ra_tools-<version>-beta.zip` and install it on a staging site via
**Extensions → Manage → Install**.

The beta zip is marked `prerelease: true`, so Joomla's auto-update never offers
it to production sites.

### Why the version bump matters

If you push to `beta` **without** raising `<version>`, the workflow still runs and
still succeeds — but it overwrites the *existing* `v<version>-beta` release asset
in place rather than creating a new one.

Joomla dedupes updates by version number, not by file contents. So:

- **System → Update → Extensions** will report you're already up to date, and
  will not re-fetch the package.
- Your change is genuinely in the zip, but nothing will offer it to you.

If you hit this, the recovery is to **download the asset manually** from the
releases page and reinstall it through **Extensions → Manage → Install**. Then
clear your browser cache — the admin UI caches aggressively, and a stale page
will keep showing the old markup for a while after a successful install.

Bumping the version avoids all of this. Bump it.

---

## Part 3 — Release a full version

Everything below happens **after** the beta has been tested and signed off.

### 1. Pre-flight checks

**a. All sub-extension versions match the package version.**

Each sub-extension carries its own `<version>` and none of them bump
automatically. This has shipped stale twice. Run the `grep` from Part 2 and fix
any that lag before going further.

**b. `changelog.xml` has an entry for this version.**

Add it above the existing entries:

```xml
<changelog>
    <element>pkg_ra_tools</element>
    <type>package</type>
    <version>4.1.0</version>
    <date>2026-09-29</date>
    <channel>stable</channel>
    <tag>stable</tag>
    <entry>
        <type>Feature</type>
        <content><![CDATA[Short description of what changed]]></content>
    </entry>
</changelog>
```

Common `<type>` values: `Feature`, `Fix`, `Security`, `Language`, `Removed`.

Version numbers follow semver: bug fix `4.0.0`→`4.0.1`, new feature
`4.0.0`→`4.1.0`, breaking change `4.0.0`→`5.0.0`.

Commit and push any fixes to `beta` before continuing.

### 2. Open a PR from `beta` → `main`

`main` is protected, so this cannot be a direct push.

```bash
gh pr create --base main --head beta --title "Release v4.1.0"
```

Review the diff, then merge it. **Merging does not release anything yet** — the
tag in the next step is what triggers the build.

### 3. Tag `main` to trigger the release

```bash
git fetch origin
git tag v4.1.0 origin/main
git push origin v4.1.0
```

The tag must match the `<version>` in `pkg_ra_tools/pkg_ra_tools.xml`, prefixed
with `v`.

Pushing a `v*` tag runs `.github/workflows/release.yml`, which:

1. Reads the version from `pkg_ra_tools/pkg_ra_tools.xml`
2. Creates `com_ra_tools/administrator/sql/updates/<version>.sql` if missing
3. Builds a zip for each extension and assembles `pkg_ra_tools-<version>.zip`
4. Publishes a non-prerelease GitHub Release with the zip attached
5. Computes the package sha256
6. Rewrites `updates/pkg_ra_tools.xml` with the version, download URL and sha256
7. Opens a PR titled `Update update manifest for v<version>`

### 4. Merge the auto-opened manifest PR

Within a couple of minutes a PR appears against `main`:

```bash
gh pr list --base main
gh pr merge <number> --merge
```

**This step is what makes the release live.** Until it's merged,
`updates/pkg_ra_tools.xml` still points at the previous version and production
sites will not be offered the upgrade via **System → Update → Extensions**.

### 5. Sync `beta` and open the next cycle

```bash
git checkout beta
git pull origin beta
git merge origin/main     # picks up the SQL update file and manifest commit
```

Then bump `<version>` to the next development version, commit, and push.

---

## Reference — what each file does

| File | Purpose |
|------|---------|
| `pkg_ra_tools/pkg_ra_tools.xml` | Package manifest. Both workflows read `<version>` from here. **Bump before every push to `beta`.** |
| `com_ra_tools/ra_tools.xml` | Component manifest. Keep its `<version>` in step with the package. |
| `updates/pkg_ra_tools.xml` | Joomla update server manifest. Written automatically by the release workflow — never edit by hand. |
| `changelog.xml` | Human-readable changelog. Update alongside `<version>` for each release. |
| `.github/workflows/beta.yml` | Beta pre-release build — triggered by **push to `beta`**. |
| `.github/workflows/release.yml` | Full release build — triggered by **push of a `v*` tag**. |

---

## Local development setup

To symlink the repo into a local Joomla install and skip the install-a-zip loop
entirely, see [`docs/JOOMLA_DEVELOPMENT_SETUP.md`](docs/JOOMLA_DEVELOPMENT_SETUP.md)
and [`setup-joomla-symlinks.sh`](setup-joomla-symlinks.sh).

Related: [`BUILDING.md`](BUILDING.md) covers the same release pipeline from the
build system's perspective.
