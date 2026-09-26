# RA Tools

A Joomla 5/6 extension package for Ramblers local groups and areas:
- provides a menu-driven interface to the RamblersWebs Library routines for displaying walks. This totally eliminates the need to write any php code, and makes it simply to choose the required display options
- similarly provides menus for other Library functions
- simplifies the display of committee members, and enables email contact to them
- supports a multi- level document library
- holds details of all Areas and Groups, with search and sort functions, and links to their walks
- allows quick sorting and searching of local articles
- supports the display of reference articles from a remote site, so there are effortlessly kept up to date
- includes a file upload facility
- and is a prerequisite for `ra_mailman` and `ra-events`.

## What's included

| Extension | Type | Purpose |
|-----------|------|---------|
| `com_ra_tools` | Component | Core component — admin and site views |
| `mod_ra_facebook` | Front-end module | A simple sidebar module that displays a link to the specified Facebook page. |
| `mod_ra_tools` | Front-end module | A customisable side-bar display of walks as list or calendar, using the configuration parameters defined in com_ra_tools for the primary selection. |
| `plg_ra_ajaxgroups` | Plugin (finder) | An Ajax service to supports a related Javascript file and custom field that work together to allow two- stage selection of Area / Group to return a four-character group code. |
|plg_system_ra_tools | System plugin | Provides a function that prevents orphan records when a Joomla User record is deleted. Cascades the delete to any related tables: ra_profiles, ra_emails, ra_mail_subcriptions, ra_mail_subcriptions_audit, ra_bookings, and ra_bookings_guest |
|plg_user_ra_profile | User plugin | Provides a function that creates or updates a record in the ra_profiles table when a User record is created by Joomla, whether by manual back end input or programmatically using the internal Joomla API. |
| `plg_webservices_ra_tools` | Plugin (webservices) | REST API endpoints |
| `plg_finder_ra_toolswalks` | Smart Search indexer for ra_walks (Should be in ra-walks)|

## Installing

1. Download the latest `pkg_ra_tools-<version>.zip` from the [Releases](https://github.com/Ramblers-Tools/ra-tools/releases) page.
2. In Joomla: **Extensions → Install → Upload Package File** — select the zip.
3. Enable the plugins under **Extensions → Plugins** if not auto-enabled.

### Updating

Joomla's built-in update system will detect new releases automatically (the update server URL is baked into the package manifest). Go to **System → Update → Extensions** and update from there.

## Branch model

| Branch | Purpose |
|--------|---------|
| `main` | Stable, released code. Protected — merge via PR only. Pushing a version tag (`vX.Y.Z`) here triggers the release workflow. |
| `beta` | Integration branch. New features land here first; a passing beta build produces a pre-release. |
| feature branches | Short-lived branches cut from `beta` for individual changes. |

Merging a PR into `main` does **not** by itself trigger a release — a maintainer tags the merged commit (`vX.Y.Z`) and pushes the tag, which is what starts the GitHub Actions release workflow. See [CONTRIBUTING.md](CONTRIBUTING.md#releasing) for the full steps.

## Development setup

```bash
git clone https://github.com/Ramblers-Tools/ra-tools.git
cd ra-tools
# Use setup-joomla-symlinks.sh to symlink extension folders into a local Joomla install
bash setup-joomla-symlinks.sh /path/to/joomla
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full contribution workflow.
