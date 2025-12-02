# CS Sponsored Articles

A Joomla 5 system plugin that automatically highlights sponsored articles with a custom background color in blog views.

## Features

- Automatically creates a custom field to mark articles as sponsored
- Highlights sponsored articles in blog category views with a configurable background color
- Works with Cassiopeia template and other common blog layouts
- Hides the custom field value display from the frontend
- Joomla 5 Native plugin architecture

## Requirements

- Joomla 5.x
- PHP 8.1 or higher

## Installation

1. Download the latest release (`plg_system_sponsoredarticles_v1.1.0.zip`)
2. In Joomla Administrator, go to **System > Install > Extensions**
3. Upload and install the package
4. The plugin will be automatically enabled
5. A custom field group "Sponsored" and field "Sponsored?" will be created automatically

## Usage

### Marking an Article as Sponsored

1. Go to **Content > Articles** and edit an article
2. Click on the **Fields** tab
3. Find the "Sponsored?" field under the "Sponsored" group
4. Toggle it to **Yes**
5. Save the article

### Viewing Sponsored Articles

When viewing a blog category page, any article marked as sponsored will have a highlighted background color applied to its container.

## Configuration

Access plugin settings via **System > Manage > Plugins** and search for "CS Sponsored Articles".

### Options

| Option | Description | Default |
|--------|-------------|---------|
| Sponsored Background Color | The background color applied to sponsored article containers | #fff3cd (light yellow) |
| Template Type | Select your template for optimal container detection | Auto-detect |
| Custom Container Class | CSS class for custom templates (only when Template Type is "Custom") | - |

### Template Type Options

- **Auto-detect**: Tries all known patterns (may be slower on large pages)
- **Cassiopeia**: Joomla's default template
- **Template Creator CK**: Templates built with Template Creator CK
- **YooTheme**: YooTheme Pro templates
- **JoomlArt (JA)**: JoomlArt templates
- **Custom CSS Class**: Specify your own container class name

## How It Works

1. The plugin queries the database for all published articles that have the `sponsored-article` custom field set to "Yes" (value: 1)
2. On page render, it searches for blog item containers that contain links to these sponsored articles
3. It adds the `cs-sponsored-item` CSS class to the matching containers
4. The plugin injects CSS to style these containers with the configured background color

## Supported Templates

The plugin supports automatic detection for the following templates:

| Template | Container Class |
|----------|-----------------|
| Cassiopeia | `com-content-category-blog__item` |
| Template Creator CK | `tck-article` |
| YooTheme | `uk-article` |
| JoomlArt (JA) | `ja-item`, `ja-blog-item` |
| Generic | `blog-item`, `news-item`, `article-item`, `card` |

For other templates, use the "Custom CSS Class" option and specify your template's blog item container class.

## Customization

### Custom CSS

You can override the default styling by adding custom CSS to your template:

```css
.cs-sponsored-item {
    background-color: #your-color !important;
    border: 2px solid #accent-color;
    /* Add any additional styling */
}
```

### Finding Your Template's Container Class

If your template isn't listed above:

1. Open your blog page in a browser
2. Right-click on a blog article item and select "Inspect"
3. Look for the `<div>` or `<article>` tag that wraps the entire blog item
4. Note the CSS class (e.g., `my-blog-item`)
5. In plugin settings, select "Custom CSS Class" and enter that class name (without the dot)

## Troubleshooting

### Sponsored articles not highlighted

1. Verify the plugin is enabled in **System > Manage > Plugins**
2. Check that the article has the "Sponsored?" field set to "Yes"
3. Ensure you're viewing the article in a blog/category view (not a single article view)
4. Check that your template uses standard Joomla blog item classes

### Custom field not showing

1. Go to **Content > Fields** and verify the "sponsored-article" field exists
2. Check the field is published and has access level "Public"
3. Ensure the field is assigned to the correct category or "All Categories"

## License

GNU General Public License version 2 or later

## Author

CyberSalt - [https://cybersalt.org](https://cybersalt.org)

## Support

For issues and feature requests, please visit [GitHub Issues](https://github.com/cybersalt/cs-sponsored-articles/issues).
