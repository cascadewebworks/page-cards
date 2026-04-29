# Page Cards

A WordPress Gutenberg block that renders a responsive grid of clickable cards. Cards can be sourced from child pages, manually entered custom entries, or any public custom post type.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Download the latest release zip from the [Releases](https://github.com/cascadewebworks/page-cards/releases) page.
2. In WordPress Admin go to **Plugins → Add New → Upload Plugin**, select the zip, and install.
3. Activate the plugin.

Once activated, the **Page Cards** block is available in the Gutenberg block inserter under the Widgets category.

Updates are delivered through the standard WordPress update mechanism — you will see the usual update notification in WP Admin when a new release is available.

---

## The Block

### Sources

| Source | Description |
|---|---|
| **Child Pages** | Queries all published child pages of the current page, ordered by menu order. A page is excluded if it has a `exclude_from_child_pages` custom field set to `1`. |
| **Custom Entries** | Manually entered cards with a title, description, link URL, and icon. The number of cards (1–12) is configurable. |
| **Custom Post Type** | Queries all published posts of any public, REST-enabled custom post type. Icon and subtitle are read from post meta fields you specify. |

### Card Styles

| Style | Description |
|---|---|
| **Rounded** | Filled card with rounded corners. Background color applies to the whole card. |
| **Flat** | Same as Rounded with square corners. |
| **Guide** | White card with a colored left border and accent color on the icon and title. Background/Accent color applies to the border, icon, and title; a separate text color applies to the subtitle. |

### Block Settings

All settings are in the block's sidebar inspector panel.

**Shared**

| Setting | Options | Default |
|---|---|---|
| Source | Child Pages, Custom Entries, Custom Post Type | Child Pages |
| Card Style | Rounded, Flat, Guide | Rounded |
| Desktop Columns | 1–4 | 2 |
| Background / Accent Color | Hex color | `#f0f0f0` |
| Text Color | Hex color | `#333333` |

**Child Pages source**

| Setting | Options | Default |
|---|---|---|
| Icon Type | MDI, Font Awesome, Dashicons, Custom Image / SVG | MDI |
| Icon | Icon name or class string (see [Icons](#icons)) | `chevron-right` |
| Subtitle Source | Excerpt, Custom Field: `page_description`, None | Excerpt |

**Custom Entries source**

Each card has its own title, description, link URL, icon type, and icon. The **Number of Cards** setting (1–12) controls how many card editors appear.

**Custom Post Type source**

| Setting | Description | Default |
|---|---|---|
| Post Type | Any public, REST-enabled CPT registered on the site | — |
| Icon Field | Post meta key that stores the icon name | `cpt_icon` |
| Icon Type Field | Post meta key that stores the icon type (`mdi`, `fa`, `dashicons`, `svg`). Leave blank to assume MDI. | `cpt_icon_type` |
| Subtitle Source | Post Excerpt, Custom Field, or None | Excerpt |
| Subtitle Field | Post meta key for the subtitle text (only shown when Subtitle Source is Custom Field) | — |

---

## Default Settings

**Plugins → Page Cards → Settings** opens a page where you can set site-wide defaults. Every new block instance inherits these values. Within the block sidebar, each setting has a **Reset to default** link that restores the field to the current site default.

---

## Icons

The plugin loads icon libraries on-demand based on the icon type selected.

| Type | Value format | Reference |
|---|---|---|
| **MDI** (Material Design Icons) | Slug only — e.g. `home`, `file-document-outline` | [pictogrammers.com/library/mdi](https://pictogrammers.com/library/mdi) |
| **Font Awesome** | Full class string — e.g. `fa-solid fa-house`, `fa-regular fa-envelope` | [fontawesome.com/icons](https://fontawesome.com/icons) |
| **Dashicons** | Name without the `dashicons-` prefix — e.g. `admin-home`, `format-image` | [developer.wordpress.org/resource/dashicons](https://developer.wordpress.org/resource/dashicons/) |
| **Custom Image / SVG** | Image URL selected from the media library | — |

---

## Custom Post Type Setup

For the CPT source to display icons, each post needs meta fields for the icon name and (optionally) icon type. The default field names are `cpt_icon` and `cpt_icon_type`, but any meta key names work — configure them in the block settings or in **Default Settings**.

Example ACF field group configuration:

| Field | Meta Key | Type | Notes |
|---|---|---|---|
| Icon Type | `cpt_icon_type` | Select | Choices: `mdi`, `fa`, `dashicons`, `svg` |
| Icon | `cpt_icon` | Text | Icon name or class string per the table above |
