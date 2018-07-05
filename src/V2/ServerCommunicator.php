<?php

namespace Smoolabs\V2;

if (!class_exists('\\Smoolabs\\V2\\ServerCommunicator', false)) :

class ServerCommunicator
{
    protected $serverUrl;
    protected $pluginVersion;
    protected $pluginSlug;
    protected $siteUrl;
    protected $unsafeDebugMode = false;

    public function __construct($config)
    {
        $this->serverUrl       = trailingslashit($config['serverUrl']);
        $this->pluginVersion   = $config['version'];
        $this->pluginSlug      = $config['slug'];
        $this->unsafeDebugMode = defined('WP_DEBUG') && WP_DEBUG;
    }

    public function activateLicense($license_key)
    {
        $response = $this->httpPostRequest('api/v1/license/activate', array(
            'license'   => $license_key,
            'slug'      => $this->pluginSlug,
            'site'      => $this->getSiteUrl(),
            'site-meta' => $this->getMetadata()
        ));

        if (!$response) {
            return (object) array('activated' => false, 'error' => array('code' => 500, 'message' => 'An unknown error occurred.', 'response' => $response));
        }

        return $response;
    }

    public function deactivateLicense($activationId)
    {
        $response = $this->httpPostRequest('api/v1/activation/' . $activationId . '/deactivate');

        if (!$response) {
            return (object) array('deactivated' => false, 'error' => array('code' => 500, 'message' => 'An unknown error occurred.', 'response' => $response));
        }

        return $response;
    }

    public function fetchAnnouncements($lastFetchTime, $packages)
    {
        $response = $this->httpGetRequest('api/v1/announcements/newest', array(
            'after' => $lastFetchTime,
            'packages' => implode(',', $packages)
        ));
               
        return $response;
    }

    protected function getSiteUrl()
    {
        $url = untrailingslashit(get_site_url());
        // in case scheme relative url is passed ('//google.com')
        $url = trim($url, '/');
        $url = preg_replace('/^http(s)?:\/\//', '', $url);
        $url = preg_replace('/^www\./', '', $url);

        return $url;
    }

    protected function httpPostRequest($path, $body = array())
    {
        $url = $this->serverUrl . $path;
        
        try {
            $response = wp_remote_post($url, array(
                'body'      => $body,
                'headers' => array(
                    'Accept' => 'application/json'
                ),
                'sslverify' => $this->unsafeDebugMode
            ));
            
            if(is_wp_error($response)) {
                return false;
            }

            $data = json_decode($response['body']);

            return $data;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function httpGetRequest($path, $query = array())
    {
        $url = $this->serverUrl . $path;
        $url = add_query_arg($query, $url);
        
        try {
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Accept' => 'application/json'
                ),
                'sslverify' => $this->unsafeDebugMode
            ));

            if(is_wp_error($response)) {
                return false;
            }

            $data = json_decode($response['body']);

            return $data;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getMetadata()
    {
        $data = json_encode(array(
            'url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'package_version' => $this->pluginVersion,
            'php_version' => phpversion()
        ));

        return $data;
    }

    public function filterPluginUpdateCheckerQuery($query_args)
    {
        $activation_id = LicenseSettings::getSavedActivationId($this->pluginSlug);
        if (!empty($activation_id)) {
            $query_args['activation'] = $activation_id;
        }

        $query_args['site-meta'] = $this->getMetadata();

        return $query_args;
    }
}

endif;