<?php
    /**
     * Execute simple GET request to a given URL.
     *
     * @param string $url
     *
     * @return string
     */
    function get_content_from_github($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Chrome");
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }

    /**
     * Pull framework stats from GitHub and enrich the $framework object.
     *
     * @param array $framework
     */
    function enrich_with_github(&$framework)
    {
        // Fetch details from GitHub API.
        $github_repo_json = get_content_from_github('https://api.github.com/repos/' . $framework['github_repo'] . '?access_token=' . GITHUB_ACCESS_TOKEN);
        $github_repo      = json_decode($github_repo_json, true);

        $framework['github'] = array(
            'stars'  => intval($github_repo['watchers_count']),
            'forks'  => intval($github_repo['forks_count']),
            'issues' => intval($github_repo['open_issues']),
        );
    }

    /**
     * Pull framework info from WordPress.org plugins' API and enrich the $framework object.
     *
     * @param array $framework
     */
    function enrich_with_wp(&$framework)
    {
        // Fetch details from GitHub API.
        $wp_repo_json = file_get_contents('http://api.wordpress.org/plugins/info/1.0/' . $framework['wp_slug'] . '.json?fields=active_installs,icons,banners');
        $wp_repo      = json_decode($wp_repo_json, true);

        $framework['wordpress'] = array(
            'downloads' => intval($wp_repo['downloaded']),
            'active'    => intval($wp_repo['active_installs']),
            'avg_rate'  => number_format(5 * (floatval($wp_repo['rating']) / 100), 2),
            'votes'     => intval($wp_repo['num_ratings']),
        );

        if (isset($wp_repo['banners']))
        {
            // Try to fetch banner from API.
            if (isset($wp_repo['banners']['low']))
                $framework['banner'] = $wp_repo['banners']['low'];
            else if (isset($wp_repo['banners']['high']))
                $framework['banner'] = $wp_repo['banners']['high'];
        }
    }

    /**
     * Uses page2images API to snap a screenshot of the framework's homepage and enrich the $framework object.
     *
     * @param array $framework
     */
    function enrich_banner(&$framework)
    {
        if (isset($framework['homepage']))
        {
            $http_homepage = urlencode(str_replace('https://', 'http://', $framework['homepage']));

            $screenshot_fetch_url = 'http://api.page2images.com/restfullink?p2i_url=' . $http_homepage . '&p2i_size=772x250&p2i_key=' . PAGE_2_IMAGES_REST_KEY;

            $screenshot_url = 'http://api.page2images.com/directlink?p2i_url=' . $http_homepage . '&p2i_size=772x250&p2i_key=' . PAGE_2_IMAGES_KEY;

            do
            {
                // Fetch screenshot.
                $result = file_get_contents($screenshot_fetch_url);

                $result = json_decode($result, true);

                if ('processing' === $result['status'])
                {
                    // Wait till screenshot generated.
                    sleep($result['estimated_need_time']);
                }
            } while ('processing' === $result['status']);

            if ('finished' === $result['status'])
            {
                $screenshot_url = $result['image_url'];
            }

            $framework['banner'] = $screenshot_url;
        }
        else
        {
            // Use generic placeholder.
            $framework['banner'] = 'https://placeimg.com/518/168/tech';
        }
    }

    /**
     * Dump variable to a PHP file based on a given destination.
     *
     * @param array  $variable
     * @param string $name     Variable name.
     * @param string $dest
     */
    function dump_var_to_php_file(&$variable, $name, $dest)
    {
        if (file_exists($dest))
            unlink($dest);

        file_put_contents($dest, '<?php ' . $name . ' = ' . var_export($variable, true) . ';');
    }

    /**
     * @param string $path
     *
     * @return string
     */
    function canonize_file_path($path){
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('|/+|', '/', $path);

        return $path;
    }