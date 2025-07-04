# Treasury Portal Access Shortcodes Example

The snippet below shows how to embed protected videos or other resources in a WordPress page using the plugin shortcodes.

```html
[protected_content content_ids="https://www.youtube.com/watch?v=dQw4w9WgXcQ"]
    <p>Thanks for joining the Treasury Tech Portal. Download our <a href="/files/portal-report.pdf">report here</a>.</p>
[/protected_content]

<p>Don't have access yet?</p>
[portal_button text="Get Portal Access"]
```

## Expected Behavior

Once the **Treasury Portal Access** plugin is active:

1. Visitors without access see a **Portal Access Required** notice prompting them to complete the form before the content is displayed.
2. After submitting the form, the visitor is redirected and gains temporary portal access, revealing any content wrapped in `[protected_content]`.
3. The `[portal_button]` shortcode renders a button that opens the access modal anywhere on the page.
4. Returning users with a valid access token see the protected content automatically without filling out the form again.
