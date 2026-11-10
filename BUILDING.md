# Building and Releasing RA Tools

## Prerequisites

- Git (any client — command line, GitHub Desktop, VS Code, etc.)
- Access to push to the `beta` branch on GitHub
- Access to merge pull requests on GitHub

No local build tools are required. All zips are built by GitHub Actions.

---

## Branch model

| Branch | Purpose |
|--------|---------|
| `beta` | Active development. Push here to get a testable zip. |
| `main` | Stable releases only. Protected — merge via PR. |

Feature branches are optional but encouraged for larger changes:
cut from `beta`, PR back to `beta` when done.

---

## Day-to-day development

1. Make your changes in the source folders (`com_ra_tools/`, `mod_ra_tools/`, `plg_ra_tools/`).
2. Push to `beta` (directly or via a feature-branch PR).
3. GitHub automatically builds `pkg_ra_tools-<version>-beta.zip` and publishes it as a pre-release at:
   `https://github.com/Ramblers-Tools/ra-tools/releases`
4. Install that zip on a staging Joomla site to test. Repeat steps 1–3 as needed.

> The beta zip is marked `prerelease: true` so Joomla's auto-update system ignores it on production sites.

---

## Releasing a new version

### 1. Decide the version number

We use semantic versioning:

| Change type | Example |
|-------------|---------|
| Bug fix | `4.0.0` → `4.0.1` |
| New feature | `4.0.0` → `4.1.0` |
| Breaking change | `4.0.0` → `5.0.0` |

### 2. Update the changelog

Edit **`changelog.xml`** and add an entry for the new version above the existing ones:

```xml
<changelog>
    <element>pkg_ra_tools</element>
    <type>package</type>
    <version>4.1.0</version>
    <date>2026-07-17</date>
    <channel>stable</channel>
    <tag>stable</tag>
    <entry>
        <type>Feature</type>
        <content><![CDATA[Short description of what changed]]></content>
    </entry>
</changelog>
```

Common `<type>` values: `Feature`, `Fix`, `Security`, `Language`, `Removed`.

### 3. Bump the version in the package manifest

Edit **`pkg_ra_tools/pkg_ra_tools.xml`** and update the `<version>` tag to match:

```xml
<version>4.1.0</version>
```

Commit both files and push to `beta`.

### 3. Open a PR from `beta` → `main` and merge it

- Title: `Release vX.Y.Z` (or a short description of what's in this release)
- Review the diff, then merge.

### 4. Push a version tag to trigger the release

After merging, tag the commit on `main` and push the tag:

```bash
git fetch origin
git tag v4.1.0 origin/main
git push origin v4.1.0
```

Or from any git client that supports tagging — GitHub Desktop: **Repository → Create Tag**.

### 5. GitHub builds the release automatically

Pushing a `v*` tag triggers `.github/workflows/release.yml`, which:

1. Reads the version from `pkg_ra_tools/pkg_ra_tools.xml`
2. Builds zips for each extension:
   - `com_ra_tools.zip`
   - `mod_ra_tools.zip`
   - `plg_webservices_ra_tools.zip`
   - `plg_cli_ra_houskeeping.zip`
   - `plg_finder_ra_toolswalks.zip`
3. Assembles the package zip: `pkg_ra_tools-<version>.zip`
4. Publishes a GitHub Release tagged `v<version>` with the zip attached
5. Computes the sha256 of the package zip
6. Updates `updates/pkg_ra_tools.xml` with the new version, download URL and sha256
7. **Automatically opens a PR** titled `Update update manifest for v<version>`

### 6. Merge the auto-opened manifest PR

Once the release workflow finishes (usually under 2 minutes), a PR will appear:

`Update update manifest for vX.Y.Z` → targeting `main`

Merge it. This updates the Joomla update server file so production sites running a previous version are offered the upgrade via **System → Update → Extensions**.

---

## What each file does

| File | Purpose |
|------|---------|
| `pkg_ra_tools/pkg_ra_tools.xml` | Package manifest. Controls version, included extensions, and the update server URL. **Bump `<version>` here before each release.** |
| `updates/pkg_ra_tools.xml` | Joomla update server manifest. Updated automatically by the release workflow. Do not edit by hand. |
| `changelog.xml` | Human-readable changelog. Update this alongside `<version>` for each release. |
| `.github/workflows/release.yml` | Release workflow — triggered on push of a `v*` tag. |
| `.github/workflows/beta.yml` | Beta pre-release workflow — triggered on push to `beta`. |

---

## Local development setup (macOS / MAMP)

See [`docs/JOOMLA_DEVELOPMENT_SETUP.md`](docs/JOOMLA_DEVELOPMENT_SETUP.md) and [`setup-joomla-symlinks.sh`](setup-joomla-symlinks.sh) for how to symlink the repo into a local Joomla installation for live development without installing the zip each time.
