# WordPress Favorites

A minimal WordPress plugin that lets visitors favorite posts and exposes those
favorites to [Bricks Builder](https://bricksbuilder.io/) through a query loop and
a reactive Favorite Toggle element.

There is no settings page.

## How it works

Favorites are stored in a single `wp_favorites_ids` cookie, so every visitor is
treated the same way. Storing them in a cookie (rather than `localStorage`) keeps
the list available server-side, which the Bricks query loop requires.

Favorites are toggled over the REST API at `wp-favorites/v1`.

## Display favorites with a Bricks query loop

1. Add a **Container** (or any element that supports a query loop).
2. Enable **Use query loop**.
3. Set **Query type** to **Favorites**.
4. Design the loop's inner elements as usual (featured image, `{post_title}`, etc.).

Favorites render in the order they were added.

## Add a Favorite Toggle element

1. Search the Bricks element panel for **Favorite Toggle**.
2. Drag it onto the page.
3. Configure the post source (current post or a specific post), the add/remove
   labels, and the button style.

The toggle renders as a native Bricks button: it reuses the core `bricks-button`
markup and the selected theme style class (`bricks-background-*` for filled,
`bricks-color-*` + `outline` for outline), so it looks like any other Button
element. The plugin ships no styles of its own.

The **Post ID** control accepts Bricks dynamic data, so inside a query loop you
can set it to `{post_id}`.

The button updates optimistically and reflects state server-side on page load.

## Making a favorites loop update live

By default the toggle updates every matching button on the page and removes the
loop item it sits inside when a post is unfavorited. To enable the item removal,
add the custom attribute `data-wp-favorites-item` to the loop item wrapper in
Bricks.

Set the `wp_favorites_refresh_mode` filter to `reload` to reload the page after a
toggle instead.

## Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `wp_favorites_max_ids` | `300` | Maximum stored favorites. |
| `wp_favorites_allowed_post_types` | public post types | Post types that may be favorited. |
| `wp_favorites_query_post_types` | public post types | Post types returned by the query loop. |
| `wp_favorites_refresh_mode` | `dom` | `dom` or `reload`. |

## Public API

```php
wp_favorites_get_ids(): array
wp_favorites_has( int $post_id ): bool
wp_favorites_add( int $post_id ): bool
wp_favorites_remove( int $post_id ): bool
wp_favorites_toggle( int $post_id ): bool
wp_favorites_is_favoritable( int $post_id ): bool
```

## Caching

Favorites are stored in a cookie, so any page containing a Favorites query loop
must vary by cookie (or set `Cache-Control: private`) for visitors.
