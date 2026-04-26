=== WP News Collector ===
Contributors: jules
Tags: rss, news, aggregator, ai, autoblog
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A premium WordPress plugin to fetch, rewrite, and publish news from RSS feeds with AI auto-tagging and translation.

== Description ==

WP News Collector is an advanced RSS aggregator plugin that fetches news from your favorite sources and places them into a custom moderation queue. Rather than blindly publishing content, it allows administrators to review, edit, approve, or reject news manually, or set it to auto-publish.

It features advanced capabilities such as:
* **AI Rewriting & Translation:** Integrates with OpenAI (ChatGPT) to automatically rewrite fetched news for SEO, translate it into your target language, and generate smart tags.
* **Smart Filtering:** Include or exclude news items based on specific keywords.
* **Full-Text Extraction:** Scrapes the full content of the article from the source URL when the RSS feed only provides an excerpt.
* **Image Sideloading:** Automatically downloads and attaches external images to your WordPress Media Library.
* **Elementor Support:** Includes a custom Elementor Widget and a shortcode `[news_bulletin]` to display news on the frontend with AJAX 'Load More' pagination.
* **Telegram Bot:** Automatically post newly approved news to your Telegram channel.
* **Visual Dashboard:** Monitor your queue statistics with beautiful Chart.js graphs.
* **Custom Post Type:** Publish news to standard blog posts or a dedicated 'News' Custom Post Type.

== Installation ==

1. Upload the `wp-news-collector` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to "News Collector" in the admin menu.
4. Go to the "Settings" tab, add your RSS links (one per line), configure your desired update interval, and input your OpenAI API Key and Telegram Bot token if desired.

== Frequently Asked Questions ==

= How do I map different RSS feeds to different categories? =
In the RSS Links text area, you can append `|category_id` to the end of the URL. For example: `https://example.com/feed|5` will automatically assign news from that feed to Category ID 5.

= What happens if an RSS feed has no image? =
The plugin will first attempt to scrape the `og:image` from the source article. If that fails, it will use the "Default Fallback Image URL" you provide in the settings.

== Screenshots ==

1. The Moderation Queue with bulk actions and AJAX approval/rejection.
2. The Visual Dashboard and Settings panel.

== Changelog ==

= 1.0.0 =
* Initial release with Full-text scraping, AI translation, Telegram bot support, and Elementor integration.