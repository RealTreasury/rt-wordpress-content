# Treasury Portal Access

This plugin adds an access gate for protected content using a Contact Form 7 form. Visitors who complete the form receive a browser cookie and optional localStorage record that grants them access for a configurable duration.

## Installation

1. Copy this folder to your WordPress `wp-content/plugins` directory or upload the ZIP through **Plugins → Add New**.
2. Ensure the [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) plugin is installed and activated.
3. Activate **Treasury Portal Access** from the Plugins screen.
4. Go to **Portal Access → Settings** in the WordPress admin and choose your Contact Form 7 form.

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- Contact Form 7

## Quick Start

1. Create or select a Contact Form 7 form with fields for name and email.
2. In **Portal Access → Settings**, select that form and adjust the access duration and redirect URL if needed.
3. Place the `[portal_button]` shortcode on any page to display a button that opens the access form modal.
4. Wrap restricted content within `[protected_content]` tags so only users with an active access cookie can see it.

## Shortcode Reference

```
[protected_content]Your content here...[/protected_content]
```
Displays content only to users who have granted access. You can also specify `content_ids` or `video_ids` to embed protected items:

```
[protected_content content_ids="https://youtu.be/abc123,https://example.com/file.mp4"]
```

```
[portal_button text="Get Portal Access"]
```
Inserts a button that opens the access form modal.

```
<a href="#openPortalModal">Access Portal</a>
```
Manually trigger the modal from a custom link or button. The ID is case sensitive so use `#openPortalModal` exactly (or the lowercase `#openportalmodal` alias added for convenience).

Once a visitor completes the form they are redirected to your chosen page and can view any content wrapped in `protected_content` for the configured number of days.

## Attempt Tracking

Version 1.0.8 adds percentage metrics to the admin dashboard. The plugin now shows how many visitors abandon the form versus how many complete it. Abandoned attempts continue to be stored in the `portal_access_attempts` database table.

Version 1.0.9 extends these stats with weekly abandonment rates for the current and previous week.

Version 1.0.10 displays weekly user counts for the current and prior week.

Version 1.0.11 adds weekly breakdowns for abandoned attempts and completion rate and shows metrics from two weeks ago across all stat cards.
