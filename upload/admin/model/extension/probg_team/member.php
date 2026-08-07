<?php
require_once(DIR_SYSTEM . 'library/probg_team/slug.php');

class ModelExtensionProbgTeamMember extends Model {
    public function prepareData($data, $member_id = 0) {
        $descriptions = isset($data['member_description']) ? (array)$data['member_description'] : array();

        foreach ($descriptions as $language_id => $description) {
            $name = isset($description['name']) ? trim($description['name']) : '';

            if (empty($description['meta_title']) && $name !== '') {
                $descriptions[$language_id]['meta_title'] = $name;
            }
        }

        $category_id = isset($data['team_category_id']) ? (int)$data['team_category_id'] : 0;
        $stores = $this->normalizeStores(isset($data['member_store']) ? $data['member_store'] : array(0), $category_id);
        $data['member_store'] = $stores;
        $data['member_description'] = $descriptions;

        if ($member_id > 0) {
            $submitted_seo_urls = isset($data['member_seo_url']) ? (array)$data['member_seo_url'] : array();
            $seo_urls = array();
            $exclude_query = 'probg_team_member_id=' . (int)$member_id;

            foreach ($stores as $store_id) {
                foreach ($descriptions as $language_id => $description) {
                    $keyword = isset($submitted_seo_urls[$store_id][$language_id]) ? trim($submitted_seo_urls[$store_id][$language_id]) : '';

                    if ($keyword === '') {
                        $slug = ProbgTeamSlug::generate(isset($description['name']) ? $description['name'] : '');
                        $base = (int)$member_id . ($slug !== '' ? '-' . $slug : '-member');
                        $keyword = $this->getUniqueSeoKeyword($base, (int)$store_id, (int)$language_id, $exclude_query);
                    }

                    $seo_urls[$store_id][$language_id] = $keyword;
                }
            }

            $data['member_seo_url'] = $seo_urls;
        }

        return $data;
    }

    public function addMember($data) {
        $data = $this->prepareData($data, 0);

        $image = $this->sanitizeImagePath(isset($data['image']) ? $data['image'] : '');
        $this->db->query("INSERT INTO `" . DB_PREFIX . "team_member` SET team_category_id = '" . (int)$data['team_category_id'] . "', image = '" . $this->db->escape($image) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_added = NOW(), date_modified = NOW()");
        $member_id = $this->db->getLastId();

        $data = $this->prepareData($data, $member_id);

        $this->saveDescriptions($member_id, $data['member_description']);
        $this->saveImages($member_id, isset($data['member_image']) ? $data['member_image'] : array());
        $this->saveStores($member_id, isset($data['member_store']) ? $data['member_store'] : array(0), (int)$data['team_category_id']);
        $this->saveSeoUrls($member_id, isset($data['member_seo_url']) ? $data['member_seo_url'] : array(), $data['member_description'], $data['member_store']);
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));

