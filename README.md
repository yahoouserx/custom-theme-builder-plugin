# Custom Theme Builder - WordPress Plugin

A WordPress plugin that provides advanced theme building functionality through a simplified conditions-based system with beautiful modern UI.

## Features

- **Pure Conditions System**: No template type selection - templates are automatically detected based on conditions
- **30+ Condition Types**: Complete coverage of WordPress scenarios including WooCommerce support
- **Beautiful Modern UI**: Card-based layout with gradient headers, interactive stats dashboard, and responsive design
- **WordPress Admin Integration**: Clean, intuitive interface following WordPress design standards
- **Template Management**: Create, edit, and manage custom templates with real-time preview
- **Elementor Integration**: Full support for Elementor page builder
- **Performance Optimized**: Efficient caching and minimal database queries
- **WooCommerce Ready**: Full support for shop pages, product categories, and product templates

## Installation

1. Upload the `custom-theme-builder` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Navigate to "Templates" in your WordPress admin menu
4. Create your first template with conditions

## Usage

### Creating Templates

1. Go to **Templates > Add New** in your WordPress admin
2. Create your template content (works with any page builder including Elementor)
3. Add conditions in the "Template Conditions" meta box
4. Save and your template will automatically be applied based on the conditions

### Condition Types

The plugin supports these condition types:

- **Site-wide**: Entire site, front page
- **Posts**: Specific posts, post types, categories, tags
- **Pages**: Specific pages, page templates
- **Archives**: Category, tag, author, date archives
- **Special**: Search results, 404 pages
- **WooCommerce**: Shop, product categories, product tags, single products
- **User-based**: User roles, specific users

### Template Priority

Templates are processed in order of creation. If multiple templates match the same conditions, the first one takes precedence.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Optional: Elementor for advanced page building

## Support

This plugin follows WordPress coding standards and best practices. For issues or questions, please check the WordPress admin interface for debugging information.

## License

GPL v2 or later