<?php
/*
 * Plugin name: Misha Update Checker
 * Description: This simple plugin does nothing, only gets updates from a custom server
 * Version: 1.1
 * Author: Misha Rudrastyh, changes by Nimrod Cohen
 * Author URI: https://rudrastyh.com
 * License: GPL
 *
 * Make sure to set Author to your github user handle and Version in the plugin header
 * use the .git/hooks/pre-commit and post-commit to automatically update the version number
 * in readme.md files if necessary (there's a pre-commit.sample file)
 * and create a tag with the version number
 */

/**/

defined('ABSPATH') || exit;

if (!class_exists('GitHubPluginUpdater')) {

  class GitHubPluginUpdater {
    private $plugin_slug;
    private $cache_key;
    private $latest_release_cache_key;
    private $cache_allowed;
    private $latest_release = null;
    private $plugin_file = null;
    private $plugin_data = null;

    private function get_plugin_data() {
      if ($this->plugin_data) {
        return;
      }
      $this->plugin_file = $this->plugin_slug . '/' . $this->plugin_slug . '.php';
      if (!file_exists(WP_PLUGIN_DIR . '/' . $this->plugin_file)) {
        $this->plugin_file = $this->plugin_slug . '/index.php';
      }

      $this->plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
    }

    public function __construct() {
      $file = __FILE__;
      $this->plugin_slug = explode('/', plugin_basename($file))[0];
      $this->cache_key = $this->plugin_slug . '_transient_data';
      $this->latest_release_cache_key = $this->plugin_slug . '_release';
      $this->cache_allowed = true;
      $this->get_plugin_data();

      add_filter('plugins_api', [$this, 'get_plugin_info'], 20, 3);
      add_filter('site_transient_update_plugins', [$this, 'update']);
      add_action('upgrader_process_complete', [$this, 'finish_install'], 10, 2);
      add_action('upgrader_post_install', [$this, 'fix_folder'], 10, 3);
    }

    function get_plugin_info($res, $action, $args) {
      // do nothing if you're not getting plugin information right now
      if ('plugin_information' !== $action || $this->plugin_slug !== $args->slug) {
        return $res;
      }
      if (!$this->get_latest_release() || !$this->latest_release) {
        return $res;
      }

      $plugin = [
        'name' => $this->plugin_data['Name'],
        'slug' => $this->plugin_slug,
        'requires' => $this->plugin_data['RequiresWP'],
        'tested' => $this->plugin_data['TestedUpTo'] ?? null,
        'version' => $this->latest_release['tag_name'],
        'author' => $this->plugin_data['AuthorName'],
        'author_profile' => $this->plugin_data['AuthorURI'],
        'last_updated' => $this->latest_release['published_at'],
        'homepage' => $this->plugin_data['PluginURI'],
        'short_description' => $this->plugin_data['Description'],
        'sections' => [
          'Description' => $this->plugin_data['Description'],
          'Updates' => $this->latest_release['body']
        ],
        'download_link' => $this->latest_release['zipball_url']
      ];

      return (object) $plugin;
    }

    private function get_latest_release() {
      if ($this->latest_release) {
        return true;
      }

      $transient = null;
      if ($this->cache_allowed) {
        $transient = get_transient($this->latest_release_cache_key);
      }

      if ($transient) {
        $this->latest_release = $transient;
        return true;
      }

      $github_api_url = 'https://api.github.com/repos/' . $this->plugin_data['AuthorName'] . '/' . $this->plugin_slug . '/releases/latest';

      // Make the API request to GitHub
      $response = wp_remote_get($github_api_url);
      if (is_wp_error($response)) {
        return false;
      }

      $this->latest_release = json_decode(wp_remote_retrieve_body($response), true);
      $this->latest_release["version"] = preg_replace('/[^0-9.]/', '', $this->latest_release["tag_name"]);

      if ($this->cache_allowed) {
        set_transient($this->latest_release_cache_key, $this->latest_release, 5 * MINUTE_IN_SECONDS);
      }

      return true;
    }

    public function update($transient) {

      if (empty($transient->checked)) {
        return $transient;
      }

      // GitHub API URL for the latest release
      if (!$this->get_latest_release() || !$this->latest_release) {
        return $transient;
      }

      if (
        version_compare($this->plugin_data["Version"], $this->latest_release["version"], '<')) {
        $res = new stdClass();
        $res->slug = $this->plugin_slug;
        $res->plugin = $this->plugin_file; // misha-update-plugin/misha-update-plugin.php
        $res->new_version = $this->latest_release["version"];
        $res->tested = $this->plugin_data["TestedUpTo"] ?? null;

        $res->package = $this->latest_release["zipball_url"];

        $transient->response[$res->plugin] = $res;
      }

      return $transient;
    }

    public function finish_install($upgrader, $options) {
      if (
        'update' !== $options['action']
        || 'plugin' === $options['type']
      ) {
        return;
      }

      // just clean the cache when new plugin version is installed
      if ($this->cache_allowed) {
        delete_transient($this->cache_key);
        delete_transient($this->latest_release_cache_key);
      }
    }

    public function fix_folder($response, $hook_extra, $result) {
      global $wp_filesystem;
      $proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
      $wp_filesystem->move($result['destination'], $proper_destination);
      $result['destination'] = $proper_destination;
      $result['destination_name'] = $this->plugin_slug;
      return $response;
    }
  }
}