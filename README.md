# SaaS Demo Block – SebWpSoft

A modern WordPress plugin demonstrating advanced block development for the Gutenberg editor, REST API integration, settings management, and WP-CLI commands.

## Features
- **Custom Gutenberg Block**: Dynamic "SaaS Demo Card" with server-side rendering.
- **REST API Endpoint**: `/wp-json/sdb/v1/ping` to check if a given URL is online, with transient caching.
- **Settings Page**: Configure default card values (name, tagline, price, CTA, features) and enable optional UTM tracking.
- **WP-CLI Command**: `wp sdb ping <url>` for quick URL checks from the command line.
- **Clean, Secure Code**: Follows WordPress coding standards with sanitization and escaping.

## Installation
1. Download or clone the repository.
2. Place the `saas-demo-block` folder into your `wp-content/plugins/` directory.
3. Activate the plugin from **Plugins** in WordPress.
4. Insert the **SaaS Demo Card** block from the Gutenberg editor.

## File Structure
```
saas-demo-block/
├── saas-demo-block.php       # Main plugin file
├── block/
│   ├── block.json            # Block metadata
│   ├── index.js              # Block edit script
```

## Usage
- In the block editor, search for **SaaS Demo Card**.
- Configure name, tagline, price, CTA, and features.
- Use the **Check URL** button to verify if the CTA link is online.

## Settings
Navigate to **Settings → SaaS Demo** to set default values for all new blocks and toggle UTM parameter appending.

## REST API
`GET /wp-json/sdb/v1/ping?url=<your-url>`
- Returns JSON with `ok`, `status`, and `code` fields.

## WP-CLI
`wp sdb ping <url>`
- Checks the given URL and prints the result in the console.

## Author
SebWpSoft

## License
GPLv2 or later
