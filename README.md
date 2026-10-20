# Small Victories Bookings

> **No longer actively maintained.** The code stays available as is. Forks are welcome; issues and pull requests may not get an answer.

A flexible WordPress plugin for managing bookings with custom forms and availability tracking.

## Features

- **Custom Form Fields**: Create forms with any number of custom fields (text, email, phone, number)
- **Option Management**: Define bookable options with availability limits
- **Stock Management**: Automatically tracks availability and prevents overbooking
- **Batch Import**: Quick option setup via batch import (name;availability format)
- **Responsive Design**: Mobile-friendly forms with customizable column layouts
- **Submissions Management**: View all submissions in the WordPress admin
- **CSV Export**: Export submissions by option with all field data
- **Multi-language Support**: Includes translations for Dutch, French, German, and English
- **Transaction Safety**: Database transactions prevent double-booking race conditions

## Installation

1. Upload the plugin files to `/wp-content/plugins/small-victories-bookings/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'SV Bookings' in the admin menu to create your first form

## Usage

### Creating a Form

1. Go to **SV Bookings > Add New**
2. Enter a form name and choose the number of columns for options display
3. Define your custom form fields (e.g., Name, Email, Phone, Company)
4. Add options with availability counts either:
   - Via **Batch Import**: Paste lines like `Option Name;10`
   - Or **Manually**: Add options one by one

### Adding to Pages

Use the shortcode with your form ID:

```
[timeslot_form id="1"]
```

### Viewing Submissions

1. Go to **SV Bookings > Submissions**
2. Select a form from the dropdown
3. View all submissions with their field responses and selected options
4. Click **Export to CSV** to download the data

### CSV Export Format

The CSV export organizes data by option:
- **Column 1**: Option name
- **Subsequent columns**: Submissions with all field values joined by " - "

Example: `John Doe - john@example.com - 555-1234`

## Database Schema

### Tables

- `wp_timeslot_forms`: Form definitions with custom fields (JSON)
- `wp_timeslot_options`: Bookable options with availability counts
- `wp_timeslot_submissions`: Submissions with field responses (JSON)
- `wp_timeslot_submission_options`: Many-to-many relationship for selected options

### Migrations

The plugin includes an automatic migration system. Version 2 schema:
- Converts fixed `name` and `email` columns to flexible JSON `response` field
- Adds `fields` JSON column to forms for custom field definitions
- Migrates existing data automatically on plugin update

## Internationalization

Translations included for:
- English (default)
- Dutch (nl_NL)
- French (fr_FR)
- German (de_DE)

To add a new language:
1. Copy `languages/small-victories-bookings.pot`
2. Create a new `.po` file (e.g., `small-victories-bookings-es_ES.po`)
3. Translate the strings
4. Compile to `.mo` using `msgfmt -o file.mo file.po`

## Development

### File Structure

```
small-victories-bookings/
├── assets/
│   ├── admin.css          # Admin styling
│   ├── admin.js           # Admin scripts
│   ├── frontend.css       # Public form styling
│   └── frontend.js        # Form submission handling
├── includes/
│   ├── admin-forms-list.php      # Forms overview
│   ├── admin-form-editor.php     # Form creation/editing
│   ├── admin-submissions.php     # Submissions view
│   └── frontend-form.php         # Public form display
├── languages/
│   ├── *.pot              # Translation template
│   ├── *.po               # Translation sources
│   └── *.mo               # Compiled translations
└── small-victories-bookings.php  # Main plugin file
```

### Hooks & Filters

The plugin uses standard WordPress hooks:
- `plugins_loaded`: Load textdomain and run migrations
- `admin_menu`: Register admin pages
- `wp_ajax_timeslot_submit`: Handle form submissions
- `admin_init`: Handle CSV exports

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher (with JSON support)

## Changelog

### Version 1.0.4
- Added custom form fields with JSON storage
- Implemented database migration system
- Added multi-language support (NL, FR, DE)
- Enhanced form styling with hover effects
- Added CSV export functionality

### Version 1.0.0
- Initial release
- Basic form and option management
- Fixed name/email fields
- Availability tracking
- Transaction-safe submissions

## License

GPL v2 or later

## Author

Small Victories
https://small-victories.co

## Support

For issues and feature requests, please visit:
https://github.com/marijnvandevoorde/small-victories-bookings
