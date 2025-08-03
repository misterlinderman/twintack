<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'meoefemy_WPNS6');

/** Database username */
define('DB_USER', 'meoefemy_WPNS6');

/** Database password */
define('DB_PASSWORD', ')3Xj0S<:QZ5GH[3vg');

/** Database hostname */
define('DB_HOST', 'localhost');

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'c41b989fe799f2f34cda09cc5fad6ef1ed331320ae439c575d554a8a704f064b');
define('SECURE_AUTH_KEY', '39f19a1b7635ad7608f3791bd5f5c8ad4b8c3a2280d5f4527250954b69e50474');
define('LOGGED_IN_KEY', '2a43b4e00a506284fe6ff8d2f9d6b5fd529bec75a726ddffecc337a02f8aa658');
define('NONCE_KEY', '97e4a79d3f203ea1978c25b715e21bfee16a18af33cd4e92453d4b3270c2fe47');
define('AUTH_SALT', '2bdffe5dee8f676a37be3a660f14e65f58d22e381289f6054e29a618b5d89517');
define('SECURE_AUTH_SALT', '12b2b18831c2658fcf3f4e83d21e0eede229860ecae65692b4578178387548b1');
define('LOGGED_IN_SALT', '033da81bb56ef7cb93d16a66e8e83dca8f487084ff42d869f91b7c29e0a3a1a8');
define('NONCE_SALT', '5914ba17b527efa1aa97b4da89b6e0bade566c2ee350a0ed8d4b7d72aca9c947');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'sCO_';
define('WP_CRON_LOCK_TIMEOUT', 120);
define('AUTOSAVE_INTERVAL', 300);
define('WP_POST_REVISIONS', 20);
define('EMPTY_TRASH_DAYS', 7);
define('WP_AUTO_UPDATE_CORE', true);

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
// WordPress debugging - default production settings
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

/* Add any custom values between this line and the "stop editing" line. */

// Add SVG and WebP support
define('ALLOW_UNFILTERED_UPLOADS', true);


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';