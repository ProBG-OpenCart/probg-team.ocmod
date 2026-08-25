<?php
require_once(DIR_SYSTEM . 'library/probg_team/slug.php');

class ModelExtensionProbgTeamCategory extends Model {
    public function prepareData($data, $category_id = 0) {
        $descriptions = isset($data['category_description']) ? (array)$data['category_description'] : array();

        foreach ($descriptions as $language_id => $description) {
            $name = isset($description['name']) ? trim($description['name']) : '';

            if (empty($description['meta_title']) && $name !== '') {
                $descriptions[$language_id]['meta_title'] = $name;
            }
        }

        $stores = $this->normalizeStores(isset($data['category_store']) ? $data['category_store'] : array(0));
        $data['category_store'] = $stores;
        $data['category_description'] = $descriptions;
        $submitted_seo_urls = isset($data['category_seo_url']) ? (array)$data['category_seo_url'] : array();
        $seo_urls = array();
        $exclude_query = 'probg_team_category_id=' . (int)$category_id;

        foreach ($stores as $store_id) {
            foreach ($descriptions as $language_id => $description) {
                $keyword = isset($submitted_seo_urls[$store_id][$language_id]) ? trim($submitted_seo_urls[$store_id][$language_id]) : '';

                if ($keyword === '') {
                    $base = ProbgTeamSlug::generate(isset($description['name']) ? $description['name'] : '');

                    if ($base === '') {
                        $base = 'category';
                    }

                    $keyword = $this->getUniqueSeoKeyword($base, (int)$store_id, (int)$language_id, $exclude_query);
                }

                $seo_urls[$store_id][$language_id] = $keyword;
            }
        }

        $data['category_seo_url'] = $seo_urls;

        return $data;
    }

