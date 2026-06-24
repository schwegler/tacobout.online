# Tacobout Social - FSE Theme

A modern, dynamic, and fully responsive WordPress Block Theme built specifically for the `tacobout.online` blog. This theme deeply integrates with the fediverse via ActivityPub and ATProto, featuring OS-level light/dark mode support and Tumblog-style post layouts.

## Features

- **Full Site Editing (FSE)**: Customize every aspect of your site—headers, footers, and templates—directly in the WordPress Site Editor.
- **Dynamic Dark Mode**: Seamlessly adapts to your device's preferred color scheme.
- **Fediverse Ready**: Optimized comment sections and reply contexts for ActivityPub (Mastodon) and ATmosphere (Bluesky) interactions.
- **Tumblog Post Formats**: Native block patterns for creating specialized layouts for text updates, photos, and videos.

## Installation

1. Copy the `tacobout.online` directory to your WordPress `wp-content/themes/` folder.
2. In your WordPress admin, go to **Appearance > Themes**.
3. Locate **Tacobout Social** and click **Activate**.

## Usage Guide

### Creating Different Post Types (Tumblog Style)

This theme includes custom block patterns to help you create distinct layouts based on the type of content you are sharing (similar to old Tumblr formats).

When creating a new post:
1. Click the **+** (Add Block) button.
2. Navigate to the **Patterns** tab.
3. You will find specific patterns under standard categories (like Text, Gallery, Media) or you can search for "Tacobout":
   - **Status Post**: For short-form microblogging (perfect for ActivityPub/ATProto updates). No title needed!
   - **Image Post**: A photo-centric layout for sharing images and galleries.
   - **Video Post**: A layout designed to focus on a central video element.

### Social Integration (ActivityPub / ATmosphere)

- **Comments**: The theme includes a highly optimized `comments.html` template part that natively supports WordPress comments. Since ActivityPub federates replies back to WordPress as comments, they will seamlessly appear threaded and styled in a modern format.
- **Author Profiles**: Ensure your users have filled out their bios in the WordPress dashboard. Fediverse handles provided by plugins will automatically integrate if the plugins use standard hooks.
- **Microblogging**: Use the **Status Post** pattern when you want to federate a short thought without a standard WordPress title, ensuring it looks like a native toot/skeet on the fediverse.

### Customization via Site Editor

To change colors, typography, or layouts:
1. Go to **Appearance > Editor**.
2. Click on **Styles** (the half-moon icon).
3. From here, you can tweak the dynamic color palette or adjust the default padding and typography settings. The OS dark mode will respect the base variables set in `style.css`, but you can override specific FSE elements here.
