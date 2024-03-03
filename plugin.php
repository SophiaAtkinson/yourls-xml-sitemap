<?php
/*
Plugin Name: YOURLS XML Sitemap Generator
Plugin URI: https://github.com/SophiaAtkinson/yourls-html-sitemap
Description: Generates a sitemap.xml file with all of your short URLs
Version: 1.0
Author: Sophia Atkinson
Author URI: https://sophia.wtf
*/

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

// Include YOURLS loader
require_once YOURLS_ABSPATH . '/includes/load-yourls.php';

// Hook to add the sitemap action
yourls_add_action('pre_html_head', 'generate_xml_sitemap');

// Function to generate the sitemap.xml file
function generate_xml_sitemap() {
    global $ydb;

    try {
        // Initialize PDO connection
        $pdo = new PDO('mysql:host=' . YOURLS_DB_HOST . ';dbname=' . YOURLS_DB_NAME, YOURLS_DB_USER, YOURLS_DB_PASS);

        // Set PDO to throw exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Start building the XML string
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Check if the 'private' column exists in the YOURLS database table
        $table_name = YOURLS_DB_TABLE_URL;
        $stmt = $pdo->query("DESCRIBE `$table_name`");
        $private_column_exists = false;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['Field'] === 'private') {
                $private_column_exists = true;
                break;
            }
        }

        if ($private_column_exists) {
            // Retrieve all public short URLs
            $stmt = $pdo->prepare("SELECT `keyword`, `url`, `timestamp` FROM `$table_name` WHERE `private` = '0'");
            $stmt->execute();
        } else {
            // Retrieve all short URLs (assuming all are public)
            $stmt = $pdo->prepare("SELECT `keyword`, `url`, `timestamp` FROM `$table_name`");
            $stmt->execute();
        }

        // Loop through each link and add it to the sitemap
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $shorturl = yourls_link($row['keyword']);
            $timestamp = date('c', strtotime($row['timestamp']));

            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars($shorturl) . '</loc>';
            $xml .= '<lastmod>' . $timestamp . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.8</priority>';
            $xml .= '</url>';
        }

        // Close the XML
        $xml .= '</urlset>';

        // Save the XML to sitemap.xml file
        file_put_contents(YOURLS_ABSPATH . '/sitemap.xml', $xml);
    } catch (PDOException $e) {
        // Handle PDO exceptions
        yourls_die('Error: ' . $e->getMessage());
    }
}