    public function addCategory($data) {
        $data = $this->prepareData($data, 0);

        $this->db->query("INSERT INTO `" . DB_PREFIX . "team_category` SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_added = NOW(), date_modified = NOW()");
        $category_id = $this->db->getLastId();

        $this->saveDescriptions($category_id, $data['category_description']);
        $this->saveStores($category_id, isset($data['category_store']) ? $data['category_store'] : array(0));
        $this->saveLayouts($category_id, isset($data['category_layout']) ? $data['category_layout'] : array(), isset($data['category_store']) ? $data['category_store'] : array(0));
        $this->saveSeoUrls($category_id, isset($data['category_seo_url']) ? $data['category_seo_url'] : array(), $data['category_description'], $data['category_store']);
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));

        return $category_id;
    }

    public function editCategory($category_id, $data) {
        $data = $this->prepareData($data, $category_id);

        $this->db->query("UPDATE `" . DB_PREFIX . "team_category` SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE team_category_id = '" . (int)$category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_description` WHERE team_category_id = '" . (int)$category_id . "'");

        $this->saveDescriptions($category_id, $data['category_description']);
        $this->saveStores($category_id, isset($data['category_store']) ? $data['category_store'] : array(0));
        $this->saveLayouts($category_id, isset($data['category_layout']) ? $data['category_layout'] : array(), isset($data['category_store']) ? $data['category_store'] : array(0));
        $this->saveSeoUrls($category_id, isset($data['category_seo_url']) ? $data['category_seo_url'] : array(), $data['category_description'], $data['category_store']);
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));
    }

    protected function saveDescriptions($category_id, $descriptions) {
        foreach ($descriptions as $language_id => $description) {
            $name = isset($description['name']) ? trim($description['name']) : '';
            $meta_title = isset($description['meta_title']) ? trim($description['meta_title']) : '';

            if ($meta_title === '') {
                $meta_title = $name;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "team_category_description` SET team_category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "', description = '" . $this->db->escape(isset($description['description']) ? $description['description'] : '') . "', meta_title = '" . $this->db->escape($meta_title) . "', meta_description = '" . $this->db->escape(isset($description['meta_description']) ? $description['meta_description'] : '') . "', meta_keyword = '" . $this->db->escape(isset($description['meta_keyword']) ? $description['meta_keyword'] : '') . "'");
        }
    }

    protected function saveStores($category_id, $stores) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_to_store` WHERE team_category_id = '" . (int)$category_id . "'");

        $stores = $this->normalizeStores($stores);

        foreach ($stores as $store_id) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "team_category_to_store` SET team_category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");
        }

        // Keep member visibility consistent with the selected stores of the category.
        if ($stores) {
            $this->db->query("DELETE mts FROM `" . DB_PREFIX . "team_member_to_store` mts INNER JOIN `" . DB_PREFIX . "team_member` tm ON (mts.team_member_id = tm.team_member_id) WHERE tm.team_category_id = '" . (int)$category_id . "' AND mts.store_id NOT IN (" . implode(',', $stores) . ")");
        }
    }

    protected function saveLayouts($category_id, $layouts, $stores = array(0)) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_to_layout` WHERE team_category_id = '" . (int)$category_id . "'");

        $valid_stores = $this->normalizeStores($stores);
        $valid_store_map = array_flip($valid_stores);

        foreach ((array)$layouts as $store_id => $layout_id) {
            $store_id = (int)$store_id;
            $layout_id = (int)$layout_id;

            if ($layout_id > 0 && isset($valid_store_map[$store_id])) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "team_category_to_layout` SET team_category_id = '" . (int)$category_id . "', store_id = '" . $store_id . "', layout_id = '" . $layout_id . "'");
            }
        }
    }

    protected function saveSeoUrls($category_id, $seo_urls, $descriptions = array(), $stores = array()) {
        $query_key = 'probg_team_category_id=' . (int)$category_id;
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query_key) . "'");

        $stores = $this->normalizeStores($stores);

        foreach ($stores as $store_id) {
            $language_keywords = isset($seo_urls[$store_id]) ? (array)$seo_urls[$store_id] : array();

            foreach ($descriptions as $language_id => $description) {
                if (!isset($language_keywords[$language_id])) {
                    $language_keywords[$language_id] = '';
                }
            }

            foreach ($language_keywords as $language_id => $keyword) {
                $keyword = trim($keyword);

                if ($keyword === '' && isset($descriptions[$language_id])) {
                    $keyword = ProbgTeamSlug::generate(isset($descriptions[$language_id]['name']) ? $descriptions[$language_id]['name'] : '');
                    if ($keyword === '') {
                        $keyword = 'category';
                    }
                    $keyword = $this->getUniqueSeoKeyword($keyword, (int)$store_id, (int)$language_id, $query_key);
                }

                if ($keyword !== '') {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = '" . $this->db->escape($query_key) . "', keyword = '" . $this->db->escape($keyword) . "'");
                }
            }
        }
    }

    public function deleteCategory($category_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category` WHERE team_category_id = '" . (int)$category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_description` WHERE team_category_id = '" . (int)$category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_to_store` WHERE team_category_id = '" . (int)$category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_category_to_layout` WHERE team_category_id = '" . (int)$category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_category_id=" . (int)$category_id . "'");
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));
    }

    public function getCategory($category_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_category` WHERE team_category_id = '" . (int)$category_id . "'")->row;
    }

    public function getCategories($data = array()) {
        $sql = "SELECT c.*, cd.name, (SELECT COUNT(*) FROM `" . DB_PREFIX . "team_member` tm WHERE tm.team_category_id = c.team_category_id) AS member_total FROM `" . DB_PREFIX . "team_category` c LEFT JOIN `" . DB_PREFIX . "team_category_description` cd ON (c.team_category_id = cd.team_category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        if (!empty($data['filter_name'])) $sql .= " AND cd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        if (isset($data['filter_status']) && $data['filter_status'] !== '') $sql .= " AND c.status = '" . (int)$data['filter_status'] . "'";
        $sort_data = array('cd.name','c.sort_order','c.status','c.date_added','c.date_modified');
        $sql .= " ORDER BY " . (isset($data['sort']) && in_array($data['sort'], $sort_data) ? $data['sort'] : 'c.sort_order') . " " . (isset($data['order']) && $data['order'] == 'DESC' ? 'DESC' : 'ASC') . ", cd.name ASC";
        if (isset($data['start']) || isset($data['limit'])) {
            $start = max(0, (int)(isset($data['start']) ? $data['start'] : 0));
            $limit = max(1, (int)(isset($data['limit']) ? $data['limit'] : 20));
            $sql .= " LIMIT " . $start . "," . $limit;
        }
        return $this->db->query($sql)->rows;
    }

    public function getTotalCategories($data = array()) {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_category` c LEFT JOIN `" . DB_PREFIX . "team_category_description` cd ON (c.team_category_id = cd.team_category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        if (!empty($data['filter_name'])) $sql .= " AND cd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        if (isset($data['filter_status']) && $data['filter_status'] !== '') $sql .= " AND c.status = '" . (int)$data['filter_status'] . "'";
        return (int)$this->db->query($sql)->row['total'];
    }

    public function getDescriptions($category_id) {
        $data = array();
        foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "team_category_description` WHERE team_category_id = '" . (int)$category_id . "'")->rows as $row) {
            $data[$row['language_id']] = $row;
        }
        return $data;
    }

    public function getStores($category_id) {
        $stores = array();
        $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "team_category_to_store` WHERE team_category_id = '" . (int)$category_id . "' ORDER BY store_id ASC");

        foreach ($query->rows as $row) {
            $stores[] = (int)$row['store_id'];
        }

        return $stores;
    }

    public function getLayouts($category_id) {
        $layouts = array();
        $query = $this->db->query("SELECT store_id, layout_id FROM `" . DB_PREFIX . "team_category_to_layout` WHERE team_category_id = '" . (int)$category_id . "'");

        foreach ($query->rows as $row) {
            $layouts[(int)$row['store_id']] = (int)$row['layout_id'];
        }

        return $layouts;
    }

    public function getLayoutId($category_id, $store_id) {
        $query = $this->db->query("SELECT layout_id FROM `" . DB_PREFIX . "team_category_to_layout` WHERE team_category_id = '" . (int)$category_id . "' AND store_id = '" . (int)$store_id . "' LIMIT 1");
        return $query->num_rows ? (int)$query->row['layout_id'] : 0;
    }

    public function getSeoUrls($category_id) {
        $data = array();
        foreach ($this->db->query("SELECT store_id, language_id, keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_category_id=" . (int)$category_id . "'")->rows as $row) {
            $data[$row['store_id']][$row['language_id']] = $row['keyword'];
        }
        return $data;
    }

    public function getTotalMembersByCategoryId($category_id) {
        return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_member` WHERE team_category_id = '" . (int)$category_id . "'")->row['total'];
    }

    private function normalizeStores($stores) {
        $valid = array(0);
        $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");

        foreach ($query->rows as $row) {
            $valid[] = (int)$row['store_id'];
        }

        $stores = array_values(array_unique(array_map('intval', (array)$stores)));

        return array_values(array_intersect($stores, $valid));
    }

    public function getUniqueSeoKeyword($base, $store_id, $language_id, $exclude_query) {
        $base = ProbgTeamSlug::generate($base);
        if ($base === '') {
            $base = 'category';
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
