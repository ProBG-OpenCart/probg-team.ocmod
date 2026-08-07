<?php
class ModelExtensionProbgTeamTeam extends Model {
    private $working_hours_column = null;
    private $cache_version = null;

    private function hasWorkingHoursColumn() {
        if ($this->working_hours_column === null) {
            $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "team_member_description` LIKE 'working_hours'");
            $this->working_hours_column = (bool)$query->num_rows;
        }

        return $this->working_hours_column;
    }

    private function getWorkingHoursSelect($alias = 'tmd') {
        return $this->hasWorkingHoursColumn() ? $alias . '.working_hours' : "'' AS working_hours";
    }

    private function isCacheEnabled() {
        return (bool)$this->config->get('module_probg_team_cache_status');
    }

    private function getCacheVersion() {
        if ($this->cache_version === null) {
            $version = $this->cache->get('probg_team.version');

            if ($version === false || $version === '') {
                $version = str_replace('.', '', sprintf('%.6F', microtime(true)));
                $this->cache->set('probg_team.version', $version);
            }

            $this->cache_version = (string)$version;
        }

        return $this->cache_version;
    }

    private function getCacheKey($suffix, $language_id = null, $store_id = null) {
        if ($language_id === null) {
            $language_id = (int)$this->config->get('config_language_id');
        }

        if ($store_id === null) {
            $store_id = (int)$this->config->get('config_store_id');
        }

        $version = $this->isCacheEnabled() ? $this->getCacheVersion() : 'disabled';

        return 'probg_team.v.' . $version . '.store.' . (int)$store_id . '.language.' . (int)$language_id . '.' . $suffix;
    }

    private function getCached($key) {
        if (!$this->isCacheEnabled()) {
            return false;
        }

        return $this->cache->get($key);
    }

    private function setCached($key, $value) {
        if ($this->isCacheEnabled()) {
            $this->cache->set($key, $value);
        }

        return $value;
    }

    public function getSectionDescription($language_id = 0, $store_id = null) {
        $language_id = $language_id ? (int)$language_id : (int)$this->config->get('config_language_id');
        $store_id = ($store_id === null) ? (int)$this->config->get('config_store_id') : (int)$store_id;
        $cache_key = $this->getCacheKey('section', $language_id, $store_id);
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_setting_description` WHERE store_id = '" . $store_id . "' AND language_id = '" . $language_id . "' LIMIT 1");

        if (!$query->num_rows && $store_id) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_setting_description` WHERE store_id = '0' AND language_id = '" . $language_id . "' LIMIT 1");
        }

        return $this->setCached($cache_key, $query->row);
    }

    public function getCategories($data = array()) {
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $cache_key = $this->getCacheKey('categories.' . md5(json_encode($data)));
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sql = "SELECT c.*, cd.name, cd.description, cd.meta_title, cd.meta_description, cd.meta_keyword, (SELECT COUNT(*) FROM `" . DB_PREFIX . "team_member` tm INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s ON (tm.team_member_id = tm2s.team_member_id AND tm2s.store_id = '" . $store_id . "') INNER JOIN `" . DB_PREFIX . "team_member_description` tmdc ON (tm.team_member_id = tmdc.team_member_id AND tmdc.language_id = '" . $language_id . "') WHERE tm.team_category_id = c.team_category_id AND tm.status = '1' AND tmdc.name != '') AS member_total FROM `" . DB_PREFIX . "team_category` c INNER JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (c.team_category_id = c2s.team_category_id AND c2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` cd ON (c.team_category_id = cd.team_category_id AND cd.language_id = '" . $language_id . "') WHERE c.status = '1' AND cd.name IS NOT NULL";

        if (empty($data['show_empty'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "team_member` tm2 INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s2 ON (tm2.team_member_id = tm2s2.team_member_id AND tm2s2.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_member_description` tmd2 ON (tm2.team_member_id = tmd2.team_member_id AND tmd2.language_id = '" . $language_id . "') WHERE tm2.team_category_id = c.team_category_id AND tm2.status = '1' AND tmd2.name IS NOT NULL)";
        }

        $sql .= " ORDER BY c.sort_order ASC, LCASE(cd.name) ASC";

        return $this->setCached($cache_key, $this->db->query($sql)->rows);
    }

    public function getCategory($team_category_id) {
        $cache_key = $this->getCacheKey('category.' . (int)$team_category_id);
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $query = $this->db->query("SELECT c.*, cd.name, cd.description, cd.meta_title, cd.meta_description, cd.meta_keyword FROM `" . DB_PREFIX . "team_category` c INNER JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (c.team_category_id = c2s.team_category_id AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` cd ON (c.team_category_id = cd.team_category_id) WHERE c.team_category_id = '" . (int)$team_category_id . "' AND c.status = '1' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");

        return $this->setCached($cache_key, $query->row);
    }

    public function getMembers($data = array()) {
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $cache_key = $this->getCacheKey('members.' . md5(json_encode($data)));
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sql = "SELECT tm.*, tmd.name, tmd.short_description, tmd.telephone, tmd.city, " . $this->getWorkingHoursSelect('tmd') . ", tmd.website, tmd.facebook, tmd.instagram, tmd.youtube, tmd.linkedin, tcd.name AS category_name FROM `" . DB_PREFIX . "team_member` tm INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s ON (tm.team_member_id = tm2s.team_member_id AND tm2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_member_description` tmd ON (tm.team_member_id = tmd.team_member_id AND tmd.language_id = '" . $language_id . "') LEFT JOIN `" . DB_PREFIX . "team_category` tc ON (tm.team_category_id = tc.team_category_id) INNER JOIN `" . DB_PREFIX . "team_category_to_store` tc2s ON (tc.team_category_id = tc2s.team_category_id AND tc2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` tcd ON (tm.team_category_id = tcd.team_category_id AND tcd.language_id = '" . $language_id . "') WHERE tm.status = '1' AND tc.status = '1' AND tmd.name IS NOT NULL";

        if (!empty($data['team_category_id'])) {
            $sql .= " AND tm.team_category_id = '" . (int)$data['team_category_id'] . "'";
        }

        if (!empty($data['search'])) {
            $search = $this->db->escape(trim($data['search']));
            $search_fields = array(
                "tmd.name LIKE '%" . $search . "%'",
                "tmd.short_description LIKE '%" . $search . "%'",
                "tmd.description LIKE '%" . $search . "%'",
                "tmd.city LIKE '%" . $search . "%'",
                "tcd.name LIKE '%" . $search . "%'"
            );

            if ($this->hasWorkingHoursColumn()) {
                $search_fields[] = "tmd.working_hours LIKE '%" . $search . "%'";
            }

            $sql .= " AND (" . implode(' OR ', $search_fields) . ")";
        }

        $sort = isset($data['sort']) ? $data['sort'] : 'sort_order';

        if ($sort === 'name') {
            $sql .= " ORDER BY LCASE(tmd.name) ASC, tm.team_member_id ASC";
        } elseif ($sort === 'date_added') {
            $sql .= " ORDER BY tm.date_added DESC, tm.team_member_id DESC";
        } else {
            $sql .= " ORDER BY tm.sort_order ASC, LCASE(tmd.name) ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            $start = max(0, (int)(isset($data['start']) ? $data['start'] : 0));
            $limit = max(1, (int)(isset($data['limit']) ? $data['limit'] : 12));
            $sql .= " LIMIT " . $start . "," . $limit;
        }

        return $this->setCached($cache_key, $this->db->query($sql)->rows);
    }

    public function getTotalMembers($team_category_id = 0, $search = '') {
        if (is_array($team_category_id)) {
            $data = $team_category_id;
            $team_category_id = isset($data['team_category_id']) ? (int)$data['team_category_id'] : 0;
            $search = isset($data['search']) ? $data['search'] : '';
        }

        $cache_key = $this->getCacheKey('member_total.' . (int)$team_category_id . '.' . md5((string)$search));
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return (int)$cached;
        }

        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_member` tm INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s ON (tm.team_member_id = tm2s.team_member_id AND tm2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_category` tc ON (tm.team_category_id = tc.team_category_id) INNER JOIN `" . DB_PREFIX . "team_category_to_store` tc2s ON (tc.team_category_id = tc2s.team_category_id AND tc2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_member_description` tmd ON (tm.team_member_id = tmd.team_member_id AND tmd.language_id = '" . $language_id . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` tcd ON (tm.team_category_id = tcd.team_category_id AND tcd.language_id = '" . $language_id . "') WHERE tm.status = '1' AND tc.status = '1' AND tmd.name IS NOT NULL";

        if ($team_category_id) {
            $sql .= " AND tm.team_category_id = '" . (int)$team_category_id . "'";
        }

        if ($search !== '') {
            $search = $this->db->escape(trim($search));
            $search_fields = array(
                "tmd.name LIKE '%" . $search . "%'",
                "tmd.short_description LIKE '%" . $search . "%'",
                "tmd.description LIKE '%" . $search . "%'",
                "tmd.city LIKE '%" . $search . "%'",
                "tcd.name LIKE '%" . $search . "%'"
            );

            if ($this->hasWorkingHoursColumn()) {
                $search_fields[] = "tmd.working_hours LIKE '%" . $search . "%'";
            }

            $sql .= " AND (" . implode(' OR ', $search_fields) . ")";
        }

        $total = (int)$this->db->query($sql)->row['total'];
        $this->setCached($cache_key, $total);

        return $total;
    }

    public function searchMembers($search, $limit = 6) {
        $search = trim($search);

        if ($search === '') {
            return array();
        }

        return $this->getMembers(array(
            'search' => $search,
            'start' => 0,
            'limit' => max(1, (int)$limit)
        ));
    }

    public function getMember($team_member_id, $team_category_id = 0) {
        $cache_key = $this->getCacheKey('member.' . (int)$team_member_id . '.category.' . (int)$team_category_id);
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $store_id = (int)$this->config->get('config_store_id');
        $sql = "SELECT tm.*, tmd.name, tmd.short_description, tmd.description, tmd.telephone, tmd.city, " . $this->getWorkingHoursSelect('tmd') . ", tmd.website, tmd.facebook, tmd.instagram, tmd.youtube, tmd.linkedin, tmd.meta_title, tmd.meta_description, tmd.meta_keyword, tcd.name AS category_name FROM `" . DB_PREFIX . "team_member` tm INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s ON (tm.team_member_id = tm2s.team_member_id AND tm2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_member_description` tmd ON (tm.team_member_id = tmd.team_member_id) LEFT JOIN `" . DB_PREFIX . "team_category` tc ON (tm.team_category_id = tc.team_category_id) INNER JOIN `" . DB_PREFIX . "team_category_to_store` tc2s ON (tc.team_category_id = tc2s.team_category_id AND tc2s.store_id = '" . $store_id . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` tcd ON (tm.team_category_id = tcd.team_category_id AND tcd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE tm.team_member_id = '" . (int)$team_member_id . "' AND tm.status = '1' AND tc.status = '1' AND tmd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if ($team_category_id) {
            $sql .= " AND tm.team_category_id = '" . (int)$team_category_id . "'";
        }

        $sql .= " LIMIT 1";

        return $this->setCached($cache_key, $this->db->query($sql)->row);
    }

    public function getMemberImages($team_member_id) {
        $cache_key = $this->getCacheKey('member_images.' . (int)$team_member_id);
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_member_image` WHERE team_member_id = '" . (int)$team_member_id . "' ORDER BY sort_order ASC, team_member_image_id ASC")->rows;

        return $this->setCached($cache_key, $rows);
    }

    public function getSitemapCategories() {
        $cache_key = $this->getCacheKey('sitemap.categories');
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $rows = $this->db->query("SELECT c.team_category_id, c.date_modified, cd.name FROM `" . DB_PREFIX . "team_category` c INNER JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (c.team_category_id = c2s.team_category_id AND c2s.store_id = '" . $store_id . "') INNER JOIN `" . DB_PREFIX . "team_category_description` cd ON (c.team_category_id = cd.team_category_id AND cd.language_id = '" . $language_id . "') WHERE c.status = '1' ORDER BY c.sort_order ASC, cd.name ASC")->rows;

        return $this->setCached($cache_key, $rows);
    }

    public function getSitemapMembers() {
        $cache_key = $this->getCacheKey('sitemap.members');
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $rows = $this->db->query("SELECT tm.team_member_id, tm.team_category_id, tm.image, tm.date_modified, tmd.name FROM `" . DB_PREFIX . "team_member` tm INNER JOIN `" . DB_PREFIX . "team_member_to_store` tm2s ON (tm.team_member_id = tm2s.team_member_id AND tm2s.store_id = '" . $store_id . "') INNER JOIN `" . DB_PREFIX . "team_member_description` tmd ON (tm.team_member_id = tmd.team_member_id AND tmd.language_id = '" . $language_id . "') INNER JOIN `" . DB_PREFIX . "team_category` tc ON (tm.team_category_id = tc.team_category_id AND tc.status = '1') INNER JOIN `" . DB_PREFIX . "team_category_to_store` tc2s ON (tc.team_category_id = tc2s.team_category_id AND tc2s.store_id = '" . $store_id . "') WHERE tm.status = '1' ORDER BY tm.sort_order ASC, tmd.name ASC")->rows;

        return $this->setCached($cache_key, $rows);
    }

    public function getSeoKeyword($query_key, $store_id = null, $language_id = null) {
        $store_id = ($store_id === null) ? (int)$this->config->get('config_store_id') : (int)$store_id;
        $language_id = ($language_id === null) ? (int)$this->config->get('config_language_id') : (int)$language_id;
        $cache_key = $this->getCacheKey('seo.' . md5($query_key), $language_id, $store_id);
        $cached = $this->getCached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $query = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query_key) . "' AND store_id = '" . $store_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
        $keyword = $query->num_rows ? $query->row['keyword'] : '';

        return $this->setCached($cache_key, $keyword);
    }

    public function getExpectedSeoRoute($team_category_id = 0, $team_member_id = 0) {
        $parts = array();
        $section_keyword = $this->getSeoKeyword('probg_team_section=1');

        if (!$section_keyword) {
            return '';
        }

        $parts[] = $section_keyword;

        if ($team_category_id) {
            $category_keyword = $this->getSeoKeyword('probg_team_category_id=' . (int)$team_category_id);

            if (!$category_keyword) {
                return '';
            }

            $parts[] = $category_keyword;
        }

        if ($team_member_id) {
            $member_keyword = $this->getSeoKeyword('probg_team_member_id=' . (int)$team_member_id);

            if (!$member_keyword) {
                return '';
            }

            $parts[] = $member_keyword;
        }

        return implode('/', $parts);
    }
}