        return $member_id;
    }

    public function editMember($member_id, $data) {
        $data = $this->prepareData($data, $member_id);

        $image = $this->sanitizeImagePath(isset($data['image']) ? $data['image'] : '');
        $this->db->query("UPDATE `" . DB_PREFIX . "team_member` SET team_category_id = '" . (int)$data['team_category_id'] . "', image = '" . $this->db->escape($image) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_description` WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_image` WHERE team_member_id = '" . (int)$member_id . "'");

        $this->saveDescriptions($member_id, $data['member_description']);
        $this->saveImages($member_id, isset($data['member_image']) ? $data['member_image'] : array());
        $this->saveStores($member_id, isset($data['member_store']) ? $data['member_store'] : array(0), (int)$data['team_category_id']);
        $this->saveSeoUrls($member_id, isset($data['member_seo_url']) ? $data['member_seo_url'] : array(), $data['member_description'], $data['member_store']);
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));
    }

    protected function saveDescriptions($member_id, $descriptions) {
        foreach ($descriptions as $language_id => $description) {
            $name = isset($description['name']) ? trim($description['name']) : '';
            $meta_title = isset($description['meta_title']) ? trim($description['meta_title']) : '';

            if ($meta_title === '') {
                $meta_title = $name;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "team_member_description` SET team_member_id = '" . (int)$member_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "', short_description = '" . $this->db->escape(isset($description['short_description']) ? $description['short_description'] : '') . "', description = '" . $this->db->escape(isset($description['description']) ? $description['description'] : '') . "', telephone = '" . $this->db->escape(isset($description['telephone']) ? $description['telephone'] : '') . "', city = '" . $this->db->escape(isset($description['city']) ? $description['city'] : '') . "', working_hours = '" . $this->db->escape(isset($description['working_hours']) ? $description['working_hours'] : '') . "', website = '" . $this->db->escape(isset($description['website']) ? $description['website'] : '') . "', facebook = '" . $this->db->escape(isset($description['facebook']) ? $description['facebook'] : '') . "', instagram = '" . $this->db->escape(isset($description['instagram']) ? $description['instagram'] : '') . "', youtube = '" . $this->db->escape(isset($description['youtube']) ? $description['youtube'] : '') . "', linkedin = '" . $this->db->escape(isset($description['linkedin']) ? $description['linkedin'] : '') . "', meta_title = '" . $this->db->escape($meta_title) . "', meta_description = '" . $this->db->escape(isset($description['meta_description']) ? $description['meta_description'] : '') . "', meta_keyword = '" . $this->db->escape(isset($description['meta_keyword']) ? $description['meta_keyword'] : '') . "'");
        }
    }

    protected function saveImages($member_id, $rows) {
        foreach ($rows as $row) {
            $image = $this->sanitizeImagePath(isset($row['image']) ? $row['image'] : '');

            if ($image !== '') {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "team_member_image` SET team_member_id = '" . (int)$member_id . "', image = '" . $this->db->escape($image) . "', sort_order = '" . (int)(isset($row['sort_order']) ? $row['sort_order'] : 0) . "'");
            }
        }
    }

    private function sanitizeImagePath($image) {
        $image = ltrim(str_replace('\\', '/', trim((string)$image)), '/');

        if ($image === '' || strpos($image, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $image)) {
            return '';
        }

        return $image;
    }

    protected function saveStores($member_id, $stores, $category_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_to_store` WHERE team_member_id = '" . (int)$member_id . "'");

        foreach ($this->normalizeStores($stores, $category_id) as $store_id) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "team_member_to_store` SET team_member_id = '" . (int)$member_id . "', store_id = '" . (int)$store_id . "'");
        }
    }

    protected function saveSeoUrls($member_id, $seo_urls, $descriptions = array(), $stores = array()) {
        $query_key = 'probg_team_member_id=' . (int)$member_id;
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query_key) . "'");

        foreach (array_values(array_unique(array_map('intval', (array)$stores))) as $store_id) {
            $language_keywords = isset($seo_urls[$store_id]) ? (array)$seo_urls[$store_id] : array();

            foreach ($descriptions as $language_id => $description) {
                if (!isset($language_keywords[$language_id])) {
                    $language_keywords[$language_id] = '';
                }
            }

            foreach ($language_keywords as $language_id => $keyword) {
                $keyword = trim($keyword);

                if ($keyword === '' && isset($descriptions[$language_id])) {
                    $slug = ProbgTeamSlug::generate(isset($descriptions[$language_id]['name']) ? $descriptions[$language_id]['name'] : '');
                    $base = (int)$member_id . ($slug !== '' ? '-' . $slug : '-member');
                    $keyword = $this->getUniqueSeoKeyword($base, (int)$store_id, (int)$language_id, $query_key);
                }

                if ($keyword !== '') {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = '" . $this->db->escape($query_key) . "', keyword = '" . $this->db->escape($keyword) . "'");
                }
            }
        }
    }

    public function deleteMember($member_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member` WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_description` WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_image` WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_member_to_store` WHERE team_member_id = '" . (int)$member_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'probg_team_member_id=" . (int)$member_id . "'");
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));
    }

    public function getMember($member_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_member` WHERE team_member_id = '" . (int)$member_id . "'")->row;
    }

    public function getMembers($data = array()) {
        $sql = "SELECT m.*, md.name, md.city, cd.name AS category FROM `" . DB_PREFIX . "team_member` m LEFT JOIN `" . DB_PREFIX . "team_member_description` md ON (m.team_member_id = md.team_member_id AND md.language_id = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "team_category_description` cd ON (m.team_category_id = cd.team_category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE 1";
        if (!empty($data['filter_name'])) $sql .= " AND md.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        if (!empty($data['filter_member_id'])) $sql .= " AND m.team_member_id = '" . (int)$data['filter_member_id'] . "'";
        if (!empty($data['filter_category_id'])) $sql .= " AND m.team_category_id = '" . (int)$data['filter_category_id'] . "'";
        if (!empty($data['filter_city'])) $sql .= " AND md.city LIKE '%" . $this->db->escape($data['filter_city']) . "%'";
        if (isset($data['filter_status']) && $data['filter_status'] !== '') $sql .= " AND m.status = '" . (int)$data['filter_status'] . "'";
        $allowed = array('m.team_member_id','md.name','md.city','cd.name','m.sort_order','m.status','m.date_modified');
        $sql .= " ORDER BY " . (isset($data['sort']) && in_array($data['sort'], $allowed) ? $data['sort'] : 'm.sort_order') . " " . (isset($data['order']) && $data['order'] == 'DESC' ? 'DESC' : 'ASC') . ", md.name ASC";
        if (isset($data['start']) || isset($data['limit'])) {
            $sql .= " LIMIT " . max(0, (int)(isset($data['start']) ? $data['start'] : 0)) . "," . max(1, (int)(isset($data['limit']) ? $data['limit'] : 20));
        }
        return $this->db->query($sql)->rows;
    }

    public function getTotalMembers($data = array()) {
        $sql = "SELECT COUNT(*) total FROM `" . DB_PREFIX . "team_member` m LEFT JOIN `" . DB_PREFIX . "team_member_description` md ON (m.team_member_id = md.team_member_id AND md.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE 1";
        if (!empty($data['filter_name'])) $sql .= " AND md.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        if (!empty($data['filter_member_id'])) $sql .= " AND m.team_member_id = '" . (int)$data['filter_member_id'] . "'";
        if (!empty($data['filter_category_id'])) $sql .= " AND m.team_category_id = '" . (int)$data['filter_category_id'] . "'";
        if (!empty($data['filter_city'])) $sql .= " AND md.city LIKE '%" . $this->db->escape($data['filter_city']) . "%'";
        if (isset($data['filter_status']) && $data['filter_status'] !== '') $sql .= " AND m.status = '" . (int)$data['filter_status'] . "'";
        return (int)$this->db->query($sql)->row['total'];
    }

    public function getDescriptions($member_id) {
        $data = array();
        foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "team_member_description` WHERE team_member_id = '" . (int)$member_id . "'")->rows as $row) {
            $data[$row['language_id']] = $row;
        }
        return $data;
    }

    public function getImages($member_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_member_image` WHERE team_member_id = '" . (int)$member_id . "' ORDER BY sort_order ASC, team_member_image_id ASC")->rows;
    }

    public function getStores($member_id) {
        $stores = array();
        $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "team_member_to_store` WHERE team_member_id = '" . (int)$member_id . "' ORDER BY store_id ASC");

        foreach ($query->rows as $row) {
            $stores[] = (int)$row['store_id'];
        }

        return $stores;
    }

    public function getSeoUrls($member_id) {
        $data = array();
        foreach ($this->db->query("SELECT store_id, language_id, keyword FROM `" . DB_PREFIX . "seo_url` WHERE query = 'probg_team_member_id=" . (int)$member_id . "'")->rows as $row) {
            $data[$row['store_id']][$row['language_id']] = $row['keyword'];
        }
        return $data;
    }

    private function normalizeStores($stores, $category_id = 0) {
        $valid = array();

        if ($category_id) {
            $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "team_category_to_store` WHERE team_category_id = '" . (int)$category_id . "'");

            foreach ($query->rows as $row) {
                $valid[] = (int)$row['store_id'];
            }
        }

        if (!$valid) {
            $valid = array(0);
            $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");

            foreach ($query->rows as $row) {
                $valid[] = (int)$row['store_id'];
            }
        }

        $stores = array_values(array_unique(array_map('intval', (array)$stores)));

        return array_values(array_intersect($stores, $valid));
    }

    public function getUniqueSeoKeyword($base, $store_id, $language_id, $exclude_query) {
        $base = ProbgTeamSlug::generate($base);
        if ($base === '') {
            $base = 'member';
        }

        $keyword = $base;
        $suffix = 2;

        while ($this->isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query)) {
            $keyword = $base . '-' . $suffix;
            $suffix++;
        }

        return $keyword;
    }

    public function isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query) {
        return (bool)$this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language_id . "' AND query != '" . $this->db->escape($exclude_query) . "' LIMIT 1")->num_rows;
    }
}
