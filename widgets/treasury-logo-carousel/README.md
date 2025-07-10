# Treasury Logo Carousel Widget

A clean, responsive logo carousel showcasing all 28 treasury technology vendors from the RealTreasury portal with a professional header.

- **Professional Header**: Displays "North American Treasury Tech Vendors" heading with vendor count
- **True Infinite Scroll**: Seamless continuous loop with no visible jumps or resets
- **Optimized Performance**: Smooth 40-second full cycle with hardware acceleration
- **Interactive Controls**: Drag/swipe functionality for manual control
- **Mobile-Optimized**: Touch support with responsive design
- **Clickable Logos**: Direct links to vendor portal pages
- **Auto-Resume**: Auto-scroll continues after user interaction
- **Accurate Sizing**: Scroll width calculated after images load for smoother looping

## Usage
Simply embed the `index.html` file into any webpage or use as an iframe:

```html
<iframe src="widgets/treasury-logo-carousel/index.html" width="100%" height="180" frameborder="0"></iframe>
```

### Customization
- Adjust scroll speed by changing the animation duration in CSS (currently 40s)
- Modify logo sizing in the responsive breakpoints  
- Update vendor data in the JavaScript array
- Customize header text and styling in the widget-header section

### Vendor Data
Contains data for 28 treasury technology vendors with proper UTM tracking for analytics.

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
        height="180"
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
- File size is minimal (~18KB total)

## Testing
After adding the widget, test:
- Header displays correctly
- Auto-scroll functionality without jumps
- Drag/swipe interactions on both desktop and mobile
- Logo click-through to vendor portal pages
- Responsive behavior on different screen sizes
