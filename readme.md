# Twitter to RSS
Quick and dirty PHP script that turns a given Twitter feed into a RSS feed using Twitter's API v2 (the free version!)

Does some basic link expansion and handling, as well as media preview images for pictures and videos.

Call it from a browser like: http://yourserver/yourpath?[numerictwitterid]

The Twitter ID must be numeric. You can look up the numeric ID for a Twitter username at sites like https://tweeterid.com/

## Requirements
- PHP
- PHP cURL
- A Twitter Developer Account

## Configuration

Copy the `config-example.php` to `config.php`

Update the `bearer_token` value with what was created in your [Twitter developer portal](https://developer.twitter.com/en/portal/dashboard)

License is Creative Commons
