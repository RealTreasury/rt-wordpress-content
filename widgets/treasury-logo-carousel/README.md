# Treasury Logo Carousel Widget

A clean, responsive logo carousel showcasing all 26 treasury technology vendors from the RealTreasury portal.

## Features
- Auto-scrolling at optimal speed (40s full cycle)
- Drag/swipe functionality for manual control
- Mobile-optimized with touch support
- Clickable logos linking to vendor websites
- Seamless infinite loop
- Auto-scroll continues even when hovering

## Usage
Simply embed the `index.html` file into any webpage or use as an iframe:

```html
<iframe src="widgets/treasury-logo-carousel/index.html" width="100%" height="120" frameborder="0"></iframe>
```

### Customization
- Adjust scroll speed by changing the animation duration in CSS (currently 40s)
- Modify logo sizing in the responsive breakpoints
- Update vendor data in the JavaScript array

### Vendor Data
Contains 26 treasury vendors with proper UTM tracking for analytics.

## Integration Options
The widget can be embedded in several ways:

**Option A: Direct Embed**
```html
<div id="treasury-carousel-container"></div>
<script>
  fetch('/widgets/treasury-logo-carousel/index.html')
    .then(response => response.text())
    .then(html => {
      document.getElementById('treasury-carousel-container').innerHTML = html;
    });
</script>
```

**Option B: iframe Embed**
```html
<iframe src="/widgets/treasury-logo-carousel/index.html" 
        width="100%" 
        height="120" 
        frameborder="0"
        style="border: none; display: block;">
</iframe>
```

**Option C: Component Include (if using a framework)**
```html
<!-- For PHP includes -->
<?php include 'widgets/treasury-logo-carousel/index.html'; ?>

<!-- For Jekyll/static sites -->
{% include widgets/treasury-logo-carousel/index.html %}
```

## Additional Notes
- The widget is completely self-contained (no external dependencies)
- All vendor logos are hosted on realtreasury.com CDN
- UTM tracking parameters are included for analytics
- Widget is responsive and works on all devices
- File size is minimal (~15KB total)

## Testing
After adding the widget, test:
- Auto-scroll functionality
- Drag/swipe interactions on both desktop and mobile
- Logo click-through to vendor websites
- Responsive behavior on different screen sizes
